"""Marcha Camión — Cuadernillo págs. 4-6 (PDF págs. 7-9).

Transcripción literal del PDF (figuras = escritura de la escuela).
"""
from dsl import INSTS, SURDOS, VACIO, compas, score, seccion, tutti, unisono

TITULO = 'Marcha Camión'
MATCH = {'año': 1, 'orden': 3, 'nombre': 'Marcha Camión'}
PDF_PAGES = [7, 8, 9]

# Redo (bases): 16 semis, acento en 1ª de cada tiempo (como Chilinga)
REDO_BASE = '>xxx>xxx>xxx>xxx'
# Intro redo: (corchea acentuada + 2 semis) × 4
INTRO_REDO = '>=xx>=xx>=xx>=xx'
# Timbal: (sil. corchea + 2 semis) × 4; diamante=palma(p) / lleno=nota(x)
TIMBAL_BASE = '--pp--xx--pp--xx'
TIMBAL_SEXT = '6:2(xxxxxx)----xx--pp--xx'
# Repique: (sil. corchea + corchea) × 4
REPI_BASE = '--x=--x=--x=--x='
REPI_SEXT_A = '--x=--x=--x=6:2(xxxxxx)--'
REPI_SEXT_B = '--x=--x=--x=-xxx'

# --- LLAMADA
# Surdos m1: (corchea + 2 semis) × 2 · sil.corchea + corchea · 2 corcheas
# Surdos m2: sil. blanca · sil. corchea c/punto + flam · 2 corcheas
# HI m2: 8 semis · negra · sil. negra  (NO xx-x)
LL_SU_1 = 'x=xxx=xx--x=x=x='
LL_SU_2 = '-----------fx=x='
LL_HI_2 = 'xxxxxxxxx===----'

llamada = [
    compas({**tutti(LL_SU_1, SURDOS), 'redoblante': VACIO, 'repique': VACIO,
            'timbal': VACIO}, texto='Llamada', dyn='f'),
    compas({**tutti(LL_SU_2, SURDOS), 'redoblante': LL_HI_2, 'repique': LL_HI_2,
            'timbal': LL_HI_2}),
]

# --- INTRODUCCIÓN (2 compases 4/4 + cierre 2/4)
# Grave: negras 1 y 3 · Agudo: negras 2 y 4
# Medio: sil. blanca · sil. corchea c/punto + semi · 2 corcheas
# Redo/Repi: 16 semis acentuadas · Timbal: base
# Cierre 2/4 (completado con silencios a 4/4, como otros toques del DSL)
INT_MEDIO = '-----------xx=x='
CIERRE_24 = '---xx=x=--------'   # sil. corchea c/punto + semi · 2 corcheas
CIERRE_HI = 'x===------------'   # negra + silencio

introduccion = [
    compas({
        'surdo_grave': 'x===----x===----',
        'surdo_agudo': '----x===----x===',
        'surdo_medio': INT_MEDIO,
        'redoblante': INTRO_REDO,
        'repique': INTRO_REDO,
        'timbal': TIMBAL_BASE,
    }, repeat_begin=True, texto='Introducción', dyn='mf'),
    compas({
        'surdo_grave': 'x===----x===----',
        'surdo_agudo': '----x===----x===',
        'surdo_medio': INT_MEDIO,
        'redoblante': INTRO_REDO,
        'repique': INTRO_REDO,
        'timbal': TIMBAL_BASE,
    }, repeat_end=True),
    compas({
        'surdo_grave': CIERRE_24,
        'surdo_agudo': CIERRE_24,
        'surdo_medio': CIERRE_24,
        'redoblante': CIERRE_HI,
        'repique': CIERRE_HI,
        'timbal': CIERRE_HI,
    }, texto='Cierre (compás de 2/4)'),
]

# --- BASE 1
# Surdos: (corchea c/punto + semi · sil. negra · 2 corcheas · sil/negra)
B1_SU_1 = 'x==x----x=x=----'
B1_SU_2 = 'x==x----x=x=x==='

base1 = [
    compas({**tutti(B1_SU_1, SURDOS), 'redoblante': REDO_BASE,
            'repique': REPI_BASE, 'timbal': TIMBAL_BASE},
           repeat_begin=True, texto='Base 1', dyn='mf'),
    compas({**tutti(B1_SU_2, SURDOS), 'redoblante': REDO_BASE,
            'repique': REPI_BASE, 'timbal': TIMBAL_BASE}, repeat_end=True),
]

# --- BASE 2 (sextillos en timbal y repique)
# Surdos: corchea c/punto + semi · negra · 2 corcheas · sil/negra
B2_SU_1 = 'x==xx===x=x=----'
B2_SU_2 = 'x==xx===x=x=x==='

base2 = [
    compas({**tutti(B2_SU_1, SURDOS), 'redoblante': REDO_BASE,
            'repique': REPI_SEXT_A, 'timbal': TIMBAL_BASE},
           repeat_begin=True, texto='Base 2', dyn='mf'),
    compas({**tutti(B2_SU_2, SURDOS), 'redoblante': REDO_BASE,
            'repique': REPI_SEXT_B, 'timbal': TIMBAL_SEXT}, repeat_end=True),
]

# --- BASE 3
# Surdos m1: 4 semis · negra · 2 corcheas · sil. negra
# Surdos m2: 4 semis · (corchea c/punto + semi) × 2 · 2 corcheas
B3_SU_1 = 'xxxxx===x=x=----'
B3_SU_2 = 'xxxxx==xx==xx=x='

base3 = [
    compas({**tutti(B3_SU_1, SURDOS), 'redoblante': REDO_BASE,
            'repique': REPI_SEXT_A, 'timbal': TIMBAL_BASE},
           repeat_begin=True, texto='Base 3', dyn='mf'),
    compas({**tutti(B3_SU_2, SURDOS), 'redoblante': REDO_BASE,
            'repique': REPI_SEXT_B, 'timbal': TIMBAL_SEXT}, repeat_end=True),
]

# --- VARIACIÓN DE LA BASE 3 (4 compases, p → f)
# Figuras densas / seisillos parcialmente ilegibles en el escaneo → revisar
variacion = [
    compas({**tutti(B3_SU_1, SURDOS), 'redoblante': REDO_BASE,
            'repique': REPI_BASE, 'timbal': TIMBAL_BASE},
           repeat_begin=True, texto='Variación Base 3 — … revisar con la escuela', dyn='p'),
    compas({**tutti('xxxxxxxxxxxxxxxx', SURDOS), 'redoblante': REDO_BASE,
            'repique': REPI_BASE, 'timbal': TIMBAL_BASE}),
    compas({**tutti(B3_SU_1, SURDOS), 'redoblante': REDO_BASE,
            'repique': '--x=--x=6:2(xxxxxx)------',
            'timbal': '--pp--pp6:2(xxxxxx)------'}),
    compas({**tutti(B3_SU_2, SURDOS), 'redoblante': REDO_BASE,
            'repique': '----6:2(xxxxxx)------x===',
            'timbal': '6:2(xxxxxx)------6:2(xxxxxx)------'},
           repeat_end=True, dyn='f'),
]

# --- LLAMADA FINAL — voz "Todos"
# … revisar: el escaneo de pág. 9 no deja leer todas las figuras con certeza
llamada_final = [
    compas(unisono('x=x=x=x=x=====x='),
           texto='Llamada final — … revisar con la escuela', dyn='f'),
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
