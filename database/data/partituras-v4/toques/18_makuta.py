"""Makuta — Cuadernillo pág. 34 (PDF pág. 37). «Campana» va en la voz del agogó."""
from cuad import hoja, rtr, surdos
from dsl import INSTS, score, seccion

TITULO = 'Makuta'
MATCH = {'año': 4, 'orden': 9, 'nombre': 'Ritmo de Makuta (Cuba)'}
PDF_PAGES = [37]

INST = INSTS + ['agogo']
c = hoja(INST)

llamada = [
    c(**surdos('x===x===x===x==x')),
    c(**surdos('xx-x-xxxx===----'), **rtr('------------xxx='), rev='surdos, tiempos 1 y 2'),
    c(**surdos('--xxx===------xx'), **rtr('--------xxx=----')),
    c(**surdos('x==xxx-x-xxxx==='), rev='surdos, tiempos 2 y 3'),
    c(texto='Compás de 1/4 en el cuadernillo', **rtr('xx==------------')),
]

GA = '---x-----x=x----'
toque = [
    c(rb=True, re=True, surdo_grave=GA, surdo_agudo=GA, surdo_medio='------x=----xxx=',
      redoblante='>xx>xx>xxx>x>xxx', timbal='s==os=oos===ooo=', repique='xx==' * 4,
      agogo='c=cc-cc=c=cc-cc=', rev='timbal: cabeza hueca con guión (¿slap o presionado?)'),
]

SCORE = score(TITULO, 'La Chilinga', 84, INST, [
    seccion('Llamada principio y final', llamada, 1),
    seccion('Toque', toque, 4),
])
