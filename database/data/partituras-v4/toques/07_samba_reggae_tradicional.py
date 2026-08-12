"""Samba Reggae Tradicional — Cuadernillo (PDF pág. 15-16)."""
from dsl import INSTS, SURDOS, VACIO, compas, score, seccion, tutti

TITULO = 'Samba Reggae Tradicional'
MATCH = {'año': 1, 'orden': 6, 'nombre': 'Samba Reggae I y II'}

TOQUE_SG = 'x===----x===----'
TOQUE_SA = '----x===----x==='
TOQUE_SM = '------x=----xxxx'
TOQUE_RE = 'xx>>xx>>xx>>xx>>'
TOQUE_RP = '>xx>xx>xx>xx>xx>'
TOQUE_TI = 'o=ppf=p=o=ppxxxx'

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

# Llamadas: van "sobre toque" (repique llama, surdos entran al final)
LL_RP_1 = 'x==x-x=-xxxxx==='
LL_RP_3 = '---------x=x--x='
LL_SU_1 = '--------------x='
LL_SU_2 = 'x===--x=x===--x='
ROLL = 'xxxxxxxxxxxxxxxx'

llamada_1 = [
    compas({'repique': LL_RP_1, **tutti(VACIO, SURDOS),
            'redoblante': VACIO, 'timbal': VACIO},
           texto='Llamada 1 (sobre toque)', dyn='f'),
    compas({'repique': '--x===x===--x===x===--xxxxx=x===',
            **tutti(VACIO * 2, SURDOS),
            'redoblante': VACIO * 2, 'timbal': VACIO * 2}, grid=32),
    compas({'repique': LL_RP_3, **tutti(ROLL, SURDOS),
            'redoblante': VACIO, 'timbal': VACIO}, dyn='p'),
]

llamada_2 = [
    compas({'repique': LL_RP_1, **tutti(VACIO, SURDOS),
            'redoblante': VACIO, 'timbal': VACIO},
           texto='Llamada 2 (sobre toque)', dyn='f'),
    compas({'repique': '-xxxxx==-xxxxx==', 'surdo_grave': LL_SU_2,
            'surdo_agudo': LL_SU_2, 'surdo_medio': LL_SU_2,
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
           texto='Llamada final (sobre toque)', dyn='f'),
    compas({'repique': VACIO, **tutti('x===============', SURDOS),
            'redoblante': VACIO, 'timbal': VACIO}),
]

SCORE = score(TITULO, 'La Chilinga', 100, INSTS, [
    seccion('Toque', toque, 4),
    seccion('Llamada 1 (sobre toque)', llamada_1, 1),
    seccion('Llamada 2 (sobre toque)', llamada_2, 1),
    seccion('Variación de surdo medio', variacion, 2),
    seccion('Llamada final (sobre toque)', llamada_final, 1),
])
