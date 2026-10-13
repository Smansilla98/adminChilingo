"""Chilinga II — Cuadernillo pág. 33 (PDF pág. 36).

El escaneo de esta página es el más pobre del cuadernillo: figuras muy chicas
y apretadas. Toda la transcripción es lectura probable y va marcada.
"""
from cuad import hoja, surdos
from dsl import INSTS, score, seccion

TITULO = 'Chilinga II'
MATCH = {'año': 5, 'orden': 2, 'nombre': 'Chilinga II'}
PDF_PAGES = [36]

c = hoja(INSTS)

RE = 'xxx>x>>xxxx>x>>x'
TI = 'oo-s--s=-oos-sso'
RP = 'aa-x-xxxaa-x-xxx'        # triángulo = golpe agudo

toque = [
    c(ending=1, **surdos('t==x-ttxx==x-xxt'), redoblante=RE, timbal=TI, repique=RP,
      rev='todo el toque: escaneo ilegible; casillas 1 y 2'),
    c(ending=2, rb=True, **surdos('t==x-xxtx==x-xxt'), redoblante=RE, timbal=TI, repique=RP,
      rev='surdos: figuras y guiones de tapado'),
    c(**surdos('x==x-xxtx==x-xxt'), redoblante=RE, timbal=TI, repique=RP,
      rev='timbal y repique: cabezas y fusas'),
    c(re=True, **surdos('x==x-xxtxx==--x='), redoblante='xxx>x>>xxxx>-->=',
      timbal='oo-s--s=--o=--o=', repique='aa-xaa-xa=x=a=x=', rev='cierre de los cuatro'),
]

SCORE = score(TITULO, 'La Chilinga', 88, INSTS, [
    seccion('Toque', toque, 1),
])
