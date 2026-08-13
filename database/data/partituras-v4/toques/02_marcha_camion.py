"""Marcha Camión — Cuadernillo (PDF págs. 7-9)."""
from dsl import INSTS, SURDOS, VACIO, compas, score, seccion, tutti, unisono

TITULO = 'Marcha Camión'
MATCH = {'año': 1, 'orden': 3, 'nombre': 'Marcha Camión'}

REDO_BASE = 'xx>xxx>xxx>xxx>x'
TIMBAL_BASE = '--oo--ss--oo--ss'
TIMBAL_SEXT = '6:2(ssssss)----ss--oo--ss'
REPI_BASE = '--x=--x=--x=--x='
REPI_SEXT_A = '--x=--x=--x=6:2(xxxxxx)--'
REPI_SEXT_B = '--x=--x=--x=-xxx'

# --- LLAMADA — voz "Todos" (unísono estricto)
llamada = [
    compas(unisono('x=xxx=xx--xxx=x='), texto='Llamada', dyn='f'),
    compas(unisono('xx-xxx-xx===----')),
]

# --- Introducción (cierra con compás de 2/4 escrito como 4/4)
introduccion = [
    compas({
        'surdo_grave': 'x===----x===----',
        'surdo_agudo': '----x===----x===',
        'surdo_medio': '-----------fx=x=',
        'redoblante': '>xxx>xxx>xxx>xxx',
        'repique': '>xxx>xxx>xxx>xxx',
        'timbal': TIMBAL_BASE,
    }, repeat_begin=True, repeat_end=True, texto='Introducción', dyn='mf'),
    compas(unisono('x===------------'), texto='Cierre (compás de 2/4)'),
]

# --- Base 1
base1 = [
    compas({**tutti('x==x----x=x=----', SURDOS), 'redoblante': REDO_BASE,
            'repique': REPI_BASE, 'timbal': TIMBAL_BASE},
           repeat_begin=True, texto='Base 1', dyn='mf'),
    compas({**tutti('x==x----x=x=x===', SURDOS), 'redoblante': REDO_BASE,
            'repique': REPI_BASE, 'timbal': TIMBAL_BASE}, repeat_end=True),
]

# --- Base 2 (sextillos en timbal y repique)
base2 = [
    compas({**tutti('x==x----x=x=----', SURDOS), 'redoblante': REDO_BASE,
            'repique': REPI_SEXT_A, 'timbal': TIMBAL_BASE},
           repeat_begin=True, texto='Base 2', dyn='mf'),
    compas({**tutti('x==xx===x=x=x===', SURDOS), 'redoblante': REDO_BASE,
            'repique': REPI_SEXT_B, 'timbal': TIMBAL_SEXT}, repeat_end=True),
]

# --- Base 3
base3 = [
    compas({**tutti('xxxxx===x=x=----', SURDOS), 'redoblante': REDO_BASE,
            'repique': REPI_SEXT_A, 'timbal': TIMBAL_BASE},
           repeat_begin=True, texto='Base 3', dyn='mf'),
    compas({**tutti('xxxxx==xx==xx===', SURDOS), 'redoblante': REDO_BASE,
            'repique': REPI_SEXT_B, 'timbal': TIMBAL_SEXT}, repeat_end=True),
]

# --- Variación de la Base 3 (4 compases, p -> f)
variacion = [
    compas({**tutti('xxxxx===x=x=----', SURDOS), 'redoblante': REDO_BASE,
            'repique': REPI_BASE, 'timbal': TIMBAL_BASE},
           repeat_begin=True, texto='Variación Base 3 (p a f)', dyn='p'),
    compas({**tutti('xxxxxxxxxxxxxxxx', SURDOS), 'redoblante': REDO_BASE,
            'repique': REPI_BASE, 'timbal': TIMBAL_BASE}),
    compas({**tutti('xxxxx===x=x=----', SURDOS), 'redoblante': REDO_BASE,
            'repique': '--x=--x=6:2(xxxxxx)------',
            'timbal': '--oo--oo6:2(ssssss)------'}),
    compas({**tutti('xxxxx==xx==xx===', SURDOS), 'redoblante': REDO_BASE,
            'repique': '----6:2(xxxxxx)------x===',
            'timbal': '6:2(ssssss)------6:2(ssssss)------'},
           repeat_end=True, dyn='f'),
]

# --- LLAMADA FINAL — voz "Todos" (unísono estricto)
llamada_final = [
    compas(unisono('x=x=x=x=x=====x='), texto='Llamada final', dyn='f'),
    compas(unisono('x=====x=x===----')),
]

SCORE = score(TITULO, 'La Chilinga', 86, INSTS, [
    seccion('Llamada', llamada, 1),
    seccion('Introducción', introduccion, 1),
    seccion('Base 1', base1, 4),
    seccion('Base 2', base2, 4),
    seccion('Base 3', base3, 4),
    seccion('Variación Base 3', variacion, 2),
    seccion('Llamada final', llamada_final, 1),
])
