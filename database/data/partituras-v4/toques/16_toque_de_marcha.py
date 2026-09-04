"""Toque de Marcha — Cuadernillo pág. 32 (PDF pág. 35).

Transcripción literal del PDF (figuras = escritura de la escuela).
"""
from dsl import INSTS, VACIO, compas, score, seccion

TITULO = 'Toque de Marcha'
MATCH = {'año': 1, 'orden': 7, 'nombre': 'Toque de Marcha'}
PDF_PAGES = [35]

V = VACIO


def c(sg=V, sa=V, sm=V, re=V, rp=V, ti=V, **kw):
    return compas({'surdo_grave': sg, 'surdo_agudo': sa, 'surdo_medio': sm,
                   'redoblante': re, 'repique': rp, 'timbal': ti}, **kw)


# --- LLAMADA
# Surdos m1: sil. blanca · 4 semis · sil. negra
# Surdos m2: negra · 4 semis · sil. blanca
# HI m2: (sil.semi + 3 semis + negra) × 2
LL_SU_1 = '--------xxxx----'
LL_SU_2 = 'x===xxxx--------'
LL_HI_2 = '-xxxx===-xxxx==='


def llamada(texto='Llamada inicial, intermedia y final'):
    return [
        c(sg=LL_SU_1, sa=LL_SU_1, sm=LL_SU_1,
          texto=texto, dyn='f'),
        c(sg=LL_SU_2, sa=LL_SU_2, sm=LL_SU_2,
          re=LL_HI_2, rp=LL_HI_2, ti=LL_HI_2),
    ]


# --- TOQUE
# Surdos/Redo: semis; X = tapado (t). Beat 3 del PDF lleva silencio en 3ª semi.
# Timbal: nota + 3 palmas · 2 corcheas × 2  (diamante = palma)
# Repique: sil. + 3 semis · corchea · … (lectura probable)
SU = 'xttxxttxxx-x-xxx'
TI = 'xpppx=x=xpppx=x='
RP = '-xxx-x=--xxx-x=-'

toque = [
    c(sg=SU, sa=SU, sm=SU, re=SU, ti=TI, rp=RP,
      repeat_begin=True, repeat_end=True, texto='Toque', dyn='mf'),
]

# --- VARIACIÓN (cada 4 vueltas) — densidades HI → revisar
variacion = [
    c(sg=SU, sa=SU, sm=SU, re=SU,
      ti='xxxx----x=x=----', rp='xxxx----x=-x=---',
      repeat_begin=True,
      texto='Variación (cada 4 vueltas) — … revisar con la escuela'),
    c(sg=SU, sa=SU, sm=SU, re=SU,
      ti='-xxx-xx-o=-o=---', rp='-xxx-xxx-x=-x=--', repeat_end=True),
]

SCORE = score(TITULO, 'La Chilinga', 90, INSTS, [
    seccion('Llamada inicial', llamada(), 1),
    seccion('Toque', toque, 8),
    seccion('Variación', variacion, 1),
    seccion('Llamada final', llamada('Llamada final'), 1),
])
