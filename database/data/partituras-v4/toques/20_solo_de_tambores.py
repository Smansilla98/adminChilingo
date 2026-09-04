"""Solo de Tambores (Chiruda) — Cuadernillo pág. 36 (PDF pág. 39).

Unísono estricto (voz Todos). Acentos en 1ª de cada tiempo (escritura de escuela).
Tramo 6/8 del original → corcheas, aclarado en texto.
"""
from dsl import SURDOS, compas, score, seccion, unisono

TITULO = 'Solo de Tambores (Chiruda)'
MATCH = {'año': 3, 'orden': 4, 'nombre': 'Solo de redoblantes (Chiruda)'}
PDF_PAGES = [39]

INST = SURDOS + ['redoblante', 'repique']

# Escuela: 16 semis, acento en 1ª de cada tiempo (no xx-x inventado)
A = '>xxx>xxx>xxx>xxx'
B = '>xxx>xxx>xxx>xxx'
C = '>xx>xx>xx>xx>xxx'   # variante con acento ternario si el PDF lo marca
D = 'xx>>xx>>xx>>xx>>'
E = '>xxxx>xx>xxxx>xx'
OCHOS = 'x=x=x=x=x=x=x=x='   # tramo 6/8 → 8 corcheas
OCHOS2 = 'x=x=x=x=x=x=x=x='

BLOQUES = [
    (A, 'Solo de tambores — … revisar con la escuela (acentos finos)', 'f'),
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
    compas(unisono(pat), texto=texto, dyn=dyn)
    for pat, texto, dyn in BLOQUES
]

SCORE = score(TITULO, 'La Chilinga', 86, INST, [
    seccion('Solo de tambores', solo, 1),
])
