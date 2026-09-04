"""Toque a Oxosi — Cuadernillo págs. 55-57 (PDF págs. 58-60).

Transcripción literal donde se lee. Timbal/HI densos → revisar.
Sin inventar x-x / xx-x: preferir corcheas y grupos de 4 semis.
"""
from dsl import INSTS, VACIO, compas, score, seccion, unisono

TITULO = 'Toque a Oxosi'
MATCH = {'año': 4, 'orden': 6, 'nombre': 'Oxosi II'}
PDF_PAGES = [58, 59, 60]

V = VACIO


def c(sg=V, sa=V, sm=V, re=V, rp=V, ti=V, **kw):
    return compas({'surdo_grave': sg, 'surdo_agudo': sa, 'surdo_medio': sm,
                   'redoblante': re, 'repique': rp, 'timbal': ti}, **kw)


# --- TOQUE 1
T1_SG = 't=--x=------x=--'
T1_SA = 't=x=------------'
T1_SM = 't=x=------------'


def toque_1(texto='Toque 1'):
    return [
        c(sg=T1_SG, sa=T1_SA, sm=T1_SM,
          repeat_begin=True,
          texto=texto + ' — … revisar con la escuela', dyn='mf'),
        c(sg='--------x=--x=--', sa='x=--------------',
          sm='----------------', repeat_end=True),
    ]


RE_A = '>xxx>xxx>xxx>xxx'

sobre_toque_1_rr = [
    c(re='->xx>xxx>xxx>xxx', rp='->xx>xxx>xxx>xxx',
      repeat_begin=True,
      texto='Sobre Toque 1 — redo/repi — … revisar con la escuela',
      dyn='f'),
    c(re=RE_A, rp=RE_A),
    c(re=RE_A, rp=RE_A),
    c(re='>xxx>xxx>xxx>x--', rp='>xxx>xxx>xxx>x--', repeat_end=True),
]

sobre_toque_1_rr2 = [
    c(re='---->xxx>xxx>xxx', rp='---->xxx>xxx>xxx',
      repeat_begin=True,
      texto='Redoblante y repique (2da vuelta) — … revisar', dyn='f'),
    c(re='>xxx>xxx>xxx>x--', rp='>xxx>xxx>xxx>x--',
      repeat_end=True),
]

# Timbal: abiertos/notas en corcheas (sin x-x inventado)
timbal_1 = [
    c(ti='x=oo-x=oo-x=x=oo', repeat_begin=True,
      texto='Timbal 1 — … revisar con la escuela', dyn='mf'),
    c(ti='x=x=oo-x=oox=oo-'),
    c(ti='x=oo-x=oox=oo-x='),
    c(ti='x=oox=oo-x=x=---', repeat_end=True),
]

timbal_2 = [
    c(ti='x=x=x=x=--------', repeat_begin=True,
      texto='Timbal 2 — … revisar con la escuela', dyn='mf'),
    c(ti='--x=x=x=x=x=x=--'),
    c(ti='x=x=x=x=x=x=----'),
    c(ti='--x=x=--x=x=x=--', repeat_end=True),
]

timbal_3 = [
    c(ti='x=oo-x=oox=oo-x=', repeat_begin=True,
      texto='Timbal 3 — … revisar con la escuela', dyn='mf'),
    c(ti='x=oox=oo-x=oo-x='),
    c(ti='oo-x=oox=oo-x=oo'),
    c(ti='x=oo-x=oox=x=---', repeat_end=True),
]


def toque_2(texto='Toque 2'):
    return [
        c(sg='t=x=x=x=----x=--', sa='t=x=x=x=----x=--',
          sm='t=x=x=x=----x=--',
          ti='x=oo-x=oox=oo-x=',
          re='3(-xx)3(-xx)3(-xx)3(-xx)',
          rp='3(-xx)3(-xx)3(-xx)3(-xx)',
          repeat_begin=True,
          texto=texto + ' — … revisar con la escuela', dyn='mf'),
        c(sg='--x=x=x=x=x=x=--', sa='--x=x=x=x=x=x=--',
          sm='--x=x=x=x=x=x=--',
          ti='x=oox=oo-x=oo-x=',
          re='3(-xx)3(xxx)3(-xx)3(xxx)',
          rp='3(-xx)3(xxx)3(-xx)3(xxx)',
          repeat_end=True),
    ]


final = [
    c(sg='t=x=--x=x=x=x=--', sa='t=x=--x=x=x=x=--',
      sm='t=x=--x=x=x=x=--',
      re='3(xxx)--x-3(xxx)--x-',
      rp='3(xxx)--x-3(xxx)--x-',
      ti='x=--x=x=x=x=x=--',
      texto='Final — … revisar con la escuela', dyn='f'),
    compas(unisono('--x=x=x=--x=x=--')),
]

SCORE = score(TITULO, 'La Chilinga', 84, INSTS, [
    seccion('Toque 1', toque_1(), 4),
    seccion('Sobre Toque 1 — redoblante y repique (1ra y 3ra)',
            sobre_toque_1_rr, 2),
    seccion('Redoblante y repique (2da vuelta)', sobre_toque_1_rr2, 1),
    seccion('Timbal 1 (1ra vuelta)', timbal_1, 1),
    seccion('Timbal 2 (2da vuelta)', timbal_2, 1),
    seccion('Timbal 3 (3ra vuelta)', timbal_3, 1),
    seccion('Toque 2', toque_2(), 4),
    seccion('Final', final, 1),
])
