"""Toque de Chilinga — Cuadernillo pág. 3 (PDF pág. 6).

Figuras leídas del PDF a 300 dpi (storage/.../toque-de-chilinga-cuadernillo.pdf).
"""
from dsl import INSTS, SURDOS, VACIO, compas, score, seccion, tutti, unisono

TITULO = 'Toque de Chilinga'
MATCH = {'año': 1, 'orden': 1, 'nombre': 'Ritmo Chilinga'}
PDF_PAGES = [6]

# --- LLAMADA INICIAL Y FINAL — voz "Todos"
# Compás 1: 4 grupos de 4 semicorcheas (escritura literal del cuadernillo)
# Compás 2: 2 corcheas · sil. negra · 4 semis · 4 semis
# Compás 3: sil. negra · 2 corcheas · sil. negra · 2 corcheas
LL_1 = 'xxxxxxxxxxxxxxxx'
LL_2 = 'x=x=----xxxxxxxx'
LL_3 = '----x=x=----x=x='

llamada = [
    compas(unisono(LL_1), texto='Todos'),
    compas(unisono(LL_2)),
    compas(unisono(LL_3)),
]

# --- TOQUE (×8)
# Grave: 1 y 3 · Agudo: 2 y 4 · Medio: 2 corcheas + sil. negra ×2
# Redo/Repi: 16 semis, acentos en 1-4-7-10-13-16
# Timbal: 2 semis + sil. corchea × 4 (cabezas normales; slap/palma se editan a mano)
toque = [
    compas({
        'surdo_grave': 'x===----x===----',
        'surdo_agudo': '----x===----x===',
        'surdo_medio': 'x=x=----x=x=----',
        'redoblante': '>xx>xx>xx>xx>xx>',
        'repique': '>xx>xx>xx>xx>xx>',
        'timbal': 'xx--xx--xx--xx--',
    }, repeat_begin=True, repeat_end=True, texto='Toque'),
]

# --- LLAMADA INTERMEDIA (×4)
INT_RE_1 = 'xxxxxxxx--xx----'
INT_RE_4 = 'xxxxxxxx--------'
INT_SU = '--x=--x=--x=--x='

intermedia = [
    compas({**tutti(INT_SU, SURDOS), 'redoblante': INT_RE_1, 'repique': INT_RE_1,
            'timbal': VACIO}, repeat_begin=True, texto='Llamada intermedia'),
    compas({**tutti(INT_SU, SURDOS), 'redoblante': INT_RE_1, 'repique': INT_RE_1,
            'timbal': VACIO}),
    compas({**tutti(INT_SU, SURDOS), 'redoblante': INT_RE_1, 'repique': INT_RE_1,
            'timbal': VACIO}),
    compas({**tutti(INT_SU, SURDOS), 'redoblante': INT_RE_4,
            'repique': INT_RE_4, 'timbal': VACIO}, repeat_end=True),
]

SCORE = score(TITULO, 'La Chilinga', 88, INSTS, [
    seccion('Llamada inicial y final', llamada, 1),
    seccion('Toque', toque, 8),
    seccion('Llamada intermedia', intermedia, 4),
])
