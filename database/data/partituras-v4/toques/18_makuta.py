"""Makuta — Cuadernillo pág. 34 (PDF pág. 37). Lleva campana (agogo).

Transcripción literal del PDF (figuras = escritura de la escuela).
"""
from dsl import INSTS, VACIO, compas, score, seccion

TITULO = 'Makuta'
MATCH = {'año': 4, 'orden': 9, 'nombre': 'Ritmo de Makuta (Cuba)'}
PDF_PAGES = [37]

V = VACIO
INST = INSTS + ['agogo']


def c(sg=V, sa=V, sm=V, re=V, rp=V, ti=V, ag=V, **kw):
    return compas({'surdo_grave': sg, 'surdo_agudo': sa, 'surdo_medio': sm,
                   'redoblante': re, 'repique': rp, 'timbal': ti,
                   'agogo': ag}, **kw)


# --- LLAMADA PRINCIPIO Y FINAL
# Surdos por altura (grave/medio/agudo) en una sola pauta — se escribe al unísono
# de ataque en las tres voces cuando el PDF marca el mismo golpe; densos → revisar.
def llamada(texto='Llamada principio y final'):
    return [
        c(sg='x===----x===x==x', sa='----x===----x===', sm='--------x===----',
          texto=texto + ' — … revisar con la escuela (alturas)', dyn='f'),
        c(sg='--------------xx', sa='xx--xx----------', sm='--xx----xx------',
          re='--xx----xx------', rp='--xx----xx------', ti='--xx----xx------'),
        c(sg='x---------------', sa='x---------------', sm='x---------------',
          re='--x=------------', rp='--x=------------', ti='--x=------------',
          texto='(cierre corto — … revisar con la escuela)'),
    ]


# --- TOQUE
# Surdos / medio / redo / timbal / repique / campana — figuras de escuela
SU = '---x----x=x=x---'
SM = '------x-----xxx-'
RE = '>xxx>xxx>xxx>xxx'
TI = 'o=====x=o===x=xx'
RP = 'xx--xx--xx--xx--'
AG = 'xx-t-t-xx-t-t---'

toque = [
    c(sg=SU, sa=SU, sm=SM, re=RE, rp=RP, ti=TI, ag=AG,
      repeat_begin=True, repeat_end=True,
      texto='Toque — … revisar con la escuela (campana/HI)', dyn='mf'),
]

SCORE = score(TITULO, 'La Chilinga', 84, INST, [
    seccion('Llamada de principio', llamada(), 1),
    seccion('Toque', toque, 8),
    seccion('Llamada final', llamada('Llamada final'), 1),
])
