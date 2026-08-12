"""Solo de Tambores (Chiruda) — Cuadernillo (PDF pág. 39).

Escrito en un solo pentagrama para todos los tambores. El cuadernillo pasa a
6/8 en el tramo central y vuelve a 4/4; acá se mantiene 4/4 y ese tramo se
escribe en corcheas, aclarado en el texto del compás.
"""
from dsl import SURDOS, compas, score, seccion

TITULO = 'Solo de Tambores (Chiruda)'
MATCH = {'año': 3, 'orden': 4, 'nombre': 'Solo de redoblantes (Chiruda)'}

INST = SURDOS + ['redoblante', 'repique']

A = '>xxx>xxx>xxx>xxx'
B = 'x>xxx>xxx>xxx>xx'
C = '>xx>x>xxx>xx>x>x'
D = 'xx>>xx>>xx>>xx>>'
E = '>xxxx>xx>xxxx>xx'
OCHOS = '>=x=>=x=>=x=>=x='
OCHOS2 = 'x=>=x=>=x=>=x=>='

BLOQUES = [
    (A, 'Solo de tambores', 'f'),
    (B, None, None),
    (C, None, None),
    (D, None, None),
    (E, None, None),
    (A, None, None),
    (B, None, None),
    (C, None, None),
    (OCHOS, 'Tramo en 6/8 en el original, escrito en corcheas', None),
    (OCHOS2, None, None),
    (OCHOS, None, None),
    (OCHOS2, None, None),
    ('x=x=x=x=x===----', 'Corte', None),
    ('t=t=>xxx>xxx>xxx', 'Vuelve el 4/4 (tapados en el arranque)', None),
    (A, None, None),
    (D, None, None),
    (E, None, None),
    (A, 'cresc. p → f', 'p'),
    ('o===============', None, 'f'),
]

solo = [
    compas({i: pat for i in INST}, texto=texto, dyn=dyn)
    for pat, texto, dyn in BLOQUES
]

SCORE = score(TITULO, 'La Chilinga', 100, INST, [
    seccion('Solo de tambores', solo, 1),
])
