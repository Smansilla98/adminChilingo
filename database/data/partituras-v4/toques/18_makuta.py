"""Makuta — Cuadernillo (PDF pág. 37). Lleva campana (agogo)."""
from dsl import INSTS, VACIO, compas, score, seccion

TITULO = 'Makuta'
MATCH = {'año': 4, 'orden': 9, 'nombre': 'Ritmo de Makuta (Cuba)'}

V = VACIO
INST = INSTS + ['agogo']


def c(sg=V, sa=V, sm=V, re=V, rp=V, ti=V, ag=V, **kw):
    return compas({'surdo_grave': sg, 'surdo_agudo': sa, 'surdo_medio': sm,
                   'redoblante': re, 'repique': rp, 'timbal': ti,
                   'agogo': ag}, **kw)


# ------------------------------------------------- Llamada principio y final
def llamada():
    SU1 = 'x---x---x==xxxx-'
    SU2 = '-xx-----xxxxxxx-'
    RTP = '--xx----xx------'
    return [
        c(sg=SU1, sa=SU1, sm=SU1, texto='Llamada principio y final', dyn='f'),
        c(sg=SU2, sa=SU2, sm=SU2, re=RTP, rp=RTP, ti=RTP),
        c(sg='x---------------', sa='x---------------', sm='x---------------',
          re='--x=------------', rp='--x=------------', ti='--x=------------',
          texto='(compás de 1/4 completado con silencios)'),
    ]


# ------------------------------------------------------------------------ Toque
SU = '---x----x=x=x---'
SM = '------x-----xxx-'
RE = '>xxx>xxx>xxx>xxx'
TI = 'o=====x=o===x=xx'
RP = 'xx--xx--xx--xx--'
AG = 'xx-t-t-xx-t-t---'

toque = [
    c(sg=SU, sa=SU, sm=SM, re=RE, rp=RP, ti=TI, ag=AG,
      repeat_begin=True, repeat_end=True, texto='Toque', dyn='mf'),
]

SCORE = score(TITULO, 'La Chilinga', 104, INST, [
    seccion('Llamada de principio', llamada(), 1),
    seccion('Toque', toque, 8),
    seccion('Llamada final', llamada(), 1),
])
