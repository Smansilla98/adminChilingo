"""Ochosi — Cuadernillo pág. 7 (PDF pág. 10)."""
from cuad import hoja, rtr, surdos
from dsl import INSTS, score, seccion

TITULO = 'Ochosi'
MATCH = {'año': 1, 'orden': 2, 'nombre': 'Ochosi'}
PDF_PAGES = [10]

c = hoja(INSTS)

llamada = [
    c(**rtr('x=x=x=x=--------'), **surdos('--------xx-x-xx=')),
]

SG = 'x===----x===----'
SA = '----x===----x==='
RE = '>xx>xx>x>xx>xx>x'
RP = '>x>x>xxx>x>x>xxx'

toque = [
    c(rb=True, surdo_grave=SG, surdo_agudo=SA, surdo_medio='x==xx=>=x==xx=>=',
      redoblante=RE, timbal='s==ss=o=s==ss=oo', repique=RP),
    c(re=True, surdo_grave=SG, surdo_agudo=SA, surdo_medio='x==xx=>=xx-xx=>=',
      redoblante=RE, timbal='oooos=oooooos===', repique=RP),
]

variacion = [
    c(rb=True, re=True, repique='xxxx>xxxxxxx>xxx'),
]

final = [
    c(**rtr('----x===----x==='), **surdos('x=x=x=x=--------')),
    c(**rtr('----x===-x==x==='), **surdos('x===============')),
]

SCORE = score(TITULO, 'La Chilinga', 84, INSTS, [
    seccion('Llamada', llamada, 1),
    seccion('Toque', toque, 4),
    seccion('Variación de Repique', variacion, 1),
    seccion('Llamada final', final, 1),
])
