"""Toque de Marcha — Cuadernillo pág. 32 (PDF pág. 35)."""
from cuad import hoja, rtr, surdos
from dsl import INSTS, score, seccion

TITULO = 'Toque de Marcha'
MATCH = {'año': 1, 'orden': 7, 'nombre': 'Toque de Marcha'}
PDF_PAGES = [35]

c = hoja(INSTS)

llamada = [
    c(**surdos('-------------xxx')),
    c(**surdos('x===-xxxx===----'), **rtr('-xxxx===-xxxx===')),
]

BASE = 'xccxxccxxx-x-xxx'           # rótulo «Surdos Grave, Agudo, Medio y Redoblante»; cruz = chapa
toque = [
    c(rb=True, re=True, **surdos(BASE), redoblante=BASE, timbal='-sss--o=-sso--o=',
      repique=('----x===----x===--x===x=--xxx===', 32)),
]

variacion = [
    c(timbal=('--ooo-ooo=======--ooo-o=o=s=s===', 32),
      repique=('--xxx===xxx=--xxx=--xxx=--xxx===', 32),
      rev='timbal y repique: fusas y cabezas del timbal'),
]

SCORE = score(TITULO, 'La Chilinga', 90, INSTS, [
    seccion('Llamada inicial, intermedia y final', llamada, 1),
    seccion('Toque', toque, 4),
    seccion('Variación (cada 4 vueltas)', variacion, 1),
])
