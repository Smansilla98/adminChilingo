#!/usr/bin/env python3
"""DSL de grilla para escribir las partituras del Cuadernillo en el modelo v4.

Modelo v4: TPQ = 48 ticks por negra. Cada compás es una lista de notas por
instrumento (`voces`). Este DSL escribe cada compás como una grilla de
semicorcheas (16 por compás de 4/4) o de fusas (`grid=32`).

Tokens
------
  -    silencio de una unidad de grilla
  =    prolonga la nota anterior una unidad más (duración)
  x    golpe pleno          >  acentuado        c  chapa / aro
  t    tapado               r  presionado       o  abierto
  s    slap                 p  palma (rombo)    d  dedos
  a    agudo / borde        f  flam (mordente)
  n(...)  grupo irregular: n notas en el espacio de un tiempo (negra).
          `3(xxx)` tresillo de corcheas, `6(xxxxxx)` sextillo, `5(xxxxx)` etc.
  |    separador visual, se ignora
"""
import json
import os

TPQ = 48

STROKES = {
    'x': 'nota', '>': 'acentuado', 'c': 'chapa', 't': 'tapado', 'o': 'abierto',
    's': 'slap', 'p': 'palma', 'd': 'dedo', 'a': 'agudo', 'f': 'flam',
    'r': 'presionado',
}

# ticks -> (duración, puntillos)
TICKS_DUR = {
    6: ('32', 0), 9: ('32', 1), 12: ('16', 0), 18: ('16', 1), 21: ('16', 2),
    24: ('8', 0), 36: ('8', 1), 42: ('8', 2), 48: ('q', 0), 72: ('q', 1),
    84: ('q', 2), 96: ('h', 0), 144: ('h', 1), 168: ('h', 2), 192: ('w', 0),
}

_counter = [0]


def nid(prefix='n'):
    _counter[0] += 1
    return f'{prefix}{_counter[0]:04d}'


def nota(dur, dots=0, rest=False, stroke='nota', dyn=None, tuplet=None):
    return {'id': nid(), 'dur': dur, 'dots': dots, 'rest': rest,
            'stroke': 'nota' if rest else stroke, 'dyn': dyn, 'tuplet': tuplet}


def ticks(n):
    base = {'w': TPQ * 4, 'h': TPQ * 2, 'q': TPQ, '8': TPQ // 2,
            '16': TPQ // 4, '32': TPQ // 8}[n['dur']]
    t = base * {0: 1, 1: 1.5, 2: 1.75}[n['dots']]
    if n['tuplet']:
        t = t * n['tuplet']['den'] / n['tuplet']['num']
    return round(t)


def _nota_ticks(t, rest=False, stroke='nota', dyn=None):
    if t not in TICKS_DUR:
        raise ValueError(f'duración de {t} ticks no representable')
    dur, dots = TICKS_DUR[t]
    return nota(dur, dots, rest=rest, stroke=stroke, dyn=dyn)


def silencios(pos, largo, unidad):
    """Silencios alineados a la grilla, del más grande al más chico."""
    out = []
    resto = largo
    while resto > 0:
        for size in (16, 8, 4, 2, 1):
            t = size * unidad
            if size <= resto and (pos % size == 0) and t in TICKS_DUR:
                out.append(_nota_ticks(t, rest=True))
                pos += size
                resto -= size
                break
        else:
            out.append(_nota_ticks(unidad, rest=True))
            pos += 1
            resto -= 1
    return out


_RE_TUP = __import__('re').compile(r'(\d+)(?::(\d+))?\(([^)]*)\)')
_STD_TICKS = [6, 12, 24, 48, 96]


def tokenizar(pat):
    i, toks = 0, []
    while i < len(pat):
        ch = pat[i]
        if ch in ' |':
            i += 1
            continue
        if ch.isdigit():
            m = _RE_TUP.match(pat, i)
            if not m:
                raise ValueError(f'grupo irregular mal formado en {pat[i:]!r}')
            n = int(m.group(1))
            largo = int(m.group(2)) if m.group(2) else None
            toks.append(('tuplet', n, list(m.group(3)), largo))
            i = m.end()
            continue
        if ch == '-':
            largo = 1
            while i + largo < len(pat) and pat[i + largo] == '-':
                largo += 1
            toks.append(('rest', largo))
            i += largo
            continue
        if ch in STROKES:
            largo = 1
            while i + largo < len(pat) and pat[i + largo] == '=':
                largo += 1
            toks.append(('note', ch, largo))
            i += largo
            continue
        raise ValueError(f'token inválido {ch!r} en {pat!r}')
    return toks


def voz(pat, dyn=None, grid=16, num=4, den=4):
    """Traduce un patrón de grilla a lista de notas del modelo v4."""
    unidad = (TPQ * 4 // den) // (grid // num) if grid % num == 0 else None
    unidad = unidad or TPQ * 4 // grid
    capacidad = TPQ * 4 * num // den
    out, pos = [], 0
    for tok in tokenizar(pat):
        if tok[0] == 'rest':
            out += silencios(pos, tok[1], unidad)
            pos += tok[1]
        elif tok[0] == 'note':
            _, ch, largo = tok
            out.append(_nota_ticks(largo * unidad, stroke=STROKES[ch], dyn=dyn))
            dyn = None
            pos += largo
        else:
            _, n, chars, largo = tok
            tid = nid('t')
            # el grupo ocupa `largo` unidades de grilla (por defecto una negra)
            largo = largo if largo else grid // num
            total_t = largo * unidad
            q = total_t / n
            base_t = next(t for t in _STD_TICKS if t >= q)
            den = round(total_t / base_t)
            base = TICKS_DUR[base_t][0]
            for c in chars:
                out.append(nota(base, 0, rest=(c == '-'),
                                stroke=STROKES.get(c, 'nota'), dyn=dyn,
                                tuplet={'id': tid, 'num': n, 'den': den}))
                dyn = None
            pos += largo
    total = sum(ticks(x) for x in out)
    if total != capacidad:
        raise ValueError(f'compás desbalanceado ({total} de {capacidad} ticks) en {pat!r}')
    return out


def compas(voces, repeat_begin=False, repeat_end=False, ending=None, texto=None,
           dyn=None, grid=16, num=4, den=4):
    return {
        'id': nid('m'),
        'repeatBegin': repeat_begin,
        'repeatEnd': repeat_end,
        'ending': ending,
        'texto': texto,
        'voces': {inst: voz(pat, dyn=dyn, grid=grid, num=num, den=den)
                  for inst, pat in voces.items()},
    }


def seccion(nombre, measures, repeat_x=1):
    return {'id': nid('s'), 'name': nombre, 'repeatX': repeat_x, 'measures': measures}


def score(title, autor, tempo, instruments, sections, num=4, den=4):
    return {
        'version': 4,
        'title': title,
        'autor': autor,
        'tempo': tempo,
        'timeSignature': {'num': num, 'den': den},
        'instruments': [{'id': i, 'volume': 0.9, 'mute': False, 'solo': False,
                         'visible': True} for i in instruments],
        'sections': sections,
    }


INSTS = ['surdo_grave', 'surdo_agudo', 'surdo_medio', 'redoblante', 'repique', 'timbal']
SURDOS = ['surdo_grave', 'surdo_agudo', 'surdo_medio']
VACIO = '----------------'


def tutti(pat, insts=None):
    """Mismo patrón para varios instrumentos."""
    return {i: pat for i in (insts or INSTS)}


def dump(data, path):
    with open(path, 'w', encoding='utf-8') as fh:
        json.dump(data, fh, ensure_ascii=False, indent=2)
