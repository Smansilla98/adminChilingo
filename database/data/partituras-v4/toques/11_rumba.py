"""Rumba — Cuadernillo (PDF pág. 24-25)."""
from dsl import INSTS, SURDOS, VACIO, compas, score, seccion, tutti, unisono

TITULO = 'Rumba'
MATCH = {'año': 2, 'orden': 7, 'nombre': 'Ritmo de Rumba'}

V = VACIO

LLAMADA = 'x==x---fx=-x=x=='

BASE = {
    'surdo_grave': 'x==x------------',
    'surdo_agudo': 'x=x=-x=x=x=x=x=-',
    'surdo_medio': '--------x==xx===',
    'redoblante': '>xx>xxx>xx>x>xxx',   # acentos sobre clave de rumba
    'repique': 'x=x=-x=x=x=-x=x=',
    'timbal': 'x=o--x=t=t--o=xx',
}

llamada = [compas(unisono(LLAMADA), texto='Llamada inicial y final', dyn='f')]

toque = [compas(dict(BASE), repeat_begin=True, repeat_end=True,
                texto='Toque', dyn='mf')]

TRES = '3(xxx)3(xxx)'

llamada_sobre = [
    compas({**BASE, 'surdo_medio': 'x==x---fx=x===--'},
           texto='Llamada (sobre el toque)', dyn='f'),
    compas({**BASE, 'surdo_medio': '-xxx-xxx-xxx-xx-'}),
    compas({**BASE, 'surdo_medio': 'xxxxxxxx' + TRES}),
    compas({**BASE, 'surdo_medio': 'xxxxxxxx' + TRES}),
]

UNISONO_1 = 'xxxxxxxx' + TRES
UNISONO_2 = 'x==xx===--------'   # el cuadernillo lo escribe en 2/4

# Voz "Todos": unísono estricto en un solo pentagrama.
final_unisono = [
    compas(unisono(UNISONO_1), texto='Final unísono de llamada', dyn='f'),
    compas(unisono(UNISONO_2), texto='(compás de 2/4 con silencios)'),
]

variacion = [
    compas({**BASE, 'timbal': '6(oooooo)6(xxxxxx)6(oooooo)6(xxxxxx)'},
           repeat_begin=True, texto='Variación de timbal (sobre el toque)'),
    compas({**BASE, 'timbal': 'x=o=-o=x=-x=x=--'}),
    compas({**BASE, 'timbal': 'oxox-o==' + TRES}),
    compas({**BASE, 'timbal': 'x==o=-x=-fx=o=--'}, repeat_end=True),
]

SCORE = score(TITULO, 'La Chilinga', 86, INSTS, [
    seccion('Llamada inicial', llamada, 1),
    seccion('Toque', toque, 8),
    seccion('Llamada (sobre el toque)', llamada_sobre, 1),
    seccion('Variación de timbal', variacion, 1),
    seccion('Final unísono de llamada', final_unisono, 1),
])
