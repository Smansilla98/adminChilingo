#!/usr/bin/env python3
"""Genera MIDI a partir de JSON v4 del Cuadernillo.

Tipo: MIDI GENERADO A PARTIR DE ANÁLISIS (transcripción pedagógica).
No es MIDI oficial de La Chilinga ni del disco.

Canal 10, TPQ=48, mapa GM/GM2 de instruments.js.

Uso:
  python3 database/data/partituras-v4/midi_from_v4.py
  python3 database/data/partituras-v4/midi_from_v4.py 26-toque-a-oxosi.json
"""
import json
import os
import sys

BASE = os.path.dirname(os.path.abspath(__file__))
OUT = os.path.join(BASE, 'investigacion', 'midi')
TPQ = 48
UNISONO = 'todos'
DEFAULT_REALES = [
    'surdo_grave', 'surdo_agudo', 'surdo_medio',
    'redoblante', 'repique', 'timbal',
]

DUR_TICKS = {'w': TPQ * 4, 'h': TPQ * 2, 'q': TPQ, '8': TPQ // 2, '16': TPQ // 4, '32': TPQ // 8}

GAIN = {
    'nota': 1, 'acentuado': 1.2, 'chapa': 0.85, 'tapado': 0.6,
    'presionado': 0.55, 'abierto': 1.1, 'slap': 1.15, 'palma': 0.9,
    'dedo': 0.5, 'agudo': 1, 'flam': 1,
}

MIDI_POR_GOLPE = {
    'timbal': {'abierto': 66, 'slap': 65, 'tapado': 64, 'palma': 39, 'dedo': 62,
               'presionado': 64, 'acentuado': 66, 'nota': 66},
    'redoblante': {'nota': 38, 'acentuado': 40, 'chapa': 37, 'tapado': 37, 'flam': 38, 'agudo': 43},
    'repique': {'nota': 40, 'acentuado': 40, 'chapa': 37, 'agudo': 43, 'flam': 40},
    'surdo_grave': {'nota': 87, 'acentuado': 87, 'chapa': 37, 'tapado': 86, 'flam': 87},
    'surdo_medio': {'nota': 41, 'acentuado': 41, 'chapa': 37, 'tapado': 86, 'flam': 41},
    'surdo_agudo': {'nota': 43, 'acentuado': 43, 'chapa': 37, 'tapado': 86, 'flam': 43},
    'agogo': {'nota': 67, 'acentuado': 67, 'tapado': 68, 'chapa': 68, 'flam': 67},
    'palmas': {'nota': 39, 'acentuado': 39, 'flam': 39},
}

MIDI_BASE = {
    'todos': 38, 'surdo_grave': 87, 'surdo_agudo': 43, 'surdo_medio': 41,
    'redoblante': 38, 'repique': 40, 'timbal': 66, 'agogo': 67, 'palmas': 39,
}

AVISO = (
    'MIDI GENERADO A PARTIR DE ANALISIS — Cuadernillo de Toques. '
    'No es MIDI oficial de La Chilinga.'
)


def ticks_nota(n):
    t = DUR_TICKS.get(n.get('dur'), TPQ)
    dots = min(2, max(0, n.get('dots') or 0))
    if dots == 1:
        t = t * 1.5
    elif dots == 2:
        t = t * 1.75
    tup = n.get('tuplet') or {}
    if tup.get('num') and tup.get('den'):
        t = t * tup['den'] / tup['num']
    return round(t)


def ticks_compas(ts):
    num = (ts or {}).get('num') or 4
    den = (ts or {}).get('den') or 4
    return round((num * TPQ * 4) / den)


def midi_de_golpe(inst_id, stroke):
    mapa = MIDI_POR_GOLPE.get(inst_id) or {}
    if stroke in mapa:
        return mapa[stroke]
    return MIDI_BASE.get(inst_id, 38)


def voces_unisono(score):
    reales = [i['id'] for i in score.get('instruments') or [] if i['id'] != UNISONO]
    return reales or DEFAULT_REALES


def expandir_timeline(score):
    out = []
    for si, sec in enumerate(score.get('sections') or []):
        pasada = []
        inicio = 0
        for mi, m in enumerate(sec.get('measures') or []):
            if m.get('repeatBegin'):
                inicio = mi
            pasada.append((si, mi))
            if m.get('repeatEnd'):
                for k in range(inicio, mi + 1):
                    pasada.append((si, k))
                inicio = mi + 1
        rx = sec.get('repeatX') or 1
        for _ in range(rx):
            out.extend(pasada)
    return out


def var_len(n):
    n = max(0, int(n))
    bytes_ = [n & 0x7F]
    v = n >> 7
    while v > 0:
        bytes_.insert(0, (v & 0x7F) | 0x80)
        v >>= 7
    return bytes_


def texto_bytes(s):
    enc = s.encode('latin-1', 'replace')[:80]
    return [len(enc), *enc]


def generar_midi(score):
    capacidad = ticks_compas(score.get('timeSignature'))
    eventos = []
    cursor = 0
    for si, mi in expandir_timeline(score):
        m = score['sections'][si]['measures'][mi]
        for cfg in score.get('instruments') or []:
            if cfg.get('mute'):
                continue
            destinos = voces_unisono(score) if cfg['id'] == UNISONO else [cfg['id']]
            for inst_id in destinos:
                local = 0
                for n in m.get('voces', {}).get(cfg['id']) or []:
                    if not n.get('rest'):
                        stroke = n.get('stroke') or 'nota'
                        note = midi_de_golpe(inst_id, stroke)
                        vel = max(20, min(127, round(96 * GAIN.get(stroke, 1) * (cfg.get('volume') or 1))))
                        eventos.append((cursor + local, True, note, vel))
                        eventos.append((cursor + local + 6, False, note, 0))
                    local += ticks_nota(n)
        cursor += capacidad

    eventos.sort(key=lambda e: (e[0], 0 if not e[1] else 1))

    us = round(60_000_000 / (score.get('tempo') or 100))
    title = (score.get('title') or 'toque')[:40]
    track = []
    track += var_len(0) + [0xFF, 0x51, 0x03, (us >> 16) & 0xFF, (us >> 8) & 0xFF, us & 0xFF]
    ts = score.get('timeSignature') or {'num': 4, 'den': 4}
    den_log = {1: 0, 2: 1, 4: 2, 8: 3, 16: 4}.get(ts.get('den'), 2)
    track += var_len(0) + [0xFF, 0x58, 0x04, ts.get('num', 4), den_log, 24, 8]
    track += var_len(0) + [0xFF, 0x03, *texto_bytes(title)]
    track += var_len(0) + [0xFF, 0x01, *texto_bytes(AVISO)]

    ultimo = 0
    for t, on, note, vel in eventos:
        delta = max(0, round(t - ultimo))
        ultimo = t
        track += var_len(delta) + [0x99 if on else 0x89, note & 0x7F, vel & 0x7F]
    track += var_len(0) + [0xFF, 0x2F, 0x00]

    header = [0x4D, 0x54, 0x68, 0x64, 0, 0, 0, 6, 0, 0, 0, 1, (TPQ >> 8) & 0xFF, TPQ & 0xFF]
    ln = len(track)
    th = [0x4D, 0x54, 0x72, 0x6B, (ln >> 24) & 0xFF, (ln >> 16) & 0xFF, (ln >> 8) & 0xFF, ln & 0xFF]
    return bytes(header + th + track)


def slug_midi(path):
    return os.path.splitext(os.path.basename(path))[0] + '.mid'


def main(argv):
    os.makedirs(OUT, exist_ok=True)
    args = argv[1:]
    if args:
        files = [a if os.path.isabs(a) else os.path.join(BASE, a) for a in args]
    else:
        files = [
            os.path.join(BASE, f)
            for f in sorted(os.listdir(BASE))
            if f.endswith('.json') and f[0].isdigit()
        ]
    for path in files:
        with open(path, encoding='utf-8') as fh:
            score = json.load(fh)
        data = generar_midi(score)
        dest = os.path.join(OUT, slug_midi(path))
        with open(dest, 'wb') as fh:
            fh.write(data)
        print(f'{os.path.basename(dest)}: {len(data)} bytes  tempo={score.get("tempo")}')
    return 0


if __name__ == '__main__':
    sys.exit(main(sys.argv))
