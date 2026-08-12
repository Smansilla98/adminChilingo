#!/usr/bin/env python3
"""Genera las partituras de ejemplo v4 (database/data/partituras-v4/*.json).

DSL: cada compás 4/4 es una grilla de 16 semicorcheas.
  -   silencio
  =   prolonga la nota anterior una semicorchea
  x   golpe pleno       >   acentuado      c   chapa/aro     t   tapado
  o   abierto           s   slap           p   palma         d   dedos
  a   agudo (borde)     f   flam
  6(oooooo)  tresillo/sextillo: n notas en el espacio de un tiempo (4 semicorcheas)
"""
import json
import hashlib
import os

TPQ = 48
SIXTEENTH = TPQ // 4  # 12 ticks

STROKES = {
    'x': 'nota', '>': 'acentuado', 'c': 'chapa', 't': 'tapado', 'o': 'abierto',
    's': 'slap', 'p': 'palma', 'd': 'dedo', 'a': 'agudo', 'f': 'flam', 'r': 'presionado',
}

LEN_DUR = {1: ('16', 0), 2: ('8', 0), 3: ('8', 1), 4: ('q', 0), 6: ('q', 1),
           8: ('h', 0), 12: ('h', 1), 16: ('w', 0)}

_counter = [0]


def nid(prefix='n'):
    _counter[0] += 1
    return f"{prefix}{_counter[0]:04d}"


def nota(dur, dots=0, rest=False, stroke='nota', dyn=None, tuplet=None):
    return {'id': nid(), 'dur': dur, 'dots': dots, 'rest': rest,
            'stroke': 'nota' if rest else stroke, 'dyn': dyn, 'tuplet': tuplet}


def silencios(start, length):
    """Silencios alineados a la grilla."""
    out = []
    pos, resto = start, length
    for size in (16, 8, 4, 2, 1):
        pass
    while resto > 0:
        for size in (16, 8, 4, 2, 1):
            if size <= resto and pos % size == 0:
                dur, dots = LEN_DUR[size]
                out.append(nota(dur, dots, rest=True))
                pos += size
                resto -= size
                break
        else:
            out.append(nota('16', 0, rest=True))
            pos += 1
            resto -= 1
    return out


def tokenizar(pat):
    """Devuelve lista de tokens: ('note', char, len16) | ('rest', len16) | ('tuplet', n, [chars])"""
    i, toks = 0, []
    while i < len(pat):
        ch = pat[i]
        if ch == ' ':
            i += 1
            continue
        if ch.isdigit() and i + 1 < len(pat) and pat[i + 1] == '(':
            n = int(ch)
            j = pat.index(')', i)
            toks.append(('tuplet', n, list(pat[i + 2:j])))
            i = j + 1
            continue
        if ch == '-':
            length = 1
            while i + length < len(pat) and pat[i + length] == '-':
                length += 1
            toks.append(('rest', length))
            i += length
            continue
        if ch in STROKES:
            length = 1
            while i + length < len(pat) and pat[i + length] == '=':
                length += 1
            toks.append(('note', ch, length))
            i += length
            continue
        raise ValueError(f'token inválido {ch!r} en {pat!r}')
    return toks


def voz(pat, dyn=None):
    out, pos = [], 0
    for tok in tokenizar(pat):
        if tok[0] == 'rest':
            out += silencios(pos, tok[1])
            pos += tok[1]
        elif tok[0] == 'note':
            _, ch, length = tok
            if length not in LEN_DUR:
                raise ValueError(f'duración {length} no soportada en {pat!r}')
            dur, dots = LEN_DUR[length]
            out.append(nota(dur, dots, stroke=STROKES[ch], dyn=dyn))
            dyn = None
            pos += length
        else:
            _, n, chars = tok
            tid = nid('t')
            for c in chars:
                out.append(nota('16', 0, rest=(c == '-'), stroke=STROKES.get(c, 'nota'),
                                dyn=dyn, tuplet={'id': tid, 'num': n, 'den': 4}))
                dyn = None
            pos += 4
    total = sum(largo(n) for n in out)
    if total != 4 * TPQ:
        raise ValueError(f'compás desbalanceado ({total} ticks) en {pat!r}')
    return out


def largo(n):
    base = {'w': TPQ * 4, 'h': TPQ * 2, 'q': TPQ, '8': TPQ // 2, '16': 12, '32': 6}[n['dur']]
    t = base * (1.5 if n['dots'] == 1 else 1.75 if n['dots'] == 2 else 1)
    if n['tuplet']:
        t = t * n['tuplet']['den'] / n['tuplet']['num']
    return round(t)


def compas(voces, repeat_begin=False, repeat_end=False, ending=None, texto=None, dyn=None):
    return {
        'id': nid('m'),
        'repeatBegin': repeat_begin,
        'repeatEnd': repeat_end,
        'ending': ending,
        'texto': texto,
        'voces': {inst: voz(pat, dyn=dyn) for inst, pat in voces.items()},
    }


def seccion(nombre, measures, repeat_x=1):
    return {'id': nid('s'), 'name': nombre, 'repeatX': repeat_x, 'measures': measures}


def score(title, autor, tempo, instruments, sections):
    return {
        'version': 4,
        'title': title,
        'autor': autor,
        'tempo': tempo,
        'timeSignature': {'num': 4, 'den': 4},
        'instruments': [{'id': i, 'volume': 0.9, 'mute': False, 'solo': False, 'visible': True}
                        for i in instruments],
        'sections': sections,
    }


INSTS = ['surdo_grave', 'surdo_agudo', 'surdo_medio', 'redoblante', 'repique', 'timbal']

VACIO = '----------------'

# ---------------------------------------------------------------- Toque de Chilinga
# Cuadernillo pág. 3 (PDF pág. 6).
LLAMADA_TUTTI_A = '>=x=x=>=x=x=>=x='   # 8 corcheas acentuadas cada dos
LLAMADA_TUTTI_B = 'x===--x=x=x=----'

chilinga_llamada = [
    compas({i: LLAMADA_TUTTI_A for i in INSTS}, repeat_begin=True, texto='Llamada inicial y final', dyn='f'),
    compas({i: LLAMADA_TUTTI_B for i in INSTS}, repeat_end=True),
]

TOQUE_SG = 'x===----x===----'                    # negras en 1 y 3
TOQUE_SA = '----x===----x==='                    # negras en 2 y 4
TOQUE_SM = 'x=x x x===x=x=x==='.replace(' ', '')  # 1, 3, 4, 5(negra), 9, 11, 13
TOQUE_RE = '>xx>>xx>>xx>>xx>'                    # semicorcheas con acentos 1,4,5,8,9,12,13,16
TOQUE_TI = '--oo--ss--oo--ss'                    # 2 semis por tiempo, abierto/slap
TOQUE_RP = '>xx>>xx>>xx>>xx>'

chilinga_toque = [
    compas({'surdo_grave': TOQUE_SG, 'surdo_agudo': TOQUE_SA, 'surdo_medio': TOQUE_SM,
            'redoblante': TOQUE_RE, 'repique': TOQUE_RP, 'timbal': TOQUE_TI},
           repeat_begin=True, texto='Toque (base)', dyn='mf'),
    compas({'surdo_grave': TOQUE_SG, 'surdo_agudo': TOQUE_SA, 'surdo_medio': TOQUE_SM,
            'redoblante': TOQUE_RE, 'repique': TOQUE_RP, 'timbal': TOQUE_TI},
           repeat_end=True),
]

INTER_RE = 'x=x=xxxx x=x=xxxx'.replace(' ', '')
INTER_SU = '--x=--x=--x=x=--'

chilinga_inter = [
    compas({'surdo_grave': INTER_SU, 'surdo_agudo': INTER_SU, 'surdo_medio': INTER_SU,
            'redoblante': INTER_RE, 'repique': INTER_RE, 'timbal': VACIO},
           repeat_begin=True, texto='Llamada intermedia (x4)', dyn='f'),
    compas({'surdo_grave': 'x===----x===----', 'surdo_agudo': 'x===----x===----',
            'surdo_medio': 'x===----x===----', 'redoblante': '>=x=x=x=--------',
            'repique': '>=x=x=x=--------', 'timbal': VACIO},
           repeat_end=True),
]

toque_chilinga = score('Toque de Chilinga', 'La Chilinga', 100, INSTS, [
    seccion('Llamada inicial y final', chilinga_llamada, 1),
    seccion('Toque', chilinga_toque, 4),
    seccion('Llamada intermedia', chilinga_inter, 4),
])

# ---------------------------------------------------------------- Marcha Camión
# Cuadernillo págs. 4-6 (PDF págs. 7-9). Base 1 y Base 2 de la pág. 5.
MC_LLAM_SU = 'x=x=x=x=--x=x=x='
MC_LLAM_RE = '--------x=x=x=x='

marcha_llamada = [
    compas({'surdo_grave': MC_LLAM_SU, 'surdo_agudo': MC_LLAM_SU, 'surdo_medio': MC_LLAM_SU,
            'redoblante': MC_LLAM_RE, 'repique': MC_LLAM_RE, 'timbal': MC_LLAM_RE},
           repeat_begin=True, repeat_end=True, texto='Llamada', dyn='f'),
]

MC_SUR_1 = 'x==x----x=x=----'   # corchea con puntillo + semi, silencio, dos corcheas, silencio
MC_SUR_2 = 'x==x----x=x=x==='
MC_RED = '>xxx>xxx>xxx>xxx'
MC_TIM = '--oo--ss--oo--ss'
MC_REP = '--x=--x=--x=--x='

base1 = [
    compas({'surdo_grave': MC_SUR_1, 'surdo_agudo': MC_SUR_1, 'surdo_medio': MC_SUR_1,
            'redoblante': MC_RED, 'repique': MC_REP, 'timbal': MC_TIM},
           repeat_begin=True, texto='Base 1', dyn='mf'),
    compas({'surdo_grave': MC_SUR_2, 'surdo_agudo': MC_SUR_2, 'surdo_medio': MC_SUR_2,
            'redoblante': MC_RED, 'repique': MC_REP, 'timbal': MC_TIM},
           repeat_end=True),
]

base2 = [
    compas({'surdo_grave': MC_SUR_1, 'surdo_agudo': MC_SUR_1, 'surdo_medio': MC_SUR_1,
            'redoblante': MC_RED, 'repique': '--x=--x=--x=6(xxxxxx)', 'timbal': MC_TIM},
           repeat_begin=True, texto='Base 2 (con sextillo)', dyn='mf'),
    compas({'surdo_grave': MC_SUR_2, 'surdo_agudo': MC_SUR_2, 'surdo_medio': MC_SUR_2,
            'redoblante': MC_RED, 'repique': MC_REP, 'timbal': '6(oooooo)--ss--oo--ss'},
           repeat_end=True),
]

marcha_camion = score('Marcha Camión', 'La Chilinga', 96, INSTS, [
    seccion('Llamada', marcha_llamada, 1),
    seccion('Base 1', base1, 4),
    seccion('Base 2', base2, 4),
])

# ---------------------------------------------------------------- escritura
DEST = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'adminChilingo',
                    'database', 'data', 'partituras-v4')
os.makedirs(DEST, exist_ok=True)

ARCHIVOS = {
    '01-toque-de-chilinga.json': (toque_chilinga, {'año': 1, 'orden': 1, 'nombre': 'Ritmo Chilinga'}),
    '02-marcha-camion.json': (marcha_camion, {'año': 1, 'orden': 3, 'nombre': 'Marcha Camión'}),
}

manifest = []
for fname, (data, match) in ARCHIVOS.items():
    with open(os.path.join(DEST, fname), 'w', encoding='utf-8') as fh:
        json.dump(data, fh, ensure_ascii=False, indent=2)
    manifest.append({'file': fname, 'title': data['title'], 'match': match})
    compases = sum(len(s['measures']) for s in data['sections'])
    print(f"{fname}: {len(data['sections'])} secciones, {compases} compases OK")

with open(os.path.join(DEST, 'manifest.json'), 'w', encoding='utf-8') as fh:
    json.dump(manifest, fh, ensure_ascii=False, indent=2)
print('manifest.json OK')
