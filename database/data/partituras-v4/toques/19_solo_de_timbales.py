"""Solo de Timbales (Buscando a Coco) — Cuadernillo (PDF pág. 38).

Solo escrito para timbal solo. Los pasajes de sextillos y tresillos del
cuadernillo se transcriben con lectura aproximada.
"""
from dsl import compas, score, seccion

TITULO = 'Solo de Timbales (Buscando a Coco)'
MATCH = {'año': 3, 'orden': 2, 'nombre': 'Solo de timbales I'}

SEIS = '6(xxxxxx)'
SEIS_O = '6(oxxxxx)'
TRES = '3(xxx)'

PATRONES = [
    '-xxx-xx-x-xx-xx-',
    '-xx-xx--xxx-xx--',
    SEIS_O + SEIS + SEIS_O + SEIS,
    'x=oo-x=-x=-o=x=-',
    'o=--xxx-x=x=x=--',
    'xx--x=x=xx-xxx--',
    'x=x=oo=-x=xx-xx-',
    'x=x=-x=-' + TRES + TRES,
    SEIS + 'x=--' + TRES + TRES,
    '-xxx-xx-x=--x=x-',
    'x=-x=-x=x=-t=t=-',
    'x=xx-x=-oo=-x=x-',
    't=-t=x=-xx-xxx--',
    'x=x=' + SEIS + SEIS + 'x=x=',
    TRES + TRES + TRES + TRES,
    't=-x=-t=-x=x=-x-',
    'o=--o=--x=xx-xx-',
    'x=------o=------',
    'x===------------',
]

solo = [
    compas({'timbal': p},
           texto='Solo de timbales' if i == 0 else None,
           dyn='mf' if i == 0 else None)
    for i, p in enumerate(PATRONES)
]

SCORE = score(TITULO, 'La Chilinga', 100, ['timbal'], [
    seccion('Solo de timbales', solo, 1),
])
