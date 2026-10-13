"""Sacateca — Cuadernillo págs. 26-27 (PDF págs. 29-30)."""
from cuad import T24, hoja, p24, surdos
from dsl import INSTS, score, seccion, unisono

TITULO = 'Sacateca'
MATCH = {'año': 2, 'orden': 4, 'nombre': 'Sacateca'}
PDF_PAGES = [29, 30]

c = hoja(INSTS)

NEGRAS = 'x===x===x===x==='
REDONDA = 'x==============='
GA = {'surdo_grave': REDONDA, 'surdo_agudo': REDONDA}

intro = [
    c(repique=NEGRAS, surdo_medio='--------------x='),
    c(repique=NEGRAS, surdo_medio='----------x=--x=', **GA),
    c(repique=NEGRAS, surdo_medio='--------------x=', **GA),
    c(repique=NEGRAS, surdo_medio='----------x=--x=', **GA),
]

FRASE = '-x=xx=x=-x=xx=x='
llamada = [
    c(repique=FRASE),
    c(texto=T24, repique=p24('xxxxx=x=')),
]

SG = 'x===----x===----'
SA = '----x===----x==='
RE = 'xx>>' * 4
toque = [
    c(rb=True, surdo_grave=SG, surdo_agudo=SA, surdo_medio='---------x=xx=x=', redoblante=RE,
      timbal='--ss--oo--ss--oo', repique='--xx' * 4),
    c(re=True, surdo_grave=SG, surdo_agudo=SA, surdo_medio='-x=xx=x=xxxxx=x=', redoblante=RE,
      timbal='--ss--oo--ss--oo', repique='--xx' * 4),
]

llamada2 = [
    c(timbal='oooos=oooos=oooo', rev='timbal: cabezas y figuras de los tiempos 2 y 3'),
    c(timbal='s==ss==ss=s=s==='),
    c(dyn='p', repique='xxxxxxxxxxxxx===', surdo_medio='--------------x=',
      rev='repique p < f (regulador)'),
]

base_solos = [
    c(rb=True, re=True, surdo_grave=SG, surdo_agudo=SA, surdo_medio='c==c--c=--c=c===',
      redoblante=RE),
]

llamada_solos = [c(**surdos('x==x--x=--------'))]

final = [
    c(**unisono(FRASE)),
    c(**unisono('xxxxx=x=x==x--x=')),
]

SCORE = score(TITULO, 'La Chilinga', 88, INSTS, [
    seccion('Introducción', intro, 1),
    seccion('Llamada', llamada, 1),
    seccion('Toque', toque, 4),
    seccion('Llamada 2', llamada2, 1),
    seccion('Base de solos de timbal', base_solos, 4),
    seccion('Llamada de solos', llamada_solos, 1),
    seccion('Llamada final', final, 1),
])
