"""Malamakuá — Cuadernillo págs. 47-48 (PDF págs. 50-51). Compás de 6/8.

Transcripción literal del PDF. Grilla de 12 (2 celdas = corchea).
Sin inventar x-x alternado: preferir corcheas x= / semis de a grupo.
"""
from dsl import INSTS, compas, score, seccion, unisono

TITULO = 'Malamakuá'
MATCH = {'año': 3, 'orden': 3, 'nombre': 'Malamakua I'}
PDF_PAGES = [50, 51]

V = '------------'


def c(sg=V, sa=V, sm=V, re=V, rp=V, ti=V, **kw):
    return compas({'surdo_grave': sg, 'surdo_agudo': sa, 'surdo_medio': sm,
                   'redoblante': re, 'repique': rp, 'timbal': ti},
                  grid=12, num=6, den=8, **kw)


# ------------------------------------------------------------- Introducción
INTRO_S = 'x=--------x='

introduccion = [
    c(rp='x=--x=--x=x=', sg='x=====----x=', sa='x=====----x=',
      sm='x=====----x=',
      texto='Introducción — 6/8 — … revisar con la escuela', dyn='mf'),
    c(rp='-x--x=--x=x=', sg='--x=--------', sa='--x=--------',
      sm='--x=--------'),
    c(rp='x=--x=--x=x=', sg='x=====----x=', sa='x=====----x=',
      sm='x=====----x='),
    c(rp='-x--x=x=x=--', sg='x=x=----x=--', sa='x=x=----x=--',
      sm='x=x=----x=--'),
]

# ------------------------------------------------------------------- Toque
T_S1 = 'x=x=t=x=t=--'
T_S2 = 'x=t=x=t=x=--'
T_RE = '>x=>x=>x=>x='
T_TI = 'x=-x=--x=x=-'
T_RP = 'x=x=x=x=x=x='   # 6 corcheas (no x-x inventado)


def toque(texto='Toque'):
    return [
        c(sg=T_S1, sa=T_S1, sm=T_S1, re=T_RE, ti=T_TI, rp=T_RP,
          repeat_begin=True,
          texto=texto + ' — … revisar con la escuela (HI)', dyn='mf'),
        c(sg=T_S2, sa=T_S2, sm=T_S2, re='>x=>x=>x=>xx',
          ti='x=--x=-x=x=-', rp=T_RP, repeat_end=True),
    ]


# --------------------------------------------------------------- Variación
variacion = [
    c(sg='xxxxxx------', sa='xxxxxx------', sm='xxxxxx------',
      re='>>x>>x>>x>>x', ti='x=x=x=x=x=x=', rp='x=x=x=x=x=x=',
      repeat_begin=True,
      texto='Variación — … revisar con la escuela', dyn='mf'),
    c(sg='xxxxxx-x=---', sa='xxxxxx-x=---', sm='xxxxxx-x=---',
      re='>>x>>x>>xxxx', ti='x=x=x=x=x=--', rp='x=x=x=xxx-x=',
      repeat_end=True),
]

# ------------------------------------------------------------ Llamada final
llamada_final = [
    compas(unisono('-x=-x=-x=x=-'), grid=12, num=6, den=8,
           texto='Llamada final — todos — … revisar con la escuela', dyn='f'),
    compas(unisono('x=--x=x=x=--'), grid=12, num=6, den=8),
]

SCORE = score(TITULO, 'La Chilinga', 88, INSTS, [
    seccion('Introducción', introduccion, 1),
    seccion('Toque', toque(), 4),
    seccion('Variación', variacion, 2),
    seccion('Toque (vuelve)', toque('Toque (vuelve)'), 2),
    seccion('Llamada final', llamada_final, 1),
], num=6, den=8)
