"""Solo de Timbales (Buscando a Coco) — Cuadernillo pág. 35 (PDF pág. 38).

Solo en timbal. Pasajes densos (sextillos/tresillos) ilegibles en el escaneo
→ marcar revisar; no inventar xx-x.
"""
from dsl import VACIO, compas, score, seccion

TITULO = 'Solo de Timbales (Buscando a Coco)'
MATCH = {'año': 3, 'orden': 2, 'nombre': 'Solo de timbales I'}
PDF_PAGES = [38]

# Figuras de escuela donde se leen; densos → VACIO + texto revisar
SEIS = '6(xxxxxx)'
TRES = '3(xxx)'

# Lecturas probables sin xx-x inventado; grupos de 4 / corcheas
PATRONES = [
    ('xxxx-xx-xxxx-xx-', 'Solo de timbales — … revisar con la escuela', 'mf'),
    ('-xx-xx--xxxx-xx-', None, None),
    (SEIS + SEIS + SEIS + SEIS, '… revisar con la escuela (sextillos)', None),
    ('x=oo-x=-x=-o=x=-', None, None),
    ('o=--xxxx-x=x=x=-', None, None),
    ('xx--x=x=xx-xxx--', None, None),
    ('x=x=oo=-x=xx-xx-', None, None),
    ('x=x=-x=-' + TRES + TRES, '… revisar con la escuela (tresillos)', None),
    (SEIS + 'x=--' + TRES + TRES, None, None),
    ('-xxx-xx-x=--x=x-', None, None),
    ('x=-x=-x=x=-t=t=-', None, None),
    ('x=xx-x=-oo=-x=x-', None, None),
    ('t=-t=x=-xx-xxx--', None, None),
    ('x=x=' + SEIS + SEIS + 'x=x=', None, None),
    (TRES + TRES + TRES + TRES, None, None),
    ('t=-x=-t=-x=x=-x-', None, None),
    ('o=--o=--x=xx-xx-', None, None),
    ('x=------o=------', None, None),
    ('x===------------', None, None),
]

solo = [
    compas({'timbal': pat}, texto=texto, dyn=dyn)
    for pat, texto, dyn in PATRONES
]

SCORE = score(TITULO, 'La Chilinga', 86, ['timbal'], [
    seccion('Solo de timbales', solo, 1),
])
