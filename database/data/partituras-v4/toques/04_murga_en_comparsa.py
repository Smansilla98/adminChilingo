"""Murga en Comparsa — Cuadernillo pág. 8 (PDF pág. 11).

Transcripción literal del PDF (figuras = escritura de la escuela).
"""
from dsl import INSTS, SURDOS, VACIO, compas, score, seccion, tutti

TITULO = 'Murga en Comparsa'
MATCH = {'año': 1, 'orden': 10, 'nombre': 'Murga en Comparsa'}
PDF_PAGES = [11]

# Toque
# Surdos: (corchea c/punto + semi · 2 corcheas) × 2
# Redo/Repi: (corchea c/punto acentuada + semi acentuada) × 4
# Timbal: (sil. corchea + 2 semis) × 4; rombo=palma(p) / lleno=nota(x)
SU_TOQUE = 'x==xx=x=x==xx=x='
RR_TOQUE = '>==x>==x>==x>==x'
TB_TOQUE = '--pp--xx--pp--xx'

# Llamada: surdos m1 termina en 2 corcheas; m2 en negra
# HI: sil. 3 negras · sil. corchea + corchea
LL_SU_1 = 'x==xx=--x=--x=x='
LL_SU_2 = 'x==xx=--x=--x==='
LL_RR = '--------------x='

llamada = [
    compas({**tutti(LL_SU_1, SURDOS), 'redoblante': LL_RR, 'repique': LL_RR,
            'timbal': LL_RR}, repeat_begin=True,
           texto='Llamada inicial e intermedia', dyn='f'),
    compas({**tutti(LL_SU_2, SURDOS), 'redoblante': LL_RR, 'repique': LL_RR,
            'timbal': LL_RR}, repeat_end=True),
]

TOQUE = {**tutti(SU_TOQUE, SURDOS), 'redoblante': RR_TOQUE,
         'repique': RR_TOQUE, 'timbal': TB_TOQUE}

toque = [compas(TOQUE, repeat_begin=True, repeat_end=True, texto='Toque', dyn='mf')]

# Variación: surdo medio y repique; el resto sostiene el toque
VAR_SM = [
    'x===----------x=',   # negra · 2 sil. · sil.corchea + corchea
    'x===----x===x===',   # negra · sil. · 2 negras
    'x===----------x=',
    'x===------------',   # negra · sil. · sil. blanca
]
VAR_RP = [
    '----x===----x===',   # (sil. negra + negra) × 2
    '----x===----x===',
    '----x===----x===',
    '----x===6(xxxxxx)6(xxxxxx)',  # sil. + negra · 2 sextillos
]

variacion = [
    compas({**TOQUE, 'surdo_medio': VAR_SM[i], 'repique': VAR_RP[i]},
           repeat_begin=(i == 0), repeat_end=(i == 3),
           texto='Variación sobre toque' if i == 0 else None)
    for i in range(4)
]

SCORE = score(TITULO, 'La Chilinga', 88, INSTS, [
    seccion('Llamada inicial e intermedia', llamada, 1),
    seccion('Toque', toque, 8),
    seccion('Variación sobre toque', variacion, 2),
])
