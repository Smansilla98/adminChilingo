"""Samba Reggae Contemporáneo — Cuadernillo pág. 14 (PDF pág. 17).

Transcripción literal del PDF (figuras = escritura de la escuela).
"""
from dsl import INSTS, SURDOS, VACIO, compas, score, seccion, tutti, unisono

TITULO = 'Samba Reggae Contemporáneo'
MATCH = {'año': 1, 'orden': 11, 'nombre': 'Samba Reggae Contemporáneo'}
PDF_PAGES = [17]

# Llamada "Todos": 2 corcheas · sil.corchea + corchea · negra · 2 corcheas
LLAMADA = 'x=x=--x=x===x=x='

# Toque
# Grave: negras 1 y 3 · Agudo: negras 2 y 4
# Medio: (sil. negra · sil.corchea + 2 semis) × 2
# Redo/Repi: 16 semis; acentos en 1,5,9,10,13,15
# Timbal: (sil.corchea + 2 semis) × 4; abierto/nota alternados
BASE = {
    'surdo_grave': 'x===----x===----',
    'surdo_agudo': '----x===----x===',
    'surdo_medio': '------xx------xx',
    'redoblante': '>xxx>xxx>>xx>x>x',
    'repique': '>xxx>xxx>>xx>x>x',
    'timbal': '--oo--xx--oo--xx',
}


def llamada(texto='Llamada inicial, intermedia y final'):
    """Voz "Todos": unísono estricto."""
    return [compas(unisono(LLAMADA), texto=texto, dyn='f')]


toque = [
    compas(dict(BASE), repeat_begin=True, repeat_end=True,
           texto='Toque', dyn='mf'),
]

# Variación surdo medio: m1 = base; m2 cierra con corchea + 2 semis
variacion = [
    compas({**BASE, 'surdo_medio': '------xx------xx'},
           repeat_begin=True, texto='Variación de surdo medio'),
    compas({**BASE, 'surdo_medio': '------xx----x=xx'}, repeat_end=True),
]

SCORE = score(TITULO, 'La Chilinga', 86, INSTS, [
    seccion('Llamada inicial', llamada(), 1),
    seccion('Toque', toque, 8),
    seccion('Variación de surdo medio', variacion, 2),
    seccion('Llamada final', llamada('Llamada final'), 1),
])
