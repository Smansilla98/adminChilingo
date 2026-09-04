"""Samba Reggae Tradicional — Cuadernillo págs. 12-13 (PDF págs. 15-16).

Transcripción literal del PDF (figuras = escritura de la escuela).
"""
from dsl import INSTS, SURDOS, VACIO, compas, score, seccion, tutti

TITULO = 'Samba Reggae Tradicional'
MATCH = {'año': 1, 'orden': 6, 'nombre': 'Samba Reggae I y II'}
PDF_PAGES = [15, 16]

# Toque
TOQUE_SG = 'x===----x===----'
TOQUE_SA = '----x===----x==='
TOQUE_SM = '------x=----xxxx'          # sil.negra · sil.corchea+corchea · sil.negra · 4 semis
TOQUE_RE = 'xx>>xx>>xx>>xx>>'          # 16 semis; acento en 3ª y 4ª de cada tiempo
TOQUE_RP = '>xx>xx>xx>xx>xx>'          # 16 semis; acento cada 3 (ternario)
TOQUE_TI = 'xpp-xxxxxpp-xxxx'          # (nota + 2 palmas + sil.semi + 4 semis) × 2

toque = [
    compas({
        'surdo_grave': TOQUE_SG, 'surdo_agudo': TOQUE_SA,
        'surdo_medio': TOQUE_SM, 'redoblante': TOQUE_RE,
        'repique': TOQUE_RP, 'timbal': TOQUE_TI,
    }, repeat_begin=True, texto='Toque', dyn='mf'),
    compas({
        'surdo_grave': TOQUE_SG, 'surdo_agudo': TOQUE_SA,
        'surdo_medio': TOQUE_SM, 'redoblante': TOQUE_RE,
        'repique': TOQUE_RP, 'timbal': TOQUE_TI,
    }, repeat_end=True),
]

# Llamadas "sobre toque": solo repique llama; surdos entran al final
# Figuras densas del escaneo → revisar frases exactas con la escuela
LL_RP_1 = 'x==x--x=--xxxx--'
LL_RP_2A = '--xxxxxxxxxxxx--'
LL_RP_3 = '----------------'
LL_SU_ENTRA = '--------------x='
LL_SU_ROLL = '----xxxxxxxxxxxx'
ROLL = 'xxxxxxxxxxxxxxxx'

llamada_1 = [
    compas({'repique': LL_RP_1, **tutti(VACIO, SURDOS),
            'redoblante': VACIO, 'timbal': VACIO},
           texto='Llamada 1 (sobre toque) — … revisar con la escuela', dyn='f'),
    compas({'repique': LL_RP_2A, **tutti(LL_SU_ENTRA, SURDOS),
            'redoblante': VACIO, 'timbal': VACIO}),
    compas({'repique': LL_RP_3, **tutti(LL_SU_ROLL, SURDOS),
            'redoblante': VACIO, 'timbal': VACIO}, dyn='p'),
]

llamada_2 = [
    compas({'repique': LL_RP_1, **tutti(VACIO, SURDOS),
            'redoblante': VACIO, 'timbal': VACIO},
           texto='Llamada 2 (sobre toque) — … revisar con la escuela', dyn='f'),
    compas({'repique': '-xxxxx==-xxxxx==',
            'surdo_grave': 'x===--x=x===--x=',
            'surdo_agudo': 'x===--x=x===--x=',
            'surdo_medio': 'x===--x=x===--x=',
            'redoblante': VACIO, 'timbal': VACIO}),
    compas({'repique': LL_RP_3, **tutti(ROLL, SURDOS),
            'redoblante': VACIO, 'timbal': VACIO}, dyn='p'),
]

variacion = [
    compas({'surdo_medio': '----xxxx----xxxx', 'surdo_grave': TOQUE_SG,
            'surdo_agudo': TOQUE_SA, 'redoblante': TOQUE_RE,
            'repique': TOQUE_RP, 'timbal': TOQUE_TI},
           repeat_begin=True, repeat_end=True,
           texto='Variación de surdo medio'),
]

llamada_final = [
    compas({'repique': LL_RP_1, **tutti(VACIO, SURDOS),
            'redoblante': VACIO, 'timbal': VACIO},
           texto='Llamada final (sobre toque) — … revisar con la escuela', dyn='f'),
    compas({'repique': VACIO, **tutti('x===============', SURDOS),
            'redoblante': VACIO, 'timbal': VACIO}),
]

SCORE = score(TITULO, 'La Chilinga', 86, INSTS, [
    seccion('Toque', toque, 4),
    seccion('Llamada 1 (sobre toque)', llamada_1, 1),
    seccion('Llamada 2 (sobre toque)', llamada_2, 1),
    seccion('Variación de surdo medio', variacion, 2),
    seccion('Llamada final (sobre toque)', llamada_final, 1),
])
