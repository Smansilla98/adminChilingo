"""Ochosi — Cuadernillo pág. 7 (PDF pág. 10).

Transcripción literal del PDF (figuras = escritura de la escuela).
"""
from dsl import INSTS, SURDOS, VACIO, compas, score, seccion, tutti

TITULO = 'Ochosi'
MATCH = {'año': 1, 'orden': 2, 'nombre': 'Ochosi'}
PDF_PAGES = [10]

# Toque
# Grave: negras 1 y 3 · Agudo: negras 2 y 4
# Medio: (corchea c/punto + semi · corchea + corchea acentuada) × 2
# Redo/Repi: 16 semis, acento en 1ª de cada tiempo
# Timbal: (abierto c/punto + semi · abierto + nota) × 2
TOQUE = {
    'surdo_grave': 'x===----x===----',
    'surdo_agudo': '----x===----x===',
    'surdo_medio': 'x==xx=>=x==xx=>=',
    'redoblante': '>xxx>xxx>xxx>xxx',
    'repique': '>xxx>xxx>xxx>xxx',
    'timbal': 'o==ox=o=o==ox=o=',
}

# Llamada: HI 4 corcheas + sil. blanca; Surdos sil. blanca + 4 corcheas
llamada = [
    compas({'redoblante': 'x=x=x=x=--------', 'timbal': 'x=x=x=x=--------',
            'repique': 'x=x=x=x=--------',
            **tutti('--------x=x=x=x=', SURDOS)},
           texto='Llamada', dyn='f'),
]

toque = [
    compas(TOQUE, repeat_begin=True, texto='Toque', dyn='mf'),
    compas(TOQUE, repeat_end=True),
]

variacion = [
    compas({**TOQUE, 'repique': '>xxx>xxx>xxx>x>x'},
           repeat_begin=True, repeat_end=True,
           texto='Variación de Repique — … revisar con la escuela'),
]

# Llamada final: HI negras 2 y 4; Surdos 4 corcheas + sil. blanca · redonda
llamada_final = [
    compas({'redoblante': '----x===----x===', 'timbal': '----x===----x===',
            'repique': '----x===----x===',
            **tutti('x=x=x=x=--------', SURDOS)},
           texto='Llamada final', dyn='f'),
    compas({'redoblante': '----x===x=======', 'timbal': '----x===x=======',
            'repique': '----x===x=======',
            **tutti('x===============', SURDOS)},
           texto='Llamada final — … revisar con la escuela (cierre HI)'),
]

SCORE = score(TITULO, 'La Chilinga', 84, INSTS, [
    seccion('Llamada', llamada, 1),
    seccion('Toque', toque, 8),
    seccion('Variación de Repique', variacion, 4),
    seccion('Llamada final', llamada_final, 1),
])
