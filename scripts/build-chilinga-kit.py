#!/usr/bin/env python3
"""Arma public/sounds/perc/ con carácter de bloque Chilinga.

No usa FluidR3 (toms largos / 808). Mezcla y recorta one-shots CC0:
  - Versilian Community Sample Library (VCSL)
  - FreePats World Percussion 2020-09-05

El surdo queda grave y corto (~0.18–0.22 s), no boom de batucada.
El golpe agudo (triángulo del cuadernillo) sale de triángulo muted, no de 808.

Uso:
  python3 scripts/build-chilinga-kit.py [/ruta/chilinga-kit-src]

Por defecto usa scripts/chilinga-kit-src (va en el repo). start.sh lo invoca al arrancar.
Hace falta ffmpeg en el PATH.

Layout esperado:
  <src>/vcsl/*.wav
  <src>/world-percussion-2020-09-05/samples/...
"""
from __future__ import annotations

import os
import subprocess
import sys

NORM = "dynaudnorm=f=120:g=4,alimiter=limit=0.88:attack=1:release=12"
STEREO = "aformat=sample_fmts=s16:channel_layouts=stereo"


def pitch(ratio: float) -> str:
    return f"asetrate=44100*{ratio:.4f},aresample=44100"


def fade(hold: float, tail: float) -> str:
    total = hold + tail
    return f"atrim=0:{total:.3f},afade=t=in:st=0:d=0.002,afade=t=out:st={hold:.3f}:d={tail:.3f}"


def ffmpeg(args: list[str]) -> None:
    subprocess.check_call(["ffmpeg", "-y", "-hide_banner", "-loglevel", "error", *args])


def one(src: str, dest: str, filt: str) -> None:
    ffmpeg(["-i", src, "-af", f"{filt},{NORM},{STEREO}", "-ar", "44100", "-ac", "2", "-sample_fmt", "s16", dest])


def mix2(a: str, b: str, dest: str, fa: str, fb: str, after: str) -> None:
    ffmpeg(
        [
            "-i", a,
            "-i", b,
            "-filter_complex",
            f"[0:a]{fa}[a];[1:a]{fb}[b];[a][b]amix=inputs=2:duration=shortest:dropout_transition=0:normalize=0,{after},{NORM},{STEREO}",
            "-ar", "44100",
            "-ac", "2",
            "-sample_fmt", "s16",
            dest,
        ]
    )


def mix3(a: str, b: str, c: str, dest: str, fa: str, fb: str, fc: str, after: str) -> None:
    ffmpeg(
        [
            "-i", a,
            "-i", b,
            "-i", c,
            "-filter_complex",
            f"[0:a]{fa}[a];[1:a]{fb}[b];[2:a]{fc}[c];"
            f"[a][b][c]amix=inputs=3:duration=shortest:dropout_transition=0:normalize=0,{after},{NORM},{STEREO}",
            "-ar", "44100",
            "-ac", "2",
            "-sample_fmt", "s16",
            dest,
        ]
    )


def require(path: str) -> str:
    if not os.path.isfile(path):
        raise SystemExit(f"Falta fuente: {path}")
    return path


def require_ffmpeg() -> None:
    try:
        subprocess.check_call(["ffmpeg", "-version"], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    except (OSError, subprocess.CalledProcessError):
        raise SystemExit("Falta ffmpeg en el PATH (lo instala el Dockerfile; en local: apt install ffmpeg).")


def main() -> None:
    require_ffmpeg()
    root = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
    default_src = os.path.join(root, "scripts", "chilinga-kit-src")
    src_root = sys.argv[1] if len(sys.argv) > 1 else os.environ.get("CHILINGA_KIT_SRC", default_src)
    vcsl = os.path.join(src_root, "vcsl")
    wp = os.path.join(src_root, "world-percussion-2020-09-05", "samples")
    dest_dir = os.path.join(root, "public", "sounds", "perc")
    os.makedirs(dest_dir, exist_ok=True)

    bass_f = require(os.path.join(vcsl, "bass_f.wav"))
    bass_mf = require(os.path.join(vcsl, "bass_mf.wav"))
    frame = require(os.path.join(vcsl, "frame.wav"))
    frame_mut = require(os.path.join(vcsl, "frame_mut.wav"))
    tom = require(os.path.join(vcsl, "tom.wav"))
    snare = require(os.path.join(vcsl, "snare.wav"))
    snare_acc = require(os.path.join(vcsl, "snare_acc.wav"))
    xstick = require(os.path.join(vcsl, "xstick.wav"))
    rope_snare = require(os.path.join(vcsl, "rope_snare.wav"))
    rope_side = require(os.path.join(vcsl, "rope_side.wav"))
    tri_mut = require(os.path.join(vcsl, "tri_mut.wav"))
    quinto = require(os.path.join(vcsl, "quinto.wav"))
    conga_slap = require(os.path.join(vcsl, "conga_slap.wav"))
    tumba_mut = require(os.path.join(vcsl, "tumba_mut.wav"))
    cowbell = require(os.path.join(vcsl, "cowbell.wav"))
    cowbell_mut = require(os.path.join(vcsl, "cowbell_mut.wav"))
    agogo_hi = require(os.path.join(vcsl, "agogo_hi.wav"))
    agogo_lo = require(os.path.join(vcsl, "agogo_lo.wav"))
    clap = require(os.path.join(vcsl, "clap.wav"))

    doom = require(os.path.join(wp, "Darbuka", "doom_01_01.flac"))
    cajon = require(os.path.join(wp, "CajonFlamenco", "101.flac"))
    claves = require(os.path.join(wp, "Claves", "01.flac"))
    handclap = require(os.path.join(wp, "HandClap", "01_02.flac"))
    bongo = require(os.path.join(wp, "Bongos", "1_01.flac"))
    muted_conga = require(os.path.join(wp, "MutedConga", "v2_02_01.flac"))

    jobs: list[tuple[str, callable]] = []

    def put(name: str, fn) -> None:
        dest = os.path.join(dest_dir, name)

        def run() -> None:
            fn(dest)
            print(name, os.path.getsize(dest))

        jobs.append((name, run))

    # Surdo grave: cuerpo de bombo + doom de darbuka afinado abajo + cajón. Decay corto (zinguero).
    put(
        "surdo_grave_normal.wav",
        lambda d: mix3(
            bass_f, doom, cajon, d,
            f"{pitch(0.80)},highpass=f=32,lowpass=f=340,volume=1.05",
            f"{pitch(0.55)},lowpass=f=210,volume=0.72",
            f"{pitch(0.70)},lowpass=f=280,volume=0.55",
            fade(0.11, 0.09),
        ),
    )
    put(
        "surdo_grave_tapado.wav",
        lambda d: mix2(
            bass_mf, muted_conga, d,
            f"{pitch(0.74)},lowpass=f=240,volume=1.0",
            f"{pitch(0.65)},lowpass=f=260,volume=0.45",
            fade(0.05, 0.05),
        ),
    )
    put(
        "surdo_grave_chapa.wav",
        lambda d: mix2(
            xstick, claves, d,
            "highpass=f=1400,volume=0.95",
            f"{pitch(0.92)},highpass=f=1800,volume=0.55",
            fade(0.04, 0.05),
        ),
    )

    put(
        "surdo_medio_normal.wav",
        lambda d: mix2(
            tom, bass_mf, d,
            f"{pitch(0.90)},lowpass=f=480,volume=1.0",
            f"{pitch(0.92)},lowpass=f=360,volume=0.45",
            fade(0.10, 0.08),
        ),
    )
    put(
        "surdo_medio_tapado.wav",
        lambda d: one(frame_mut, d, f"{pitch(0.88)},lowpass=f=320,{fade(0.045, 0.045)}"),
    )
    put(
        "surdo_medio_chapa.wav",
        lambda d: one(xstick, d, f"highpass=f=1500,{fade(0.04, 0.05)}"),
    )

    put(
        "surdo_agudo_normal.wav",
        lambda d: mix2(
            tom, frame, d,
            f"{pitch(1.12)},lowpass=f=620,volume=1.0",
            f"{pitch(1.05)},lowpass=f=700,volume=0.35",
            fade(0.09, 0.07),
        ),
    )
    put(
        "surdo_agudo_tapado.wav",
        lambda d: one(frame_mut, d, f"{pitch(1.08)},lowpass=f=480,{fade(0.04, 0.04)}"),
    )
    put(
        "surdo_agudo_chapa.wav",
        lambda d: one(claves, d, f"{pitch(1.15)},highpass=f=1900,{fade(0.035, 0.04)}"),
    )

    put(
        "redoblante_normal.wav",
        lambda d: one(snare, d, f"highpass=f=140,lowpass=f=8500,{fade(0.12, 0.08)}"),
    )
    put(
        "redoblante_acentuado.wav",
        lambda d: one(snare_acc, d, f"highpass=f=120,volume=1.12,{fade(0.13, 0.08)}"),
    )
    put(
        "redoblante_chapa.wav",
        lambda d: one(xstick, d, f"highpass=f=900,{fade(0.06, 0.06)}"),
    )
    # Triángulo del cuadernillo (Oxosi): ping de borde, no 808.
    put(
        "redoblante_agudo.wav",
        lambda d: one(tri_mut, d, f"{pitch(0.92)},highpass=f=700,volume=0.95,{fade(0.05, 0.05)}"),
    )

    put(
        "repique_normal.wav",
        lambda d: one(rope_snare, d, f"{pitch(1.22)},highpass=f=280,{fade(0.08, 0.06)}"),
    )
    put(
        "repique_acentuado.wav",
        lambda d: one(rope_snare, d, f"{pitch(1.28)},highpass=f=260,volume=1.15,{fade(0.09, 0.06)}"),
    )
    put(
        "repique_chapa.wav",
        lambda d: one(rope_side, d, f"{pitch(1.18)},highpass=f=1600,{fade(0.05, 0.05)}"),
    )
    put(
        "repique_agudo.wav",
        lambda d: one(tri_mut, d, f"{pitch(1.08)},highpass=f=950,volume=1.0,{fade(0.05, 0.05)}"),
    )

    # Timbal: piel (quinto/conga) + ataque de metal (cowbell bajo). No hay timbal GM en este kit.
    put(
        "timbal_abierto.wav",
        lambda d: mix2(
            quinto, cowbell, d,
            f"{pitch(0.98)},highpass=f=180,volume=1.05",
            f"{pitch(0.85)},highpass=f=600,lowpass=f=4200,volume=0.22",
            fade(0.10, 0.08),
        ),
    )
    put(
        "timbal_slap.wav",
        lambda d: mix2(
            conga_slap, cowbell_mut, d,
            "highpass=f=350,volume=1.1",
            f"{pitch(1.05)},highpass=f=800,volume=0.18",
            fade(0.07, 0.06),
        ),
    )
    put(
        "timbal_palma.wav",
        lambda d: one(handclap, d, f"highpass=f=400,{fade(0.08, 0.06)}"),
    )
    put(
        "timbal_presionado.wav",
        lambda d: one(tumba_mut, d, f"lowpass=f=900,{fade(0.06, 0.05)}"),
    )
    put(
        "timbal_dedo.wav",
        lambda d: one(bongo, d, f"{pitch(1.18)},highpass=f=500,{fade(0.04, 0.04)}"),
    )

    put(
        "agogo_normal.wav",
        lambda d: one(agogo_hi, d, f"volume=0.72,{fade(0.12, 0.10)}"),
    )
    put(
        "agogo_acentuado.wav",
        lambda d: one(agogo_hi, d, f"volume=0.88,{fade(0.13, 0.10)}"),
    )
    put(
        "agogo_tapado.wav",
        lambda d: one(agogo_lo, d, f"volume=0.7,{fade(0.05, 0.05)}"),
    )

    put(
        "palmas_normal.wav",
        lambda d: mix2(
            handclap, clap, d,
            "volume=1.0",
            "volume=0.35",
            fade(0.08, 0.06),
        ),
    )
    put(
        "palmas_acentuado.wav",
        lambda d: one(handclap, d, f"volume=1.15,{fade(0.09, 0.06)}"),
    )

    for _name, run in jobs:
        run()

    wavs = sorted(f for f in os.listdir(dest_dir) if f.endswith(".wav"))
    print(f"\n{len(wavs)} WAV en {dest_dir}")


if __name__ == "__main__":
    main()
