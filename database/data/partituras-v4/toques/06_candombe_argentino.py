"""Candombe Argentino — Cuadernillo (PDF pág. 14)."""
from dsl import INSTS, SURDOS, VACIO, compas, score, seccion, tutti

TITULO = 'Candombe Argentino'
MATCH = {'año': 2, 'orden': 2, 'nombre': 'Candombe Argentino'}

LL_RR = '-x==x==x-x==x==='
LL_SU = '---x--x=---x--x='

llamada = [
    compas({**tutti(LL_SU, SURDOS), 'redoblante': LL_RR, 'repique': LL_RR,
            'timbal': LL_RR}, texto='Llamada', dyn='f'),
]

toque = [
    compas({
        'surdo_grave': 'x==x--x=--x=x=--',
        'surdo_agudo': 'x==x--x=--x=x=--',
        'surdo_medio': 'x==x--x=--xxxxxx',
        'redoblante': '>xxx>xx>xxx>xx>x',
        'repique': '>x-xx-x=>x-xx-x=',
        'timbal': 'o==so=sso==so=ss',
    }, repeat_begin=True, repeat_end=True, texto='Toque', dyn='mf'),
]

llamada_final = [
    compas({**tutti(LL_SU, SURDOS), 'redoblante': LL_RR, 'repique': LL_RR,
            'timbal': LL_RR}, texto='Llamada final', dyn='f'),
    compas(tutti('x=x=------------')),
]

SCORE = score(TITULO, 'La Chilinga', 108, INSTS, [
    seccion('Llamada', llamada, 1),
    seccion('Toque', toque, 8),
    seccion('Llamada final', llamada_final, 1),
])
