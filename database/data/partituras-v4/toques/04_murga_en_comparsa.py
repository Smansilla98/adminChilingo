"""Murga en Comparsa — Cuadernillo (PDF pág. 11)."""
from dsl import INSTS, SURDOS, VACIO, compas, score, seccion, tutti

TITULO = 'Murga en Comparsa'
MATCH = {'año': 1, 'orden': 10, 'nombre': 'Murga en Comparsa'}

SU_TOQUE = 'x==xx=x=x==xx=x='
RR_TOQUE = '>===x=>=>===x=>='
TB_TOQUE = '--oo--ss--oo--ss'

LL_SU = 'x==x--x=-x==x==='
LL_RR = '--------------x='

llamada = [
    compas({**tutti(LL_SU, SURDOS), 'redoblante': LL_RR, 'repique': LL_RR,
            'timbal': LL_RR}, repeat_begin=True,
           texto='Llamada inicial e intermedia', dyn='f'),
    compas({**tutti(LL_SU, SURDOS), 'redoblante': LL_RR, 'repique': LL_RR,
            'timbal': LL_RR}, repeat_end=True),
]

TOQUE = {**tutti(SU_TOQUE, SURDOS), 'redoblante': RR_TOQUE,
         'repique': RR_TOQUE, 'timbal': TB_TOQUE}

toque = [compas(TOQUE, repeat_begin=True, repeat_end=True, texto='Toque', dyn='mf')]

# --- Variación sobre toque: surdo medio y repique varían, el resto sostiene el toque
VAR_SM = ['x===----------x=', 'x===----x===x===', 'x===----------x=',
          'x===------------']
VAR_RP = ['----x===----x===', '----x===----x===', '----x===----x===',
          '----x===6:2(xxxxxx)--6:2(xxxxxx)--']

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
