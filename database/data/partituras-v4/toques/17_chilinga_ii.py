"""Chilinga II — Cuadernillo pág. 33 (PDF pág. 36).

Transcripción literal del PDF: semis de a 4 (grilla 16), no fusas inventadas.
Golpes abajo/arriba de la línea y acentos finos: revisar con la escuela.
"""
from dsl import INSTS, VACIO, compas, score, seccion

TITULO = 'Chilinga II'
MATCH = {'año': 5, 'orden': 2, 'nombre': 'Chilinga II'}
PDF_PAGES = [36]

# Surdos: 2 abajo (grave) + 2 en línea (medio) por tiempo
SG = 'xx--xx--xx--xx--'
SM = '--xx--xx--xx--xx'
# Redoblante: 16 semis
RE = 'xxxxxxxxxxxxxxxx'
# Timbal: (2 semis + corchea) × 4 — cabezas mixtas ≈ nota/abierto
TI = 'xxo=xxo=xxo=xxo='
# Repique: triángulo = agudo
RP = 'aaxxaaxxaaxxaaxx'

# Cierre: ostinato + corchea c/punto + semi en grave
SG_END = 'xx--xx--xx--x==x'
SM_END = '--xx--xx--xx----'
RE_END = 'xxxxxxxxxxxx' + 'x==x'
TI_END = 'xxo=xxo=x-o=x-o='
RP_END = 'aaxxaaxxa-x=a-x='

toque = [
    compas({'surdo_grave': SG, 'surdo_agudo': VACIO, 'surdo_medio': SM,
            'redoblante': RE, 'timbal': TI, 'repique': RP},
           repeat_begin=True, ending=1,
           texto='Toque', dyn='mf'),
    compas({'surdo_grave': SG, 'surdo_agudo': VACIO, 'surdo_medio': SM,
            'redoblante': RE, 'timbal': TI, 'repique': RP},
           ending=2),
    compas({'surdo_grave': SG, 'surdo_agudo': VACIO, 'surdo_medio': SM,
            'redoblante': RE, 'timbal': TI, 'repique': RP}),
    compas({'surdo_grave': SG, 'surdo_agudo': VACIO, 'surdo_medio': SM,
            'redoblante': RE, 'timbal': TI, 'repique': RP}),
    compas({'surdo_grave': SG_END, 'surdo_agudo': VACIO, 'surdo_medio': SM_END,
            'redoblante': RE_END, 'timbal': TI_END, 'repique': RP_END},
           repeat_end=True,
           texto='Cierre'),
]

SCORE = score(TITULO, 'La Chilinga', 88, INSTS, [
    seccion('Toque', toque, 4),
])
