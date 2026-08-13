"""Mongo kutá — Cuadernillo (PDF pág. 40-42)."""
from dsl import INSTS, VACIO, compas, score, seccion

TITULO = 'Mongo kutá'
MATCH = {'año': 3, 'orden': 6, 'nombre': 'Mongokuta I'}

V = VACIO


def c(sg=V, sa=V, sm=V, re=V, rp=V, ti=V, **kw):
    return compas({'surdo_grave': sg, 'surdo_agudo': sa, 'surdo_medio': sm,
                   'redoblante': re, 'repique': rp, 'timbal': ti}, **kw)


# ------------------------------------------------------------------ Introducción
introduccion = [
    c(ti='x-tt-xx-x-xx-xx-', sa='x==-x=x=x=-x=x--',
      texto='Introducción', dyn='mf'),
    c(ti='xx-xxx--x-xx-xx-', sa='o===============',
      sg='----------x---x-'),
]

# ------------------------------------------------------------------------ Toque
SA = 'xx-xxx-xx-xxx-x-'
SG = 'x==-------------'
SG2 = '--x==-----------'
SM = '-x--x=--x-x-x=--'
RE = '>xxx>xxx>xxx>xxx'
RE2 = 'xx>>xx>>xx>>xx>>'
TI = 'x=t=xtxtx=t=xtxt'
RP = '-xx-----x-------'
RP2 = '-xx-x=--x=------'


def toque(texto='Toque'):
    return [
        c(sa=SA, sg=SG, sm=SM, re=RE, ti=TI, rp=RP,
          repeat_begin=True, texto=texto, dyn='mf'),
        c(sa=SA, sg=SG2, sm='-x-x=--x-x-x=x--', re=RE2, ti=TI, rp=RP2),
        c(sa=SA, sg=SG, sm=SM, re=RE, ti=TI, rp=RP),
        c(sa=SA, sg=SG2, sm='x=--x-x-x=-xx---', re=RE2, ti=TI, rp=RP2,
          repeat_end=True),
    ]


# -------------------------------------------------------------- Variación 1 (x4)
variacion_1 = [
    c(sg='x==-----x==-----', re=RE, ti='x==-x=--tttt-x--',
      repeat_begin=True, texto='Variación 1 (x4) — cresc. p → f', dyn='p'),
    c(sa=SA, sg='x==-----x==-----', re=RE, rp=RE, ti='x=tt-x=-tttt-x--'),
    c(sa=SA, sg='x==-------------', re=RE2, rp=RE2, ti='x=t=xtxtx=t=xtxt'),
    c(sa='xx-xxx-xxxxx-xx-', sm='-----------xxxx-', re='xxxxxxxxxxxxxxx-',
      rp='xxxxxxxxxxxxxxx-', ti='x=t=xtxtxxxx-xx-', repeat_end=True, dyn='f'),
]

# ------------------------------------------------------------------ Variación 2
variacion_2 = [
    c(sm='-x-t-x=-t=-x-t--', rp='-x-t-x=-t=-x-t--',
      re='xx>>xx>>x>x>' + '6(xxxxxx)',
      repeat_begin=True, texto='Variación 2 — sobre surdo medio y repique',
      dyn='mf'),
    c(sm='-x-t-x=-t=-x-tx-', rp='-x-t-x=-t=-x-tx-', re=RE, repeat_end=True),
]

# ------------------------------------------- Llamada sobre variación 2 (x2)
llamada_var2 = [
    c(sg='o===============', sa='o===============',
      texto='Llamada sobre variación 2 (x2)', dyn='f'),
    c(sg='o===============', sa='o==============='),
    c(sg='o===============', sa='o==============='),
    c(sg='x==-x==-x==-x=x-', sa='x==-x==-x==-x=x-'),
]

# ---------------------------------------------------------------------- Llamada
llamada = [
    c(ti='x==-x=-x=x=x=x--',
      texto='Llamada — timbal sobre la 2da vuelta de surdos grave y agudo',
      dyn='f'),
    c(ti='xxx-x==-xx-xx=x-'),
    c(rp=RE2, texto='Repique (después de surdos grave y agudo)'),
    c(rp=RE2),
]

SCORE = score(TITULO, 'La Chilinga', 84, INSTS, [
    seccion('Introducción', introduccion, 1),
    seccion('Toque', toque(), 4),
    seccion('Variación 1', variacion_1, 4),
    seccion('Variación 2', variacion_2, 4),
    seccion('Llamada sobre variación 2', llamada_var2, 2),
    seccion('Llamada', llamada, 1),
    seccion('Toque (vuelve)', toque('Toque (vuelve)'), 4),
])
