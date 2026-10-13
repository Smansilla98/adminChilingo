"""Solo de Surdos (Malamakuá) — Cuadernillo pág. 49 (PDF pág. 52). Compás de 6/8.

Cruz = chapa. Donde el cuadernillo escribe cruz y negra a la vez se carga la
negra. Los dos compases en C se escriben como 6 corcheas y quedan aclarados.
"""
from cuad import hoja, rtr, surdos
from dsl import INSTS, score, seccion, unisono

TITULO = 'Solo de Surdos (Malamakuá)'
MATCH = {'año': 3, 'orden': 10, 'nombre': 'Solo de Surdos (Malamakuá)'}
PDF_PAGES = [52]

c = hoja(INSTS, num=6, den=8, grid=12)
DOS = 'negra y cruz a la vez'
TC = 'Compás en C en el cuadernillo (8 corcheas, p < f)'

SOLO = [
    ('x=====c=x===', DOS), ('c=--x=c=====', None), ('c=--x=c=====', None), ('x=====c=x===', DOS),
    ('c=x===x=x===', DOS), ('c=x===x=x===', DOS), ('x=x=x=c=x=x=', DOS), ('x=--x=x=====', DOS),
    ('c=====c=--x=', None), ('c=====c=--x=', None), ('c=====c=--x=', None), ('c=--x=c=--x=', None),
    ('x=====x=====', DOS), ('x=====x=====', DOS),
]
solo = [c(**surdos(p), rev=r) for p, r in SOLO] + [
    c(**surdos('x=x=x=x=x=x='), dyn='p', texto=TC),
]

ENT = dict(rtr('----x=------'))
entrada = [
    c(**ENT),
    c(**rtr('x=====--x===')),
]

acomp = [
    c(redoblante='>=x=>=x=x=>=', timbal='o===s=s=o=o=', repique='x=--x=--xxx=',
      rev='timbal: cabezas'),
    c(redoblante='x=>=x=>=x=x=', timbal='s===s=s=o=o=', repique='--x===x=--xx'),
]

despues = [
    c(**rtr('--x=x=x=x=x='), dyn='p', texto=TC),
    c(**surdos('--x=x=x=x=x='), dyn='p', texto=TC),
]

FINAL = ['--x=x=x=x=x=', '--x=x=--x===', 'x=x===x=--x=', 'x=--x=------']
final = [c(**unisono(p)) for p in FINAL]

SCORE = score(TITULO, 'La Chilinga', 88, INSTS, [
    seccion('Solo de surdos', solo, 2),
    seccion('Entrada al acompañamiento', entrada, 1),
    seccion('Acompañamiento de surdos', acomp, 1),
    seccion('Después de repetición de surdos', despues, 1),
    seccion('Llamada final', final, 1),
], num=6, den=8)
