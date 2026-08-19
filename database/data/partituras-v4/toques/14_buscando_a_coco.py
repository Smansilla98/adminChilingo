"""Buscando a Coco — Cuadernillo (PDF pág. 31-33)."""
from dsl import INSTS, VACIO, compas, score, seccion

TITULO = 'Buscando a Coco'
MATCH = {'año': 3, 'orden': 1, 'nombre': 'Buscando a Coco'}

V = VACIO


def c(sg=V, sa=V, sm=V, re=V, rp=V, ti=V, **kw):
    return compas({'surdo_grave': sg, 'surdo_agudo': sa, 'surdo_medio': sm,
                   'redoblante': re, 'repique': rp, 'timbal': ti}, **kw)


RE = 'x>xxx>xxx>xxx>xx'          # redoblante: 16avos con acento en el 2do
RP = '>>xxx>xx>>xxx>xx'          # repique
RP2 = '--------x>xxx>xx'
TI = 'x==-x=-oo=-x=oo-'          # timbal
TI2 = 'x=oo-x=-oo=-x=o-'

# ---------------------------------------------------------------------- Llamada
llamada = [
    c(ti='xxx-xxx-xxx-xx--', texto='Llamada (director / timbaletas)', dyn='f'),
    c(ti='xxx-xxx-x---x---', sg='--------------x-', sa='--------------x-',
      sm='--------------x-'),
    c(ti='xxx-x=x=x=-x-xx-', rp='xxx-x=x=x=-x-xx-'),
    c(),
]

# ------------------------------------------------------------------- Base 1 (x4)
base_1 = [
    c(sa='----x-----------', sg='----x---------x-', sm='----x-------x==x',
      re=RE, rp=RP, ti=TI, repeat_begin=True, texto='Base 1 (x4)', dyn='mf'),
    c(re=RE, rp=RP2, ti=TI2, repeat_end=True),
]

# ------------------------------------------------------------------- Base 2 (x4)
base_2 = [
    c(sa='----x-----------', sg='----x---------x-', sm='---x----x==x----',
      re=RE, rp=RP, ti=TI, repeat_begin=True, texto='Base 2 (x4)', dyn='mf'),
    c(sa='-x--------------', sm='--x-x=x=--x=x---',
      re=RE, rp=RP2, ti=TI2, repeat_end=True),
]

# ------------------------------------------------------------------- Base 3 (x4)
base_3 = [
    c(sa='----x-----------', sg='----x---------x-', sm='---x----x==x----',
      re=RE, rp=RP, ti=TI, repeat_begin=True, texto='Base 3 (x4)', dyn='mf'),
    c(sg='------x-----x---', sm='-----x=---x=x---',
      re=RE, rp=RP2, ti=TI2, repeat_end=True),
]

# ------------------------------------------------------------------------- Corte
corte = [
    c(ti='6(oxxxxx)6(oxxxxx)x===----', texto='Corte (timbal, sextillos)',
      dyn='f'),
    c(sg='--------x---x=x=', sa='--------x---x=x=', sm='--------x---x=x='),
]

# ------------------------------------------------------ Base 4 (x4), dim. f → p
base_4 = [
    c(sa='o===============', sg='----x---------x-', sm='---x----x==x----',
      re=RE, rp=RP, ti=TI, repeat_begin=True,
      texto='Base 4 (x4) — dim. f → p', dyn='f'),
    c(sa='o===============', sg='------x-------x-', sm='----x=--x---x==x',
      re=RE, rp=RP2, ti=TI2, repeat_end=True, dyn='p'),
]

# ------------------------------------------------ Coda sobre timbal y repique
TRES = '3(xxx)3(xxx)3(xxx)3(xxx)'

coda = [
    c(ti=TRES, sg='x=-x----t=------', sa='x=-x----t=------',
      sm='x=-x----t=------', rp='----x---t=------',
      texto='Coda sobre timbal y repique', dyn='f'),
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
