"""Toque de Chilinga — Cuadernillo pág. 3 (PDF pág. 6)."""
from dsl import INSTS, SURDOS, VACIO, compas, score, seccion, tutti

TITULO = 'Toque de Chilinga'
MATCH = {'año': 1, 'orden': 1, 'nombre': 'Ritmo Chilinga'}

# --- Llamada inicial y final (2 compases, "Todos")
LL_A = 'xxxx-xxx xxxx-xxx'.replace(' ', '')
LL_B = 'x===--xx x===----'.replace(' ', '')

llamada = [
    compas(tutti(LL_A), repeat_begin=True, texto='Llamada inicial y final', dyn='f'),
    compas(tutti(LL_B), repeat_end=True),
]

# --- Toque (1 compás por instrumento)
toque = [
    compas({
        'surdo_grave': 'x===----x===----',
        'surdo_agudo': '----x===----x===',
        'surdo_medio': 'x=xxx===x=x=x===',
        'redoblante': '>xx>>xx>>xx>>xx>',
        'repique': '>xx>>xx>>xx>>xx>',
        'timbal': '--oo--ss--oo--ss',
    }, repeat_begin=True, repeat_end=True, texto='Toque', dyn='mf'),
]

# --- Llamada intermedia (x4) — 4 compases
INT_RE_1 = 'xxxxxxxxx===--xx'
INT_RE_2 = 'xxxxxxxxx===--xx'
INT_RE_3 = 'xxxxxxxxx===--xx'
INT_RE_4 = 'xxxxxxxxx===----'
INT_SU = '----------x=x==='

intermedia = [
    compas({**tutti(INT_SU, SURDOS), 'redoblante': INT_RE_1, 'repique': INT_RE_1,
            'timbal': VACIO}, repeat_begin=True, texto='Llamada intermedia (x4)', dyn='f'),
    compas({**tutti(INT_SU, SURDOS), 'redoblante': INT_RE_2, 'repique': INT_RE_2,
            'timbal': VACIO}),
    compas({**tutti(INT_SU, SURDOS), 'redoblante': INT_RE_3, 'repique': INT_RE_3,
            'timbal': VACIO}),
    compas({**tutti('----------x=x===', SURDOS), 'redoblante': INT_RE_4,
            'repique': INT_RE_4, 'timbal': VACIO}, repeat_end=True),
]

SCORE = score(TITULO, 'La Chilinga', 100, INSTS, [
    seccion('Llamada inicial y final', llamada, 1),
    seccion('Toque', toque, 8),
    seccion('Llamada intermedia', intermedia, 4),
])
