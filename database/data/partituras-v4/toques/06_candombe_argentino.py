"""Candombe Argentino — Cuadernillo pág. 11 (PDF pág. 14).

Transcripción literal del PDF (figuras = escritura de la escuela).
"""
from dsl import INSTS, SURDOS, VACIO, compas, score, seccion, tutti, unisono

TITULO = 'Candombe Argentino'
MATCH = {'año': 2, 'orden': 2, 'nombre': 'Candombe Argentino'}
PDF_PAGES = [14]

# Llamada
# HI: sil.corchea · corchea c/punto + semi · sil.corchea · corchea c/punto + semi · negra
# Surdos: (sil. corchea c/punto + semi · sil.corchea + corchea) × 2
LL_RR = '--x==x--x==xx==='
LL_SU = '---x--x=---x--x='

llamada = [
    compas({**tutti(LL_SU, SURDOS), 'redoblante': LL_RR, 'repique': LL_RR,
            'timbal': LL_RR}, texto='Llamada', dyn='f'),
]

# Toque
# Grave/Agudo: corchea c/punto + semi · (corchea) × 6
# Medio: (corchea c/punto + semi · 2 corcheas) × 2
# Redo: 16 semis, acento en 1ª de cada tiempo (+ extra en 14ª)
# Repi/Timbal: figuras densas → revisar cabezas/acentos con la escuela
toque = [
    compas({
        'surdo_grave': 'x==xx=x=x=x=x=x=',
        'surdo_agudo': 'x==xx=x=x=x=x=x=',
        'surdo_medio': 'x==xx=x=x==xx=x=',
        'redoblante': '>xxx>xxx>xxx>>xx',
        'repique': '>x=xx=>x=xx=>x=x',
        'timbal': 'o==so=sso==so=ss',
    }, repeat_begin=True, repeat_end=True,
       texto='Toque — … revisar con la escuela (repi/timbal)', dyn='mf'),
]

llamada_final = [
    compas({**tutti(LL_SU, SURDOS), 'redoblante': LL_RR, 'repique': LL_RR,
            'timbal': LL_RR}, texto='Llamada final', dyn='f'),
    compas(unisono('x=x=------------')),
]

SCORE = score(TITULO, 'La Chilinga', 88, INSTS, [
    seccion('Llamada', llamada, 1),
    seccion('Toque', toque, 8),
    seccion('Llamada final', llamada_final, 1),
])
