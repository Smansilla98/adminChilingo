"""Muñequitos I — Cuadernillo págs. 50-54 (PDF págs. 53-57).

Surdo Base = surdo_grave · Surdo Melodía = surdo_agudo.
Densidades (tresillos/fusas) → revisar; sin inventar xx-x.
"""
from dsl import compas, score, seccion

TITULO = 'Muñequitos I'
MATCH = {'año': 4, 'orden': 5, 'nombre': 'Muñequitos I'}
PDF_PAGES = [53, 54, 55, 56, 57]

INSTS = ['surdo_grave', 'surdo_agudo', 'redoblante', 'repique', 'timbal']
V = '----------------'


def c(sb=V, sme=V, re=V, rp=V, ti=V, **kw):
    return compas({'surdo_grave': sb, 'surdo_agudo': sme,
                   'redoblante': re, 'repique': rp, 'timbal': ti}, **kw)


# ------------------------------------------------------------- Introducción
introduccion = [
    c(rp='xxxxxxxxxxxxxxxx',
      texto='Introducción — repique cresc. p → f — … revisar con la escuela',
      dyn='p'),
    c(ti='------x-o=o=----', rp='xxxxxxxxxxxxxxxx', dyn='f'),
    c(ti='-x=-x-xxxx-xxxx-', rp='xxxxxxxxxxxxxxxx', dyn='p'),
    c(ti='x=x=xxxx-x=xxxx-', rp='xxxxxxxxxxxx----'),
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
# Base/melodía: figuras de escuela (corcheas / grupos); HI densos → revisar
T1_SB = '----x=--x=----x-'
T1_SME = 'xxxxxxxx--xxxx--'
T1_RE = 'x>>-x>>-x>>-x>>-'
T1_TI = 'x=oox=oox=oox=oo'
T1_RP = '-x=--x=--x=--x=-'


def toque_1(texto='Toque 1 (x4)'):
    return [
        c(sb=T1_SB, sme=T1_SME, re=T1_RE, ti=T1_TI, rp=T1_RP,
          repeat_begin=True,
          texto=texto + ' — … revisar con la escuela', dyn='mf'),
        c(sb='--x---x---x=x=--', sme='xxxxxx--xxxxxx--',
          re=T1_RE, ti=T1_TI, rp=T1_RP),
        c(sb='-x--x---x---x=--', sme='xxxx----xxxxxx--',
          re=T1_RE, ti=T1_TI, rp=T1_RP),
        c(sb='-x--x-----x=x=--', sme='x=x=----x=x=x=--',
          re=T1_RE, ti=T1_TI, rp=T1_RP,
          repeat_end=True),
    ]


corte = [
    c(rp='x=x=x=--x=x=x=--',
      texto='Corte — repique — … revisar con la escuela', dyn='f'),
    c(rp='xxxx-x=-x=x=x=--'),
]

# ------------------------------------------------------------------ Toque 2
T2_RE = 'x>>-x>>-x>>-x>>-'
T2_RP = '-x=--x=--x=--x=-'


def toque_2(texto='Toque 2 (x4)'):
    return [
        c(sb=T1_SB, sme=T1_SME, re=T2_RE, rp=T2_RP,
          ti='3(xoo)3(xoo)3(xoo)3(xoo)',
          repeat_begin=True,
          texto=texto + ' — … revisar con la escuela', dyn='mf'),
        c(sb='--x---x---x=x=--', sme='xxxxxx--xxxxxx--',
          re=T2_RE, rp=T2_RP,
          ti='3(xoo)3(xoo)3(xoo)3(xoo)'),
        c(sb='-x--x---x---x=--', sme='xxxx----xxxxxx--',
          re=T2_RE, rp=T2_RP,
          ti='3(xoo)3(xoo)3(xoo)3(xoo)'),
        c(sb='-x--x-----x=x=--', sme='x=x=----x=x=x=--',
          re=T2_RE, rp=T2_RP,
          ti='3(xoo)3(oxo)3(xoo)3(xxo)',
          texto='Variación de timbal en la 4ta vuelta — … revisar',
          repeat_end=True),
    ]


# ---------------------------------------------------------------- Variación
variacion = [
    c(sme='xxxxxx--xxxxxx--', re=T2_RE,
      ti='x=--o=--x=--o=--',
      repeat_begin=True,
      texto='Variación — … revisar con la escuela', dyn='mf'),
    c(sme='xxxxxx--xxxx----', re=T2_RE,
      ti='x=--o=--x=--o=--', repeat_end=True),
]

# -------------------------------------------------------- Llamada intermedia
llamada_intermedia = [
    c(rp='xxxxxxxxxxxx----',
      texto='Llamada intermedia — … revisar con la escuela', dyn='f'),
    c(rp='xxxxxxxxxxxx----'),
    c(rp='o==============='),
    c(rp='x=------x=------'),
    c(rp='x=------x=x=x=--'),
    c(sme='xxxxxxxxxxxxxxxx', sb='----------------',
      texto='Surdo melodía en fusas / surdo base en silencio', dyn='f'),
    c(sme='xxxxxxxxxxxxxxxx'),
    c(sme='xxxxxxxx----x=x=', sb='------------x=x='),
    c(re=T2_RE, ti=T1_TI,
      texto='Redoblante y timbal sobre la llamada'),
    c(re=T2_RE, ti=T1_TI),
]

# ------------------------------------------------------------------ Toque 3
def toque_3(texto='Toque 3 (x2)'):
    return [
        c(sb=T1_SB, sme='xxxxxxxxxxxxxxxx',
          re=T2_RE, rp=T2_RP,
          ti='3(xoo)3(xoo)3(xoo)3(xoo)',
          repeat_begin=True,
          texto=texto + ' — … revisar con la escuela', dyn='mf'),
        c(sb='--x---x---x=x=--', sme='xxxxxx--xxxxxx--',
          re=T2_RE, rp=T2_RP,
          ti='3(xoo)3(xoo)3(xoo)3(xoo)'),
        c(sb='-x--x---x---x=--', sme='xxxx----xxxxxx--',
          re=T2_RE, rp=T2_RP,
          ti='3(xoo)3(xoo)3(xoo)3(xoo)'),
        c(sb='-x--x-----x=x=--', sme='x=x=----x=x=x=--',
          re='x>>-x>>-x>>-x>>>', rp='-x=--x=--x=-x=x-',
          ti='3(xoo)3(oxo)3(xoo)3(xxo)', repeat_end=True),
    ]


# ------------------------------------------------------------- Llamada final
llamada_final = [
    c(rp='3(xxx)3(xxx)--------', ti='----------xxxx--',
      re=T2_RE, sb=T1_SB, sme='xxxxxxxxxxxxxxxx',
      texto='Llamada final — … revisar con la escuela', dyn='f'),
    c(rp='--------3(xxx)3(xxx)', ti='o===============',
      re=T2_RE, sb='--x---x---x=x=--',
      sme='xxxxxx--xxxxxx--'),
    c(rp='-x=-x=-x=-x=-x=-', ti='xxxx----oo--oo--',
      re=T2_RE, sb='-x--x---x---x=--',
      sme='xxxx----xxxxxx--'),
    c(rp='-x=-x=-x=-x=----', ti='oo--x=--x=x=x=--',
      re='x>>-x>>-x>>>x->-', sb='-x--x-----x=x=--',
      sme='x=x=----x=x=x=--'),
]

SCORE = score(TITULO, 'La Chilinga', 86, INSTS, [
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
