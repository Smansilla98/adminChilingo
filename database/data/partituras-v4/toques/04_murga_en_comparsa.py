"""Murga en Comparsa — Cuadernillo pág. 8 (PDF pág. 11)."""
from cuad import agudos, hoja, rtr, surdos
from dsl import INSTS, score, seccion

TITULO = 'Murga en Comparsa'
MATCH = {'año': 1, 'orden': 10, 'nombre': 'Murga en Comparsa'}
PDF_PAGES = [11]

c = hoja(INSTS)

llamada = [
    c(**surdos('x==x--x=-x==x==='), **rtr('--------------x=')),
    c(**surdos('x==x--x=-x==x==='), **rtr('--------------x=')),
]

toque = [
    c(rb=True, re=True, **surdos('x==xx=x=x==xx=x='), **agudos('>=x>' * 4),
      timbal='--ss--oo--ss--oo'),
]

RP = '----x===----x==='
variacion = [
    c(surdo_medio='x===----------x=', repique=RP),
    c(surdo_medio='x===----x===x===', repique=RP),
    c(surdo_medio='x===----------x=', repique=RP),
    c(surdo_medio='x===------------', repique='----x===6(xxxx--)6(xxxx--)'),
]

SCORE = score(TITULO, 'La Chilinga', 88, INSTS, [
    seccion('Llamada inicial e intermedia', llamada, 1),
    seccion('Toque', toque, 4),
    seccion('Variación sobre Toque', variacion, 1),
])
