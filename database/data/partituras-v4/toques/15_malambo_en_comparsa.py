"""Malambo en Comparsa — Cuadernillo (PDF pág. 34). Compás de 6/8."""
from dsl import INSTS, compas, score, seccion

TITULO = 'Malambo en Comparsa'
MATCH = {'año': 2, 'orden': 9, 'nombre': 'Malambo en Comparsa'}

V = '------------'          # 6/8 → grilla de 12 semicorcheas


def c(sg=V, sa=V, sm=V, re=V, rp=V, ti=V, **kw):
    return compas({'surdo_grave': sg, 'surdo_agudo': sa, 'surdo_medio': sm,
                   'redoblante': re, 'repique': rp, 'timbal': ti},
                  grid=12, num=6, den=8, **kw)


# --------------------------------------- Llamada inicial, intermedia y final
LL_RP = ['--x===x=x=x=', '--x===x=x=x=', '--x===x=x=x=', V, '--x===x=x-x-']
LL_SU = [V, 'x=====------', 'x=====------', 'x-x-x=------', 'x=====------']

def llamada():
    return [
        c(rp=LL_RP[i], sg=LL_SU[i], sa=LL_SU[i], sm=LL_SU[i],
          texto='Llamada inicial, intermedia y final' if i == 0 else None,
          dyn='f' if i == 0 else None)
        for i in range(5)
    ]

# ------------------------------------------------------------------------ Toque
SU = 't=-xt=x=x=--'
SU2 = 't=-xt=x=x-x-'
RE = '>xx>xx>xx>xx'
TI = 'xxx-xxx-xx--'
RP = '------x====='

toque = [
    c(sg=SU, sa=SU, sm=SU, re=RE, rp=RP, ti=TI,
      repeat_begin=True, texto='Toque', dyn='mf'),
    c(sg=SU2, sa=SU2, sm=SU2, re=RE, rp='--x---x=====', ti=TI,
      repeat_end=True),
]

SCORE = score(TITULO, 'La Chilinga', 120, INSTS, [
    seccion('Llamada inicial', llamada(), 1),
    seccion('Toque', toque, 8),
    seccion('Llamada final', llamada(), 1),
], num=6, den=8)
