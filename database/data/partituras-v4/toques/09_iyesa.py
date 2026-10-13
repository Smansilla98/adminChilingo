"""Iyesá I, II y III — Cuadernillo págs. 15-19 (PDF págs. 18-22).

Agogó y Palmas: la cruz se carga como `chapa` (en el agogó, la otra campana).
"""
from cuad import hoja, rtr, surdos
from dsl import score, seccion

TITULO = 'Iyesá I, II y III'
MATCH = {'año': 1, 'orden': 9, 'nombre': 'Ixesa I'}
PDF_PAGES = [18, 19, 20, 21, 22]

INSTS = ['surdo_grave', 'surdo_agudo', 'surdo_medio', 'redoblante', 'repique',
         'timbal', 'agogo', 'palmas']
c = hoja(INSTS)


def ga(pat):
    """Rótulo «Surdos Grave y Agudo»."""
    return {'surdo_grave': pat, 'surdo_agudo': pat}


AGOGO = 'c=c=x=x=cc-x-xx='
intro = [
    c(agogo=AGOGO, rev='agogó: cruz y negra, qué campana es cada una'),
    c(agogo=AGOGO, palmas='c===c=c=c===c=c='),
]

# --- Iyesá I
RE1 = '>=x>>=x>>=x>6(>>>>--)'     # (corchea + 2 semis) × 3 · seisillo de 4 + silencio
TI1 = 'ssoo--ooss==sso='
SU_LL = '----x=x=x===----'
llamada = [
    c(repique='-x==x===-x=xx=x='),
    c(repique='x===-------x-x=x', **surdos(SU_LL), redoblante=RE1, timbal=TI1),
    c(repique='x===-------x-x=x', **surdos(SU_LL), redoblante=RE1, timbal=TI1,
      rev='repique, 4º tiempo'),
    c(repique='x===-----x=xx===', **surdos('----x=x=--------'), redoblante=RE1, timbal=TI1),
]

GA1 = 'x===----x===----'
SM1 = '----x=x=----x=x='
toque1 = [
    c(rb=True, re=True, **ga(GA1), surdo_medio=SM1, redoblante=RE1, timbal=TI1,
      repique='>x>xxx>x>xx>x>>x', rev='acentos del repique'),
]

variacion = [
    c(repique='6(>>>>--)xx>x>xx>x>>x'),
]

# --- Llamada para Iyesá II (sobre Iyesá I)
llamada2 = [
    c(repique='6(xxxxxx)6(xxxxxx)6(xxxxxx)6(xxxxxx)', **ga(GA1), surdo_medio=SM1,
      redoblante=RE1, timbal=TI1, rev='repique: seisillos (¿5 o 6 golpes cada uno?)'),
    c(repique='x=x=--x=--------', **ga('x===---------x=x'), surdo_medio='----x=x=--------',
      redoblante=RE1, timbal='ssoo--oo-s=s--oo'),
]

# --- Iyesá II
RE2 = 'xx>x>xxx>xx>xxx>'
SM2 = '-x=x----x==x----'
RP2 = 'xx==xx==xx==----'
toque2 = [
    c(rb=True, re=True, agogo=AGOGO, **ga('----x===-----x=x'), surdo_medio=SM2,
      redoblante=RE2, timbal='--oo--oo-s=s--oo', repique=RP2),
]

GA_LL = '-------xx=xx-xx='     # llamada de surdos grave y agudo para cada cambio
llamada3a = [
    c(**ga(GA_LL), surdo_medio=SM2, redoblante=RE2, timbal='--oo--oo-s-s--oo', repique=RP2),
]

# --- Iyesá III A / B / C: en los surdos la cruz es golpe de chapa
TI3 = '-s=s--o=-s=s--o='
toque3a = [
    c(rb=True, re=True, agogo='--x=x===x==x---x', **ga('---c--c=-x=c--c='),
      surdo_medio='c===c===c===c=x=', redoblante=RE2, timbal='-s=s--o=--------',
      repique='-x=x---------x=='),
]

llamada3b = [c(**ga(GA_LL))]

toque3b = [
    c(rb=True, re=True, **ga('-xxc--c=-xxc--c='), surdo_medio='c===c=xxc===c=xx',
      redoblante=RE2, timbal=TI3, repique='-x==---x-----x=='),
]

llamada3c = [c(**ga(GA_LL))]

toque3c = [
    c(rb=True, re=True, **ga('x=xc--c=-x=c--c='), surdo_medio='c===x=xxc===c=x=',
      redoblante=RE2, timbal=TI3, repique='-x=x---x-x==-x==',
      rev='surdo medio, 2º tiempo: cruz y negra a la vez'),
]

llamada_fin = [c(**ga(GA_LL))]

final = [
    c(dyn='p', **surdos('xxxxxxxxxxxxx==='), **rtr('xxxxxxxxxxxxx==='), rev='todos p < f (regulador)'),
    c(**surdos('x=x=----x=x=----'), **rtr('----xx==----x==x')),
    c(**surdos('x===x===3(x-x)----'), **rtr('---x---xx===x==='),
      rev='redo, timbal y repique: tiempos 1 y 2 (tresillo)'),
]

SCORE = score(TITULO, 'La Chilinga', 84, INSTS, [
    seccion('Introducción (Iyesá I)', intro, 1),
    seccion('Llamada', llamada, 1),
    seccion('Toque', toque1, 4),
    seccion('Variación', variacion, 1),
    seccion('Llamada para Iyesá II (sobre Iyesá I)', llamada2, 1),
    seccion('Toque (Iyesá II)', toque2, 4),
    seccion('Llamada para Iyesá III A (sobre II)', llamada3a, 1),
    seccion('Toque (Iyesá III A)', toque3a, 4),
    seccion('Llamada para Iyesá III B (sobre III A)', llamada3b, 1),
    seccion('Toque Iyesá III B', toque3b, 4),
    seccion('Llamada para Iyesá III C (sobre III B)', llamada3c, 1),
    seccion('Iyesá III C', toque3c, 4),
    seccion('Llamada para final Iyesá III C', llamada_fin, 1),
    seccion('Llamada final', final, 1),
])
