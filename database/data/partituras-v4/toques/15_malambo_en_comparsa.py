"""Malambo en Comparsa — Cuadernillo pág. 31 (PDF pág. 34). Compás de 6/8.

Transcripción literal del PDF (figuras = escritura de la escuela).
Grilla de 12 (2 celdas = corchea en 6/8).
"""
from dsl import INSTS, compas, score, seccion

TITULO = 'Malambo en Comparsa'
MATCH = {'año': 2, 'orden': 9, 'nombre': 'Malambo en Comparsa'}
PDF_PAGES = [34]

V = '------------'


def c(sg=V, sa=V, sm=V, re=V, rp=V, ti=V, **kw):
    return compas({'surdo_grave': sg, 'surdo_agudo': sa, 'surdo_medio': sm,
                   'redoblante': re, 'repique': rp, 'timbal': ti},
                  grid=12, num=6, den=8, **kw)


# --- LLAMADA
# Repique: sil.corchea · corchea · sil.corchea · 3 corcheas  → --x=--x=x=x=
# m4 silencio · m5: (sil.corchea · corchea) × 3
# Surdos: m2–m3 grave negra c/punto; m4 frase por altura; m5 medio
LL_RP = ['--x=--x=x=x=', '--x=--x=x=x=', '--x=--x=x=x=', V, '--x=--x=--x=']
LL_SG = [V, 'x=====------', 'x=====------', 'x=--x=------', V]
LL_SM = [V, V, V, '----x=--x=--', 'x=====------']
LL_SA = [V, V, V, '------x=----', V]


def llamada(texto='Llamada inicial, intermedia y final'):
    return [
        c(rp=LL_RP[i], sg=LL_SG[i], sa=LL_SA[i], sm=LL_SM[i],
          texto=texto if i == 0 else None,
          dyn='f' if i == 0 else None)
        for i in range(5)
    ]


# --- TOQUE
SU = 't=-xt=x=x=--'
SU2 = 't=-xt=x=x-x-'
RE = '>xx>xx>xx>xx'
TI = 'xxx-xxx-xx--'

toque = [
    c(sg=SU, sa=SU, sm=SU, re=RE, rp='------x=====', ti=TI,
      repeat_begin=True,
      texto='Toque — … revisar con la escuela (timbal/repique)', dyn='mf'),
    c(sg=SU2, sa=SU2, sm=SU2, re=RE, rp='--x---x=====', ti=TI,
      repeat_end=True),
]

SCORE = score(TITULO, 'La Chilinga', 90, INSTS, [
    seccion('Llamada inicial', llamada(), 1),
    seccion('Toque', toque, 8),
    seccion('Llamada final', llamada('Llamada final'), 1),
], num=6, den=8)
