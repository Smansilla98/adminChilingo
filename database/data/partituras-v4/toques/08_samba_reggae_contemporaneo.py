"""Samba Reggae Contemporáneo — Cuadernillo (PDF pág. 17)."""
from dsl import INSTS, SURDOS, VACIO, compas, score, seccion, tutti

TITULO = 'Samba Reggae Contemporáneo'
MATCH = {'año': 1, 'orden': 11, 'nombre': 'Samba Reggae Contemporáneo'}

LLAMADA = 'x=x=--x=x===x=x='

BASE = {
    'surdo_grave': 'x===----x===----',
    'surdo_agudo': '----x===----x===',
    'surdo_medio': '------xx------xx',
    'redoblante': '>xx>xx>x>xx>xx>x',
    'repique': 'xxx>xx>x>xx>xx>x',
    'timbal': '--oo--xx--oo--xx',
}

llamada = [
    compas(tutti(LLAMADA), texto='Llamada inicial, intermedia y final', dyn='f'),
]

toque = [
    compas(dict(BASE), repeat_begin=True, repeat_end=True,
           texto='Toque', dyn='mf'),
]

variacion = [
    compas({**BASE, 'surdo_medio': '------xx------xx'},
           repeat_begin=True, texto='Variación de surdo medio'),
    compas({**BASE, 'surdo_medio': '------xx---fx=xx'}, repeat_end=True),
]

SCORE = score(TITULO, 'La Chilinga', 100, INSTS, [
    seccion('Llamada inicial', llamada, 1),
    seccion('Toque', toque, 8),
    seccion('Variación de surdo medio', variacion, 2),
    seccion('Llamada final', llamada, 1),
])
