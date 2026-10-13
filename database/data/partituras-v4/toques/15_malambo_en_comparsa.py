"""Malambo en Comparsa — Cuadernillo pág. 31 (PDF pág. 34). Compás de 6/8."""
from cuad import hoja, surdos
from dsl import INSTS, score, seccion

TITULO = 'Malambo en Comparsa'
MATCH = {'año': 2, 'orden': 9, 'nombre': 'Malambo en Comparsa'}
PDF_PAGES = [34]

# Grilla de 12 semicorcheas por compás: corchea `x=`, negra `x===`, negra c/punto `x=====`.
c = hoja(INSTS, num=6, den=8, grid=12)

RP = '--x===x=x==='             # sil. corchea · negra · corchea · negra
RP_FIN = '--x===x=--x='
GOLPE = 'x=====------'

llamada = [
    c(repique=RP),
    c(repique=RP, **surdos(GOLPE)),
    c(repique=RP, **surdos(GOLPE)),
    c(**surdos('x=--x=--x===')),
    c(repique=RP_FIN, **surdos(GOLPE)),
]

SU = 'c=--x=c=x==='             # cruz = chapa
RE = '>=x=x=>=x=x='
TI = 'o=o=o=o=s=o='
toque = [
    c(**surdos(SU), redoblante=RE, timbal=TI, repique='------x====='),
    c(**surdos(SU), redoblante=RE, timbal=TI, repique='------x====='),
    c(**surdos(SU), redoblante=RE, timbal=TI, repique='------x====='),
    c(**surdos(SU), redoblante=RE, timbal=TI, repique=RP_FIN),
]

SCORE = score(TITULO, 'La Chilinga', 90, INSTS, [
    seccion('Llamada inicial, intermedia y final', llamada, 1),
    seccion('Toque', toque, 4),
], num=6, den=8)
