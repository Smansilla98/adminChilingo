"""Samba Reggae Contemporáneo — Cuadernillo pág. 14 (PDF pág. 17)."""
from cuad import hoja
from dsl import INSTS, score, seccion, unisono

TITULO = 'Samba Reggae Contemporáneo'
MATCH = {'año': 1, 'orden': 11, 'nombre': 'Samba Reggae Contemporáneo'}
PDF_PAGES = [17]

c = hoja(INSTS)

llamada = [c(**unisono('x=x=--x=x===x=x='))]

toque = [
    c(rb=True, re=True, surdo_grave='x===----x===----', surdo_agudo='----x===----x===',
      surdo_medio='------xx------xx', redoblante='>xx>xx>x>xx>xx>x',
      timbal='--ss--oo--ss--oo', repique='>xx>xx>>xx>x>xxx',
      rev='acentos de redoblante y repique'),
]

variacion = [
    c(surdo_medio='------xx------xx'),
    c(surdo_medio='------xx---xx=xx'),
]

SCORE = score(TITULO, 'La Chilinga', 86, INSTS, [
    seccion('Llamada inicial, intermedia y final', llamada, 1),
    seccion('Toque', toque, 4),
    seccion('Variación', variacion, 1),
])
