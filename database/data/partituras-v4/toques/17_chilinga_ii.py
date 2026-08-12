"""Chilinga II — Cuadernillo (PDF pág. 36).

El toque está escrito con figuración densa (fusas y grupos de seis); la
transcripción usa grilla de fusas (grid=32) y aproxima los pasajes más densos.
"""
from dsl import INSTS, compas, score, seccion

TITULO = 'Chilinga II'
MATCH = {'año': 5, 'orden': 2, 'nombre': 'Chilinga II'}

V = '-' * 32


def c(sg=V, sa=V, sm=V, re=V, rp=V, ti=V, **kw):
    return compas({'surdo_grave': sg, 'surdo_agudo': sa, 'surdo_medio': sm,
                   'redoblante': re, 'repique': rp, 'timbal': ti},
                  grid=32, **kw)


SU = 'x-xxx-x-' * 4
SU2 = 'x-xx-xx-' * 4
RE = 'x>xx>>xx' * 4
RE_FILL = 'x>xx>>xx' * 3 + 'x>xx--x-'
TI = 'xxx-x=--' * 4
TI2 = 'xxx-x=--' * 2 + 'x=--x=--x=--x=--'
RP = 'x-xx-xx-' * 4
RP2 = 'x-xx-xx-' * 3 + 'xxxx-xx-'

toque = [
    c(sg=SU, sa=SU, sm=SU, re=RE, ti=TI, rp=RP,
      repeat_begin=True, ending=1, texto='Toque — repetición 1', dyn='mf'),
    c(sg=SU2, sa=SU2, sm=SU2, re=RE, ti=TI, rp=RP,
      ending=2, texto='Repetición 2'),
    c(sg=SU, sa=SU, sm=SU, re=RE, ti=TI2, rp=RP),
    c(sg=SU2, sa=SU2, sm=SU2, re=RE, ti=TI, rp=RP2),
    c(sg=SU, sa=SU, sm=SU, re=RE_FILL, ti=TI2, rp=RP2, repeat_end=True),
]

SCORE = score(TITULO, 'La Chilinga', 96, INSTS, [
    seccion('Toque', toque, 4),
])
