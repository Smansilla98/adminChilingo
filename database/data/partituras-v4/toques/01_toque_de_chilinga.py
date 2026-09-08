"""Toque de Chilinga — Cuadernillo pág. 3 (PDF pág. 6).

Transcripción literal del PDF (figuras = escritura de la escuela).
"""
from dsl import INSTS, SURDOS, VACIO, compas, score, seccion, tutti, unisono

TITULO = 'Toque de Chilinga'
MATCH = {'año': 1, 'orden': 1, 'nombre': 'Ritmo Chilinga'}
PDF_PAGES = [6]

# --- LLAMADA INICIAL Y FINAL (Todos) — 4 compases (PDF pág. 6)
# El cuadernillo arranca con semis (no con corcheas): m1 y m3 estaban al revés.
# 1: (4 semis + 2 corcheas) × 2
# 2: sil. negra · 2 corcheas · sil. negra · 2 corcheas
# 3: (2 corcheas + 4 semis) × 2
# 4: igual al 2
LL_1 = 'xxxxx=x=xxxxx=x='  # xxxx x=x= xxxx x=x=
LL_2 = '----x=x=----x=x='
LL_3 = 'x=x=xxxxx=x=xxxx'   # x=x=xxxx x=x=xxxx

llamada = [
    compas(unisono(LL_1), texto='Todos'),
    compas(unisono(LL_2)),
    compas(unisono(LL_3)),
    compas(unisono(LL_2)),
]

# --- TOQUE (×8)
# Grave: negras 1 y 3 · Agudo: negras 2 y 4
# Medio: (2 corcheas + negra) × 2
# Redo/Repi: 16 semis; acento en la 1ª de cada tiempo
# Timbal: (2 corcheas + sil. negra) × 2
toque = [
    compas({
        'surdo_grave': 'x===----x===----',
        'surdo_agudo': '----x===----x===',
        'surdo_medio': 'x=x=x===x=x=x===',
        'redoblante': '>xxx>xxx>xxx>xxx',
        'repique': '>xxx>xxx>xxx>xxx',
        'timbal': '----x=x=----x=x=',
    }, repeat_begin=True, repeat_end=True, texto='Toque'),
]

# --- LLAMADA INTERMEDIA (×4)
# Redo/Repi: misma frase que la llamada inicial
# Surdos: (sil. negra + 2 corcheas) × 2
INT_SU = '----x=x=----x=x='

intermedia = [
    compas({**tutti(INT_SU, SURDOS), 'redoblante': LL_1, 'repique': LL_1,
            'timbal': VACIO}, repeat_begin=True, texto='Llamada intermedia'),
    compas({**tutti(INT_SU, SURDOS), 'redoblante': LL_2, 'repique': LL_2,
            'timbal': VACIO}),
    compas({**tutti(INT_SU, SURDOS), 'redoblante': LL_3, 'repique': LL_3,
            'timbal': VACIO}),
    compas({**tutti(INT_SU, SURDOS), 'redoblante': LL_2, 'repique': LL_2,
            'timbal': VACIO}, repeat_end=True),
]

SCORE = score(TITULO, 'La Chilinga', 88, INSTS, [
    seccion('Llamada inicial y final', llamada, 1),
    seccion('Toque', toque, 8),
    seccion('Llamada intermedia', intermedia, 4),
])
