"""Toque de Marcha — Cuadernillo (PDF pág. 35)."""
from dsl import INSTS, VACIO, compas, score, seccion

TITULO = 'Toque de Marcha'
MATCH = {'año': 1, 'orden': 7, 'nombre': 'Toque de Marcha'}

V = VACIO


def c(sg=V, sa=V, sm=V, re=V, rp=V, ti=V, **kw):
    return compas({'surdo_grave': sg, 'surdo_agudo': sa, 'surdo_medio': sm,
                   'redoblante': re, 'repique': rp, 'timbal': ti}, **kw)


# --------------------------------------- Llamada inicial, intermedia y final
def llamada():
    return [
        c(sg='------------xxx-', sa='------------xxx-', sm='------------xxx-',
          texto='Llamada inicial, intermedia y final', dyn='f'),
        c(sg='x----xxxx-------', sa='x----xxxx-------', sm='x----xxxx-------',
          re='-xxxx----xxxx---', rp='-xxxx----xxxx---', ti='-xxxx----xxxx---'),
    ]


# ------------------------------------------------------------------------ Toque
SU = 'x=t=t=x=x=t=-x=x'
TI = 'x=o=o=x=-o=x=x=-'
RP = '-xxx-x=--xxx-x=-'

toque = [
    c(sg=SU, sa=SU, sm=SU, re=SU, ti=TI, rp=RP,
      repeat_begin=True, repeat_end=True, texto='Toque', dyn='mf'),
]

# ------------------------------------------------ Variación (cada 4 vueltas)
variacion = [
    c(sg=SU, sa=SU, sm=SU, re=SU,
      ti='xx-xxx--x=x=----', rp='xx-xxx--x=-x=---',
      repeat_begin=True, texto='Variación (cada 4 vueltas) — timbal y repique'),
    c(sg=SU, sa=SU, sm=SU, re=SU,
      ti='-xxx-xx-o=-o=---', rp='-xxx-xxx-x=-x=--', repeat_end=True),
]

SCORE = score(TITULO, 'La Chilinga', 108, INSTS, [
    seccion('Llamada inicial', llamada(), 1),
    seccion('Toque', toque, 8),
    seccion('Variación', variacion, 1),
    seccion('Llamada final', llamada(), 1),
])
