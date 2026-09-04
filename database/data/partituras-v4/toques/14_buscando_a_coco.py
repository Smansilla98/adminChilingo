"""Buscando a Coco — Cuadernillo págs. 28-30 (PDF págs. 31-33).

Transcripción literal del PDF (figuras = escritura de la escuela).
Sin inventar xx-x: semis de a 4, corcheas, negras y silencios del cuadernillo.
"""
from dsl import INSTS, VACIO, compas, score, seccion

TITULO = 'Buscando a Coco'
MATCH = {'año': 3, 'orden': 1, 'nombre': 'Buscando a Coco'}
PDF_PAGES = [31, 32, 33]

V = VACIO


def c(sg=V, sa=V, sm=V, re=V, rp=V, ti=V, **kw):
    return compas({'surdo_grave': sg, 'surdo_agudo': sa, 'surdo_medio': sm,
                   'redoblante': re, 'repique': rp, 'timbal': ti}, **kw)


# --- LLAMADA
# Director m1: 4 semis × 3 · sil. negra
# Surdos m2: sil. blanca · sil. negra · negra (beat 4)
# HI densos m2–m3 → revisar
LL_DIR_1 = 'xxxxxxxxxxxx----'
LL_SU_2 = '------------x==='

llamada = [
    c(ti=LL_DIR_1, texto='Llamada (director / timbaletas)', dyn='f'),
    c(ti=VACIO, sg=LL_SU_2, sa=LL_SU_2, sm=LL_SU_2,
      texto='Llamada — … revisar con la escuela (HI densos)'),
    c(ti=VACIO, rp=VACIO,
      texto='Llamada — … revisar con la escuela (repique / director)'),
    c(),
]

# --- BASE 1 (x4)
# Agudo: negras 1 y 3 · Grave: sil. · negra 2 · sil. · sil.corchea + corchea
# Medio: sil.negra · sil.corchea + corchea · sil.negra · corchea c/punto + semi
# Redo: 16 semis, acento en 2ª de cada tiempo (+ última)
# Timbal: corchea c/punto + semi · sil.corchea + 2 abiertos · … (HI m2)
# Repique denso → revisar
RE = 'x>xxx>xxx>xxx>x>'
TI1 = 'x==x--oo--xx--ox'
TI2 = '--xo--xo--xx--oo'

base_1 = [
    c(sa='x===----x===----', sg='----x===------x=',
      sm='------x=----x==x',
      re=RE, rp=VACIO, ti=TI1, repeat_begin=True,
      texto='Base 1 (x4) — … revisar con la escuela (repique)', dyn='mf'),
    c(re=RE, rp=VACIO, ti=TI2, repeat_end=True),
]

# --- BASE 2 / 3 / 4 y CORTE: densidades del escaneo → revisar figuras HI
base_2 = [
    c(sa='x===----x===----', sg='----x===------x=',
      sm='---x----x==x----',
      re=RE, ti=TI1, repeat_begin=True,
      texto='Base 2 (x4) — … revisar con la escuela', dyn='mf'),
    c(sa='-x--------------', sm='--x-x=x=--x=x---',
      re=RE, ti=TI2, repeat_end=True),
]

base_3 = [
    c(sa='x===----x===----', sg='----x===------x=',
      sm='---x----x==x----',
      re=RE, ti=TI1, repeat_begin=True,
      texto='Base 3 (x4) — … revisar con la escuela', dyn='mf'),
    c(sg='------x-----x---', sm='-----x=---x=x---',
      re=RE, ti=TI2, repeat_end=True),
]

corte = [
    c(ti='6(xxxxxx)6(xxxxxx)x===----',
      texto='Corte (timbal) — … revisar con la escuela (sextillos)',
      dyn='f'),
    c(sg='--------x---x=x=', sa='--------x---x=x=', sm='--------x---x=x='),
]

base_4 = [
    c(sa='o===============', sg='----x===------x=', sm='---x----x==x----',
      re=RE, ti=TI1, repeat_begin=True,
      texto='Base 4 (x4) — dim. f → p — … revisar con la escuela', dyn='f'),
    c(sa='o===============', sg='------x-------x-', sm='----x=--x---x==x',
      re=RE, ti=TI2, repeat_end=True, dyn='p'),
]

TRES = '3(xxx)3(xxx)3(xxx)3(xxx)'

coda = [
    c(ti=TRES, sg='x=-x----t=------', sa='x=-x----t=------',
      sm='x=-x----t=------', rp='----x---t=------',
      texto='Coda — … revisar con la escuela (tresillos)', dyn='f'),
    c(ti=TRES),
]

SCORE = score(TITULO, 'La Chilinga', 86, INSTS, [
    seccion('Llamada', llamada, 1),
    seccion('Base 1', base_1, 4),
    seccion('Base 2', base_2, 4),
    seccion('Base 3', base_3, 4),
    seccion('Corte', corte, 1),
    seccion('Base 4', base_4, 4),
    seccion('Coda', coda, 1),
])
