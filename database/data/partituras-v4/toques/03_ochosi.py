"""Ochosi — Cuadernillo (PDF pág. 10)."""
from dsl import INSTS, SURDOS, VACIO, compas, score, seccion, tutti

TITULO = 'Ochosi'
MATCH = {'año': 1, 'orden': 2, 'nombre': 'Ochosi'}

TOQUE = {
    'surdo_grave': 'x===----x===----',
    'surdo_agudo': '----x===----x===',
    'surdo_medio': 'x==xx=>=x==xx=>=',
    'redoblante': '>xx>xx>x>xx>xx>x',
    'repique': '>x>x>xxx>x>x>xxx',
    'timbal': 'o==oo=s=o==oo=ss',
}

# --- Llamada: redoblante/timbal/repique en los tiempos 1-2, surdos en 3-4
llamada = [
    compas({'redoblante': 'x=x=x=x=--------', 'timbal': 'x=x=x=x=--------',
            'repique': 'x=x=x=x=--------',
            **tutti('--------xx-x-xx=', SURDOS)},
           texto='Llamada', dyn='f'),
]

toque = [
    compas(TOQUE, repeat_begin=True, texto='Toque', dyn='mf'),
    compas(TOQUE, repeat_end=True),
]

variacion = [
    compas({**TOQUE, 'repique': 'xxxx>xxxxxxx>xxx'},
           repeat_begin=True, repeat_end=True, texto='Variación de Repique'),
]

llamada_final = [
    compas({'redoblante': '----x===----x===', 'timbal': '----x===----x===',
            'repique': '----x===----x===',
            **tutti('x=x=x=x=--------', SURDOS)},
           texto='Llamada final', dyn='f'),
    compas({'redoblante': '----x===-x==x===', 'timbal': '----x===-x==x===',
            'repique': '----x===-x==x===',
            **tutti('x===============', SURDOS)}),
]

SCORE = score(TITULO, 'La Chilinga', 84, INSTS, [
    seccion('Llamada', llamada, 1),
    seccion('Toque', toque, 8),
    seccion('Variación de Repique', variacion, 4),
    seccion('Llamada final', llamada_final, 1),
])
