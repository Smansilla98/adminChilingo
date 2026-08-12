"""Muñequitos I — Cuadernillo (PDF pág. 53-57).

Recopilación: Luciano Molina - Pablo Cuffia (Bloque Lunes Saavedra).
El cuadernillo escribe "Surdo Base" (surdo grave) y "Surdo Melodía"
(surdo agudo). Los pasajes de fusas/tresillos del timbal y del redoblante se
transcriben aproximando la lectura más probable del escaneo.
"""
from dsl import compas, score, seccion

TITULO = 'Muñequitos I'
MATCH = {'año': 4, 'orden': 5, 'nombre': 'Muñequitos I'}

INSTS = ['surdo_grave', 'surdo_agudo', 'redoblante', 'repique', 'timbal']
V = '----------------'


def c(sb=V, sme=V, re=V, rp=V, ti=V, **kw):
    return compas({'surdo_grave': sb, 'surdo_agudo': sme,
                   'redoblante': re, 'repique': rp, 'timbal': ti}, **kw)


# ------------------------------------------------------------- Introducción
introduccion = [
    c(rp='xxxxxxxxxxxxxxxx',
      texto='Introducción — repique cresc. p → f', dyn='p'),
    c(ti='------x-o=o=----', rp='xxxxxxxxxxxxxxxx', dyn='f'),
    c(ti='-x=-x-xxx-x-xxx-', rp='xxxxxxxxxxxxxxxx', dyn='p'),
    c(ti='x-x-xxx-x-x-xxx-', rp='xx-xxxxxxx-x----'),
]

llamada_intro = [
    c(rp='xxxxxxxxxxxxxxxx', repeat_begin=True,
      texto='Llamada de introducción', dyn='f'),
    c(rp='xxxxxxxxxxxxxxxx'),
    c(rp='xxxxxxxxxxxx----'),
    c(rp='----3(-xx)3(xxx)3(xxx)', repeat_end=True),
]

acompanamiento_intro = [
    c(sb='o===============', sme='o===============',
      ti='x==-----o=------', re='c=-c-x--c=-x-x--',
      texto='Surdos, timbal y redoblante sobre la introducción', dyn='mf'),
    c(sb='o===============', sme='o===============',
      ti='x-x-----o=--x-x-', re='c=-c-x--c=-x-x--'),
]

# ------------------------------------------------------------------ Toque 1
T1_SB = '----x=--x-x---x-'
T1_SME = 'x-xxxxxx--xxxx--'
T1_RE = 'x>>-x>>-x>>-x>>-'
T1_TI = 'x-oox-oox-oox-oo'
T1_RP = '-x=--x=--x=--x=-'


def toque_1(texto='Toque 1 (x4)'):
    return [
        c(sb=T1_SB, sme=T1_SME, re=T1_RE, ti=T1_TI, rp=T1_RP,
          repeat_begin=True, texto=texto, dyn='mf'),
        c(sb='--x---x---x-x=--', sme='x-xxxx--x-xxxx--',
          re='x>>-x>>-x>>-x>>-', ti='x-oox-oox-oox-oo', rp=T1_RP),
        c(sb='-x--x---x---x=--', sme='xx-x----x-xxxx--',
          re='x>>-x>>-x>>-x>>-', ti='x-oox-oox-oox-oo', rp=T1_RP),
        c(sb='-x--x-----x-x=--', sme='x-x-----x-x-x=--',
          re='x>>-x>>-x>>-x>>-', ti='x-oox-oox-oox-oo', rp=T1_RP,
          repeat_end=True),
    ]


corte = [
    c(rp='x-x-x=--x=x-x=--', texto='Corte — repique', dyn='f'),
    c(rp='xxxx-x=-x-x-x=--'),
]

# ------------------------------------------------------------------ Toque 2
T2_RE = 'x>>-x>>-x>>-x>>-'
T2_RP = '-x=--x=--x=--x=-'


def toque_2(texto='Toque 2 (x4)'):
    return [
        c(sb='----x=--x-x---x-', sme='x-xxxxxx--xxxx--',
          re=T2_RE, rp=T2_RP,
          ti='3(xoo)3(xoo)3(xoo)3(xoo)',
          repeat_begin=True, texto=texto, dyn='mf'),
        c(sb='--x---x---x-x=--', sme='x-xxxx--x-xxxx--',
          re=T2_RE, rp=T2_RP,
          ti='3(xoo)3(xoo)3(xoo)3(xoo)'),
        c(sb='-x--x---x---x=--', sme='xx-x----x-xxxx--',
          re=T2_RE, rp=T2_RP,
          ti='3(xoo)3(xoo)3(xoo)3(xoo)'),
        c(sb='-x--x-----x-x=--', sme='x-x-----x-x-x=--',
          re=T2_RE, rp=T2_RP,
          ti='3(xoo)3(oxo)3(xoo)3(xxo)',
          texto='Variación de timbal en la 4ta vuelta', repeat_end=True),
    ]


# ---------------------------------------------------------------- Variación
variacion = [
    c(sme='x-xxxx--x-xxxx--', re='x>>-x>>-x>>-x>>-',
      ti='x=--o=--x=--o=--',
      repeat_begin=True, texto='Variación (surdo melodía y redoblante)',
      dyn='mf'),
    c(sme='x-xxxx--xx-xxx--', re='x>>-x>>-x>>-x>>-',
      ti='x=--o=--x=--o=--', repeat_end=True),
]

# -------------------------------------------------------- Llamada intermedia
llamada_intermedia = [
    c(rp='xxxxxxxxxxxx----', texto='Llamada intermedia', dyn='f'),
    c(rp='xxxxxxxxxxxx----'),
    c(rp='o==============='),
    c(rp='x=------x=------'),
    c(rp='x=------x-x-x---'),
    c(sme='xxxxxxxxxxxxxxxx', sb='----------------',
      texto='Surdo melodía en fusas / surdo base en silencio', dyn='f'),
    c(sme='xxxxxxxxxxxxxxxx'),
    c(sme='xxxxxxxx----x-x-', sb='------------x-x-'),
    c(re='x>>-x>>-x>>-x>>-', ti='x-oox-oox-oox-oo',
      texto='Redoblante y timbal sobre la llamada'),
    c(re='x>>-x>>-x>>-x>>-', ti='x-oox-oox-oox-oo'),
]

# ------------------------------------------------------------------ Toque 3
def toque_3(texto='Toque 3 (x2)'):
    return [
        c(sb='----x=--x-x---x-', sme='x-xxxxxxxx-xxx--',
          re='x>>-x>>-x>>-x>>-', rp='-x=--x=--x=--x=-',
          ti='3(xoo)3(xoo)3(xoo)3(xoo)',
          repeat_begin=True, texto=texto, dyn='mf'),
        c(sb='--x---x---x-x=--', sme='x-xxxx--x-xxxx--',
          re='x>>-x>>-x>>-x>>-', rp='-x=--x=--x=--x=-',
          ti='3(xoo)3(xoo)3(xoo)3(xoo)'),
        c(sb='-x--x---x---x=--', sme='xx-x----x-xxxx--',
          re='x>>-x>>-x>>-x>>-', rp='-x=--x=--x=--x=-',
          ti='3(xoo)3(xoo)3(xoo)3(xoo)'),
        c(sb='-x--x-----x-x=--', sme='x-x-----x-x-x=--',
          re='x>>-x>>-x>>-x>>>', rp='-x=--x=--x=-x-x-',
          ti='3(xoo)3(oxo)3(xoo)3(xxo)', repeat_end=True),
    ]


# ------------------------------------------------------------- Llamada final
llamada_final = [
    c(rp='3(xxx)3(xxx)--------', ti='----------x-xx--',
      re='x>>-x>>-x>>-x>>-', sb='----x=--x-x---x-',
      sme='x-xxxxxxxx-xxx--',
      texto='Llamada final', dyn='f'),
    c(rp='--------3(xxx)3(xxx)', ti='o===============',
      re='x>>-x>>-x>>-x>>-', sb='--x---x---x-x=--',
      sme='x-xxxx--x-xxxx--'),
    c(rp='-x-x-x-x-x-x-x-x', ti='xx-xxx-xoo-xoo--',
      re='x>>-x>>-x>>-x>>-', sb='-x--x---x---x=--',
      sme='xx-x----x-xxxx--'),
    c(rp='-x-x-x-x-x-x-x--', ti='oo-x-x--x-x-x=--',
      re='x>>-x>>-x>>>x->-', sb='-x--x-----x-x=--',
      sme='x-x-----x-x-x=--'),
]

SCORE = score(TITULO, 'La Chilinga', 100, INSTS, [
    seccion('Introducción', introduccion, 1),
    seccion('Llamada de introducción', llamada_intro, 1),
    seccion('Acompañamiento de la introducción', acompanamiento_intro, 1),
    seccion('Toque 1', toque_1(), 4),
    seccion('Corte', corte, 1),
    seccion('Toque 2', toque_2(), 4),
    seccion('Variación', variacion, 2),
    seccion('Llamada intermedia', llamada_intermedia, 1),
    seccion('Toque 3', toque_3(), 2),
    seccion('Llamada final', llamada_final, 1),
])
