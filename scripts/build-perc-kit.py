#!/usr/bin/env python3
"""Arma public/sounds/perc/ desde un SoundFont FluidR3 GM.

Uso:
  python3 scripts/build-perc-kit.py /ruta/FluidR3_GM_GS.sf2
"""
from __future__ import annotations

import array
import os
import struct
import subprocess
import sys
import wave

TAIL = (
    "afade=t=in:st=0:d=0.003,areverse,"
    "silenceremove=start_periods=1:start_threshold=-46dB:start_silence=0.01,areverse,"
    "silenceremove=start_periods=1:start_threshold=-46dB:start_silence=0.008,"
    "dynaudnorm=f=75:g=7,alimiter=limit=0.89:attack=1:release=20"
)


def parse_sf2(path: str):
    data = open(path, "rb").read()
    i = 12
    sdta = pdta = None
    while i + 8 <= len(data):
        ck, sz = data[i : i + 4], struct.unpack_from("<I", data, i + 4)[0]
        if ck == b"LIST":
            form = data[i + 8 : i + 12]
            if form == b"sdta":
                sdta = (i + 12, sz - 4)
            if form == b"pdta":
                pdta = (i + 12, sz - 4)
        i += 8 + sz + (sz & 1)
    off, sz = sdta
    end, p, smpl = off + sz, off, None
    while p + 8 <= end:
        ck, csz = data[p : p + 4], struct.unpack_from("<I", data, p + 4)[0]
        if ck == b"smpl":
            smpl = data[p + 8 : p + 8 + csz]
        p += 8 + csz + (csz & 1)
    off, sz = pdta
    end, p, shdr = off + sz, off, None
    while p + 8 <= end:
        ck, csz = data[p : p + 4], struct.unpack_from("<I", data, p + 4)[0]
        if ck == b"shdr":
            shdr = (p + 8, csz)
        p += 8 + csz + (csz & 1)
    samples = {}
    off, csz = shdr
    for k in range(csz // 46):
        rec = data[off + k * 46 : off + (k + 1) * 46]
        name = rec[:20].split(b"\x00", 1)[0].decode("ascii", "replace")
        start, endf, _s, _e, rate = struct.unpack_from("<IIIII", rec, 20)
        samples[name] = (start, endf, rate)
    return smpl, samples


def frames(smpl, samples, name):
    start, endf, rate = samples[name]
    pcm = array.array("h")
    pcm.frombytes(smpl[start * 2 : endf * 2])
    return pcm, rate


def stereo(smpl, samples, base):
    for L, R in ((f"{base}(L)", f"{base}(R)"), (base, None)):
        if L in samples and (R is None or R in samples):
            left, rate = frames(smpl, samples, L)
            right = frames(smpl, samples, R)[0] if R else left
            return left, right, rate
    raise KeyError(base)


def write_wav(path, left, right, rate):
    n = min(len(left), len(right))
    interleaved = array.array("h")
    for i in range(n):
        interleaved.append(left[i])
        interleaved.append(right[i])
    with wave.open(path, "w") as w:
        w.setnchannels(2)
        w.setsampwidth(2)
        w.setframerate(rate)
        w.writeframes(interleaved.tobytes())


def pitch(ratio: float) -> str:
    return f"asetrate=44100*{ratio},aresample=44100,aformat=s16"


def main():
    if len(sys.argv) < 2:
        print("Uso: python3 scripts/build-perc-kit.py FluidR3_GM_GS.sf2", file=sys.stderr)
        sys.exit(2)
    root = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
    dest_dir = os.path.join(root, "public", "sounds", "perc")
    raw_dir = os.path.join(dest_dir, ".raw")
    os.makedirs(raw_dir, exist_ok=True)
    smpl, samples = parse_sf2(sys.argv[1])
    raw = {}
    for key, base in {
        "floor": "Tom Floor",
        "tom_low": "Tom Low",
        "rim": "Rim Tap",
        "sticks": "Sticks",
        "clap": "Clap",
        "kick_short": "Std Kick 7",
        "kick": "Std Kick",
        "orch_snare": "Orch Snare",
        "power_snare": "Power Snare 1",
        "power_snare2": "Power Snare 2",
        "timb_hi": "High Timbale",
        "timb_lo": "Low Timbale",
        "conga_hi": "High Conga",
        "bongo_rim": "Bongo Rim",
        "agogo_hi": "High Agogo",
        "agogo_lo": "Low Agogo",
        "wood_hi": "High Woodblock",
        "snare_808": "808 Snare 1",
    }.items():
        left, right, rate = stereo(smpl, samples, base)
        path = os.path.join(raw_dir, f"{key}.wav")
        write_wav(path, left, right, rate)
        raw[key] = path

    kit = {
        "surdo_grave_normal.wav": (raw["floor"], f"{pitch(0.78)},lowpass=f=420,{TAIL}"),
        "surdo_grave_tapado.wav": (raw["kick_short"], f"{pitch(0.72)},lowpass=f=280,afade=t=out:st=0.08:d=0.06,{TAIL}"),
        "surdo_grave_chapa.wav": (raw["sticks"], f"highpass=f=1200,{TAIL}"),
        "surdo_medio_normal.wav": (raw["floor"], f"{pitch(0.94)},lowpass=f=520,{TAIL}"),
        "surdo_medio_tapado.wav": (raw["kick"], f"{pitch(0.88)},lowpass=f=340,afade=t=out:st=0.07:d=0.05,{TAIL}"),
        "surdo_medio_chapa.wav": (raw["rim"], f"highpass=f=1400,afade=t=out:st=0.12:d=0.08,{TAIL}"),
        "surdo_agudo_normal.wav": (raw["tom_low"], f"{pitch(1.06)},lowpass=f=700,{TAIL}"),
        "surdo_agudo_tapado.wav": (raw["kick_short"], f"{pitch(1.05)},lowpass=f=500,afade=t=out:st=0.06:d=0.05,{TAIL}"),
        "surdo_agudo_chapa.wav": (raw["wood_hi"], f"highpass=f=1800,{TAIL}"),
        "redoblante_normal.wav": (raw["orch_snare"], TAIL),
        "redoblante_acentuado.wav": (raw["power_snare"], TAIL),
        "redoblante_chapa.wav": (raw["rim"], f"highpass=f=900,afade=t=out:st=0.14:d=0.08,{TAIL}"),
        "repique_normal.wav": (raw["power_snare2"], f"{pitch(1.18)},highpass=f=200,{TAIL}"),
        "repique_acentuado.wav": (raw["power_snare2"], f"{pitch(1.22)},volume=1.15,{TAIL}"),
        "repique_chapa.wav": (raw["sticks"], f"{pitch(1.35)},highpass=f=2000,{TAIL}"),
        "repique_agudo.wav": (raw["snare_808"], f"{pitch(1.4)},highpass=f=400,{TAIL}"),
        "timbal_abierto.wav": (raw["timb_hi"], TAIL),
        "timbal_slap.wav": (raw["bongo_rim"], f"highpass=f=400,{TAIL}"),
        "timbal_palma.wav": (raw["clap"], TAIL),
        "timbal_presionado.wav": (raw["timb_lo"], f"lowpass=f=900,afade=t=out:st=0.12:d=0.08,{TAIL}"),
        "timbal_dedo.wav": (raw["conga_hi"], f"{pitch(1.25)},highpass=f=500,afade=t=out:st=0.08:d=0.05,{TAIL}"),
        "agogo_normal.wav": (raw["agogo_hi"], TAIL),
        "agogo_acentuado.wav": (raw["agogo_hi"], f"volume=1.2,{TAIL}"),
        "agogo_tapado.wav": (raw["agogo_lo"], f"afade=t=out:st=0.08:d=0.05,{TAIL}"),
        "palmas_normal.wav": (raw["clap"], TAIL),
        "palmas_acentuado.wav": (raw["clap"], f"volume=1.25,{TAIL}"),
    }
    for name, (src, filt) in kit.items():
        dest = os.path.join(dest_dir, name)
        subprocess.check_call(
            ["ffmpeg", "-y", "-hide_banner", "-loglevel", "error", "-i", src, "-af", filt, "-ar", "44100", "-ac", "2", "-sample_fmt", "s16", dest]
        )
        print(name, os.path.getsize(dest))


if __name__ == "__main__":
    main()
