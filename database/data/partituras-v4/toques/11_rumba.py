"""Rumba — Cuadernillo págs. 21-22 (PDF págs. 24-25).

Transcripción literal del PDF (figuras = escritura de la escuela).
"""
from dsl import INSTS, SURDOS, VACIO, compas, score, seccion, tutti, unisono

TITULO = 'Rumba'
MATCH = {'año': 2, 'orden': 7, 'nombre': 'Ritmo de Rumba'}
PDF_PAGES = [24, 25]

V = VACIO

# Llamada todos: corchea c/punto + semi · sil. corchea c/punto + flam · …
LLAMADA = 'x==x---fx=--x=x='

BASE = {
    'surdo_grave': 'x==x------------',
    'surdo_agudo': 'x=x=-x=x=x=x=x=-',
    'surdo_medio': '--------x==xx===',
    'redoblante': '>xx>xxx>xx>x>xxx',
    'repique': 'x=x=-x=x=x=-x=x=',
    'timbal': 'x=o--x=t=t--o=xx',
}

llamada = [compas(unisono(LLAMADA), texto='Llamada inicial y final', dyn='f')]

toque = [compas(dict(BASE), repeat_begin=True, repeat_end=True,
                texto='Toque — … revisar con la escuela (repi/timbal)', dyn='mf')]

TRES = '3(xxx)3(xxx)'

llamada_sobre = [
    compas({**BASE, 'surdo_medio': 'x==x---fx=x===--'},
           texto='Llamada (sobre el toque)', dyn='f'),
    compas({**BASE, 'surdo_medio': '-x=x=-x=x=--x=x='},
           texto='Llamada (sobre el toque) — … revisar con la escuela'),
    compas({**BASE, 'surdo_medio': 'xxxxxxxx' + TRES}),
    compas({**BASE, 'surdo_medio': 'xxxxxxxx' + TRES}),
]

UNISONO_1 = 'xxxxxxxx' + TRES
UNISONO_2 = 'x==xx===--------'   # el cuadernillo lo escribe en 2/4

final_unisono = [
    compas(unisono(UNISONO_1), texto='Final unísono de llamada', dyn='f'),
    compas(unisono(UNISONO_2), texto='(compás de 2/4 con silencios)'),
]

variacion = [
    compas({**BASE, 'timbal': '6(oooooo)6(xxxxxx)6(oooooo)6(xxxxxx)'},
           repeat_begin=True, texto='Variación de timbal (sobre el toque)'),
    compas({**BASE, 'timbal': 'x=o=-o=x=-x=x=--'}),
    compas({**BASE, 'timbal': 'oxoxo===' + TRES},
           texto='Variación de timbal — … revisar con la escuela'),
    compas({**BASE, 'timbal': 'x==o=-x=-fx=o=--'}, repeat_end=True),
]

SCORE = score(TITULO, 'La Chilinga', 86, INSTS, [
    seccion('Llamada inicial', llamada, 1),
    seccion('Toque', toque, 8),
    seccion('Llamada (sobre el toque)', llamada_sobre, 1),
    seccion('Variación de timbal', variacion, 1),
    seccion('Final unísono de llamada', final_unisono, 1),
])
