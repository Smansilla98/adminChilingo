"""Malamakuá — Cuadernillo págs. 47-48 (PDF págs. 50-51). Compás de 6/8.

En los surdos el cuadernillo escribe cruz (chapa), negra, y las dos a la vez.
El editor admite una cabeza por golpe: donde van las dos se carga la negra.
"""
from cuad import hoja, surdos
from dsl import INSTS, score, seccion, unisono

TITULO = 'Malamakuá'
MATCH = {'año': 3, 'orden': 3, 'nombre': 'Malamakua I'}
PDF_PAGES = [50, 51]

# Grilla de 12 semicorcheas: corchea `x=`, negra `x===`, negra con punto `x=====`.
c = hoja(INSTS, num=6, den=8, grid=12)

RP_A = 'x=--x=--xxx='
RP_B = '--x===x=--xx'
GOLPE = 'x=====------'

intro = [
    c(repique=RP_A, **surdos(GOLPE), rev='¿el repique sigue durante los 8 compases?'),
    c(repique=RP_B),
    c(repique=RP_A, **surdos(GOLPE)),
    c(repique=RP_B),
    c(repique=RP_A, **surdos(GOLPE)),
    c(repique=RP_B, **surdos(GOLPE)),
    c(repique=RP_A, **surdos('x=--x=--x===')),
    c(repique=RP_B, **surdos(GOLPE)),
]

RE_A = '>=x=>=x=x=>='
RE_B = 'x=>=x=>=x=x='
TI = '----s=--o=o='
DOS = 'surdos: negra y cruz a la vez'

SU = ['c=x=x=c=x=x=', 'c=x===x=====', 'x=x=x=c=x=x=', 'x=--x=x=====',
      'c=x=x=c=x=x=', 'c=x===x=====', 'x=--x=c=x===', 'x=====c=====']
toque = [
    c(**surdos(s), redoblante=(RE_A, RE_B)[i % 2], timbal=TI, repique=(RP_A, RP_B)[i % 2],
      rev=DOS if i in (1, 2, 3, 5, 6, 7) else None)
    for i, s in enumerate(SU)
]

VS_A, VS_B = 'x=x=x=--x=x=', 'x=--x=x=x==='
TV = 's=--o=o====='
variacion = [
    c(**surdos((VS_A, VS_B)[i % 2]), redoblante=(RE_A, RE_B)[i % 2], timbal=TV,
      repique=(RP_A, RP_B)[i % 2])
    for i in range(6)
] + [
    c(**surdos('x=--x=--x==='), redoblante='>=x=>=x=>=x=', timbal='o===o=--o===',
      repique='x===x=--x==='),
    c(**surdos(GOLPE), redoblante='>=-->>>=====', timbal=GOLPE.replace('x', 'o'),
      repique='x=--xxx====='),
]

FINAL = ['--x=x=x=x=x=', '--x=x=--x===', 'x=x===x=--x=', 'x=--x=------']
final = [c(**unisono(p)) for p in FINAL]

SCORE = score(TITULO, 'La Chilinga', 88, INSTS, [
    seccion('Introducción', intro, 1),
    seccion('Toque', toque, 1),
    seccion('Variación', variacion, 1),
    seccion('Llamada final', final, 1),
], num=6, den=8)
