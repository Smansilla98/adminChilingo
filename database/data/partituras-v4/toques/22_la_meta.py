"""La Meta — Cuadernillo (PDF pág. 43-49).

Recopilación: Luciano Molina - Pablo Cuffia (Bloque Lunes Saavedra).
Las llamadas y cortes están escritos con sextillos muy densos: se transcribe
la lectura más probable, aproximando los pasajes ilegibles del escaneo.
"""
from dsl import INSTS, VACIO, compas, score, seccion

TITULO = 'La Meta'
MATCH = {'año': 4, 'orden': 2, 'nombre': 'La Meta'}

V = VACIO


def c(sg=V, sa=V, sm=V, re=V, rp=V, ti=V, **kw):
    return compas({'surdo_grave': sg, 'surdo_agudo': sa, 'surdo_medio': sm,
                   'redoblante': re, 'repique': rp, 'timbal': ti}, **kw)


# ------------------------------------------------------------- Introducción 1
MARCHA = '--t---t---t---t-'

introduccion_1 = [
    c(ti='6(x-x-xo)6(x-x-xo)6(xxxx-x)6(o-x-x-)',
      sg=MARCHA, sa=MARCHA, sm=MARCHA, re=MARCHA, rp=MARCHA,
      texto='Introducción 1 — timbal en sextillos, resto marcando', dyn='mf'),
    c(ti='6(x-xx-x)6(x-x-xo)6(xxxxxx)6(x-x-x-)',
      sg=MARCHA, sa=MARCHA, sm=MARCHA, re=MARCHA, rp=MARCHA),
]

# --------------------------------------------------- Llamada (sobre el timbal)
llamada_timbal = [
    c(rp='6(-x-x-x)6(-xx-xx)6(xx-xxx)6(x-xx-x)',
      sm='--x---x-----6(xxx-x-)',
      sg=MARCHA, sa=MARCHA,
      texto='Llamada (sobre timbal)', dyn='f'),
    c(rp='6(-x-x-x)6(x-xx-x)6(xxx-xx)6(x-x---)',
      sm='--x-6(xxx---)--x---x-',
      sg=MARCHA, sa=MARCHA),
    c(rp='--x---x---x---x-', sm='--x---x---x---x-',
      sg='--x---x---x---x-', sa='--x---x---x---x-'),
    c(rp='--x---x---x---x-', sm='--x---x---x---x-',
      sg='x---x---x---x---', sa='x---x---x---x---'),
]

# ------------------------------------------------------------------ Corte
corte_1 = [
    c(sg='6(xxxxxx)6(xxxxxx)6(xxxxxx)6(xxxxxx)',
      sa='6(xxxxxx)6(xxxxxx)6(xxxxxx)6(xxxxxx)',
      sm='6(xxxxxx)6(xxxxxx)6(xxxxxx)6(xxxxxx)',
      re='6(xxxxxx)6(xxxxxx)6(xxxxxx)6(xxxxxx)',
      rp='6(xxxxxx)6(xxxxxx)6(xxxxxx)6(xxxxxx)',
      ti='6(x-oxox)6(xoxox-)6(x-oxox)6(xoxox-)',
      texto='Corte — todos en sextillos', dyn='f'),
    c(sg='6(xxxxxx)6(xxxxxx)6(xxxxxx)x---',
      sa='6(xxxxxx)6(xxxxxx)6(xxxxxx)x---',
      sm='6(xxxxxx)6(xxxxxx)6(xxxxxx)x---',
      re='6(xxxxxx)6(xxxxxx)6(xxxxxx)x---',
      rp='6(xxxxxx)6(xxxxxx)6(xxxxxx)x---',
      ti='6(x-oxox)6(xoxox-)6(oxoxox)x---'),
]

# ------------------------------------------------------------- Introducción 2
introduccion_2 = [
    c(ti='6(xx-xx-)6(xx-xx-)6(xx-xx-)6(xx-xx-)',
      re='-x--x-x--x--x-x-',
      sg='-t-t-t-t-t-t-t-t', sm='-t-t-t-t-t-t-t-t',
      sa='-x--x---x--x---x', rp='-x--x-x--x--x-x-',
      texto='Introducción 2', dyn='mf'),
    c(ti='6(xx-xx-)6(xx-xx-)6(xx-xx-)6(xx-xx-)',
      re='xxxx>xxxxxxx>xxx',
      sg='-t-t-t-t-t-t-t-t', sm='-t-t-t-t-t-t-t-t',
      sa='-x--x---x--x---x', rp='-x--x-x--x--x-x-'),
    c(ti='----x=x-x=--x=x-',
      re='xxxx>xxxxxxx>>>>',
      sg='x==-x=--------x=', sm='x==-x=--------x=',
      sa='x=--x---x---x---', rp='x=--x---x---x=x-'),
    c(ti='--x=x---x=x-x=--',
      re='xxxx>xxxxxxx>>>>',
      sg='x==-x=--x=------', sm='x==-x=--x=------',
      sa='x=--x---x---x---', rp='x=--x---x---x=x-'),
]

corte_2 = [
    c(ti='6(x-xoxo)6(x-xoxo)6(xoxox-)6(x-xox-)',
      rp='-x=-x=--x=-x-x--',
      texto='Corte', dyn='f'),
    c(ti='6(x-xoxo)6(xoxox-)6(x-xoxo)6(xoxox-)',
      rp='-x=-x=--x-x-x=x-'),
]

# -------------------------------------------------------- Llamada (p → f)
llamada = [
    c(sg='3(xxx)3(xxx)3(xxx)3(xxx)',
      sa='3(xxx)3(xxx)3(xxx)3(xxx)',
      sm='3(xxx)3(xxx)3(xxx)3(xxx)',
      texto='Llamada — cresc. p → f', dyn='p'),
    c(sg='x=x---x=--------', sa='x=x---x=--------',
      sm='x=x---x=--------', dyn='f'),
    c(rp='--x=--x---x=----', ti='--x=--x---x-x=--',
      re='--x=--x---x-x=--', texto='Repique, timbal y redoblante'),
    c(ti='6(xxxxxx)6(xxxxxx)6(xxxxxx)6(xxxxxx)',
      re='6(tttttt)6(tttttt)6(tttttt)6(tttttt)', dyn='f'),
]

# ------------------------------------------------------------------- Toque 1
T1_SGA = 'tt-t----x-------'
T1_SM = 'tt-t--------x---'
T1_RE = '>>>>xxxx>xxx>xxx'
T1_TI = '-x---x=--x---x=-'
T1_RP = 'x-------x---x=--'


def toque_1(texto='Toque 1'):
    return [
        c(sg=T1_SGA, sa=T1_SGA, sm=T1_SM, re=T1_RE, ti=T1_TI, rp=T1_RP,
          repeat_begin=True, texto=texto, dyn='mf'),
        c(sg='--------tt-t--x-', sa='--------tt-t--x-',
          sm='--------tt-t--x-', re='>>>>xxxx>xxx>xxx',
          ti='-x---x=--x-x-x--', rp='x-------x-x-x=--',
          repeat_end=True),
    ]


# ------------------------------------------ Variación (cada 2 vueltas)
variacion_1 = [
    c(ti='-f-x----x=x---x-',
      sg='----tt-t--x-----', sa='----tt-t--x-----',
      sm='----tttt--x-----',
      re='tttt>xxx>xxxtttt', rp='--x---x=--x---x-',
      repeat_begin=True, texto='Variación (cada 2 vueltas)', dyn='mf'),
    c(ti='---x----x=--x-x-',
      sg='----tt-t--x-----', sa='----tt-t--x-----',
      sm='----tttt--x=----',
      re='tttt>xxx>xxx>xxx', rp='--x---x=--x---x-',
      repeat_end=True),
]

# --------------------------------------------------------- Llamada intermedia
llamada_intermedia = [
    c(sg='tttt----tttt----', sa='tttt----tttt----',
      sm='ttttxxxxttttxxxx', re='tttt----tttt----',
      rp='tttt----tttt----',
      texto='Llamada intermedia', dyn='f'),
    c(sg='tttt----3(xxx)----', sa='tttt----3(xxx)----',
      sm='tttt--x-3(xxx)----', re='tttt----3(xxx)----',
      rp='tttt----3(xxx)----'),
    c(sg='3(xxx)----tt-t----', sa='3(xxx)----tt-t----',
      sm='3(xxx)----tt-t----', re='3(xxx)----x---x---',
      rp='3(xxx)----tt-t----',
      ti='--x-x=--x=--x=x-'),
    c(ti='-x-x=---x=x-x=x-'),
]

# ------------------------------------------------------------------ Variación
variacion_2 = [
    c(sg='tt-t----x-------', sa='tt-t----x-------',
      sm='tt-t----x-------',
      re='>>>>xxxx>xxx>xxx', ti='--x=--x---x=----',
      rp='x-----x=--x-----',
      repeat_begin=True, texto='Variación', dyn='mf'),
    c(sg='tt-t--------x---', sa='tt-t--------x---',
      sm='tt-t--------x---',
      re='>>>>xxxx>xxx>xxx', ti='--x=--x---x=----',
      rp='x-----x=--x-----', repeat_end=True),
]

# ------------------------------------------------------------------- Toque 2
T2_SG = '----x=------x=--'
T2_SA = '----x=--x=--x=--'
T2_SMRP = 'tt-t----x-------'
T2_RE = '>>>>xxxx>>>>xxxx'
T2_TI = '--o=x-x-x---o=x-'


def toque_2(texto='Toque 2'):
    return [
        c(sg=T2_SG, sa=T2_SA, sm=T2_SMRP, rp=T2_SMRP, re=T2_RE, ti=T2_TI,
          repeat_begin=True, texto=texto, dyn='mf'),
        c(sg='----x=----------', sa='----x=--x=------',
          sm='tt-t----x-------', rp='tt-t----x-------',
          re='>>>>xxxx>xxx>xxx', ti='--o=x-x-x-x-x=--',
          repeat_end=True),
    ]


# ---------------------------------------------------------------- Solo repique
solo = [
    c(rp='-x-x6(xxx-xx)6(x-xxxx)x---',
      sg='tttt----------x-', sm='tttt-x--tttt-x--',
      re='6(>xxxxx)6(>xxxxx)6(>xxxxx)6(>xxxxx)',
      repeat_begin=True, texto='Solo de repique', dyn='f'),
    c(rp='6(-xxx-x)6(xxx-xx)6(x-xxxx)x---',
      sg='--------tttt----', sm='tttt-x--tttt-x--',
      re='6(>xxxxx)6(>xxxxx)6(>xxxxx)6(>xxxxx)'),
    c(rp='x-x-x-x-6(xx-xxx)x-x-',
      sm='tttt-x--tttt-x--',
      re='6(>xxxxx)6(>xxxxx)6(>xxxxx)6(>xxxxx)'),
    c(rp='6(xxxxxx)6(x-xxxx)6(xxxxxx)x---',
      sm='tttt-x----------',
      re='6(>xxxxx)6(>xxxxx)6(>xxxxx)6(>xxxxx)',
      repeat_end=True),
]

# ----------------------------------------------------------------- Solo timbal
solo_timbal = [
    c(ti='6(xoxoxo)6(xoxoxo)6(xoxoxo)6(xoxoxo)',
      repeat_begin=True, texto='Solo de timbal', dyn='f'),
    c(ti='6(xoxoxo)6(xoxoxo)6(xoxoxo)6(xoxox-)'),
    c(ti='x-x-6(xoxoxo)x-x-x---', repeat_end=True),
]

# ------------------------------------------------------- Variación (p → f)
variacion_3 = [
    c(sg='xx-x----tttt--x-', sa='xx-x----tttt--x-',
      sm='xx-x----t=-t--x-',
      re='tttt>xxx>xxx>xxx', rp='o===============',
      repeat_begin=True, texto='Variación — cresc. p → f', dyn='p'),
    c(sg='tttt----x-x-x=--', sa='tttt----x-x-x=--',
      sm='tttt----x-x-x=--',
      re='>xxx>xxx>xxx>xxx', rp='----------------',
      repeat_end=True, dyn='f'),
]

# ----------------------------------------------------------------- Base final
base_final = [
    c(sg='-x-x----tttt--x-', sa='-x-x----tttt--x-',
      sm='-x-x--x-t=-t--x-',
      re='tt-t--x->xxx>xxx', rp='-x-x----x---x=--',
      ti='-x-x----oo-x-x=-',
      repeat_begin=True, texto='Base final — cresc. p → f', dyn='p'),
    c(sg='tttt----x-x-x=--', sa='tttt----x-x-x=--',
      sm='tttt----x-x-x=--',
      re='>xxx>xxx>xxx>xxx', rp='x-----x=--x-x=--',
      ti='oo-x-x--oo-x-x=-',
      repeat_end=True, dyn='f'),
]

SCORE = score(TITULO, 'La Chilinga', 86, INSTS, [
    seccion('Introducción 1', introduccion_1, 1),
    seccion('Llamada (sobre timbal)', llamada_timbal, 1),
    seccion('Corte', corte_1, 1),
    seccion('Introducción 2', introduccion_2, 1),
    seccion('Corte 2', corte_2, 1),
    seccion('Llamada', llamada, 1),
    seccion('Toque 1', toque_1(), 4),
    seccion('Variación (cada 2 vueltas)', variacion_1, 2),
    seccion('Llamada intermedia', llamada_intermedia, 1),
    seccion('Variación', variacion_2, 2),
    seccion('Toque 2', toque_2(), 4),
    seccion('Solo de repique', solo, 2),
    seccion('Solo de timbal', solo_timbal, 2),
    seccion('Variación final', variacion_3, 2),
    seccion('Base final', base_final, 4),
])
