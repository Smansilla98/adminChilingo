"""Chilinga II — Cuadernillo pág. 33 (PDF pág. 36).

Transcripción literal del PDF: semis de a 4 (grilla 16), no fusas inventadas.
Golpes abajo/arriba de la línea y acentos finos: revisar con la escuela.
"""
from dsl import INSTS, compas, score, seccion

TITULO = 'Chilinga II'
MATCH = {'año': 5, 'orden': 2, 'nombre': 'Chilinga II'}
PDF_PAGES = [36]

# Surdos: 16 semis (2 abajo + 2 en línea por tiempo ≈ tapado/nota)
SU = 'ttxxttxxttxxttxx'
# Redoblante: 16 semis
RE = 'xxxxxxxxxxxxxxxx'
# Timbal: (2 semis + corchea) × 4 — cabezas mixtas ≈ nota/abierto
TI = 'xxo=xxo=xxo=xxo='
# Repique: (2 tapado + 2 nota) × 4
RP = 'ttxxttxxttxxttxx'

# Cierre del último compás: 12 semis + corchea c/punto + semi
SU_END = 'ttxxttxxttxxx==x'
RE_END = 'xxxxxxxxxxxx' + 'x==x'
TI_END = 'xxo=xxo=x-o=x-o='
RP_END = 'ttxxttxxt-x=t-x='

toque = [
    compas({'surdo_grave': SU, 'surdo_agudo': SU, 'surdo_medio': SU,
            'redoblante': RE, 'timbal': TI, 'repique': RP},
           repeat_begin=True, ending=1,
           texto='Toque — … revisar con la escuela (cabezas/acentos)', dyn='mf'),
    compas({'surdo_grave': SU, 'surdo_agudo': SU, 'surdo_medio': SU,
            'redoblante': RE, 'timbal': TI, 'repique': RP},
           ending=2),
    compas({'surdo_grave': SU, 'surdo_agudo': SU, 'surdo_medio': SU,
            'redoblante': RE, 'timbal': TI, 'repique': RP}),
    compas({'surdo_grave': SU, 'surdo_agudo': SU, 'surdo_medio': SU,
            'redoblante': RE, 'timbal': TI, 'repique': RP}),
    compas({'surdo_grave': SU_END, 'surdo_agudo': SU_END, 'surdo_medio': SU_END,
            'redoblante': RE_END, 'timbal': TI_END, 'repique': RP_END},
           repeat_end=True,
           texto='Cierre — … revisar con la escuela'),
]

SCORE = score(TITULO, 'La Chilinga', 88, INSTS, [
    seccion('Toque', toque, 4),
])
