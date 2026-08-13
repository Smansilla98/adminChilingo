"""Toque de Rap — Cuadernillo (PDF págs. 12-13)."""
from dsl import INSTS, SURDOS, VACIO, compas, score, seccion, tutti

TITULO = 'Toque de Rap'
MATCH = {'año': 1, 'orden': 8, 'nombre': 'Rap - Murga'}

REDO_A = 'xxxx>xxxxxxx>xxx'
REDO_B = 'xxxxxxxx>x>x>x>x'
TIMBAL = '--oo--ss--oo--ss'
REPI_A = '----x==='
REPI_M1 = '----x===----x==='
REPI_M2 = '----x===--x=x=x='

# --- Llamada inicial (3 compases): surdos con tresillos p -> f, luego respuesta
llamada = [
    compas({**tutti('3(>xx)3(>xx)3(>xx)3(>xx)', SURDOS),
            'redoblante': VACIO, 'repique': VACIO, 'timbal': VACIO},
           texto='Llamada (p a f)', dyn='p'),
    compas({**tutti('x===x=x=--x=x===', SURDOS),
            'redoblante': '--x=----x===--x=', 'timbal': '--x=----x===--x=',
            'repique': '--x=----x===--x='}, dyn='f'),
    compas({**tutti(VACIO, SURDOS), 'redoblante': VACIO, 'timbal': VACIO,
            'repique': '----------x=x=xx'}),
]

# --- Base 1
BASE1_SU = 'xx==--xx--------'
base1 = [
    compas({'surdo_grave': BASE1_SU, 'surdo_medio': BASE1_SU,
            'surdo_agudo': '----x===----x===', 'redoblante': REDO_A,
            'repique': REPI_M1, 'timbal': TIMBAL},
           repeat_begin=True, texto='Base 1', dyn='mf'),
    compas({'surdo_grave': BASE1_SU, 'surdo_medio': BASE1_SU,
            'surdo_agudo': '----x===----x===', 'redoblante': REDO_B,
            'repique': REPI_M2, 'timbal': TIMBAL}, repeat_end=True),
]

# --- Llamada intermedia (repique solo)
intermedia = [
    compas({**tutti(VACIO, SURDOS), 'redoblante': VACIO, 'timbal': VACIO,
            'repique': '-xxx==x=-xx=-xx='},
           repeat_begin=True, texto='Llamada intermedia (repique)', dyn='f'),
    compas({**tutti(VACIO, SURDOS), 'redoblante': VACIO, 'timbal': VACIO,
            'repique': 'x==x-x=-xx--x=x='}, repeat_end=True),
]

# --- Base 2
base2 = [
    compas({'surdo_grave': 'xxxx--xx--xx----', 'surdo_medio': 'xxxx--xx--xx----',
            'surdo_agudo': '----x===----x===', 'redoblante': REDO_A,
            'repique': REPI_M1, 'timbal': TIMBAL},
           repeat_begin=True, texto='Base 2', dyn='mf'),
    compas({'surdo_grave': 'xxxx--xx--------', 'surdo_medio': 'xxxx--xx--------',
            'surdo_agudo': '----x===----x===', 'redoblante': REDO_B,
            'repique': REPI_M2, 'timbal': TIMBAL}, repeat_end=True),
]

SCORE = score(TITULO, 'La Chilinga', 82, INSTS, [
    seccion('Llamada', llamada, 1),
    seccion('Base 1', base1, 4),
    seccion('Llamada intermedia', intermedia, 1),
    seccion('Base 2', base2, 4),
])
