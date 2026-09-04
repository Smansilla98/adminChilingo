"""Chiruda — Cuadernillo págs. 23-25 (PDF págs. 26-28).

Transcripción literal del PDF (figuras = escritura de la escuela).
"""
from dsl import INSTS, VACIO, compas, score, seccion, tutti

TITULO = 'Chiruda'
MATCH = {'año': 2, 'orden': 5, 'nombre': 'Chiruda'}
PDF_PAGES = [26, 27, 28]

V = VACIO
RE_BASE = '>xx>>xx>x>xx>x>x'


def c(sg=V, sa=V, sm=V, re=V, rp=V, ti=V, **kw):
    return compas({'surdo_grave': sg, 'surdo_agudo': sa, 'surdo_medio': sm,
                   'redoblante': re, 'repique': rp, 'timbal': ti}, **kw)


# Llamada — HI y surdos en diálogo; frases densas → revisar acentos finos
HI_LL = 'x=xxx=xxx=xxx=xx'          # (corchea + 2 semis) × 4
llamada = [
    c(re=HI_LL, rp=HI_LL, ti=HI_LL,
      sg='-x=x=-x=--x=x=--', sa='-x=x=-x=--x=x=--', sm='-x=x=-x=--x=x=--',
      texto='Llamada — … revisar con la escuela', dyn='f'),
    c(re='----x=--x=xxx=xx', rp='----x=--x=xxx=xx', ti='----x=--x=xxx=xx',
      sg='x=xxx=x=--------', sa='x=xxx=x=--------', sm='x=xxx=x=--------'),
    c(re='x=xxx=x=x=======', rp='x=xxx=x=x=======', ti='x=xxx=x=x=======',
      sg='x=--x===--------', sa='x=--x===--------', sm='x=--x===--------'),
    c(sg='--------xxxxx=x=', sa='--------xxxxx=x=', sm='--------xxxxx=x='),
]

BASE = dict(sg='----x===----x===', sa='x=x=-x=x=x=x=x=-',
            sm='x===-x=x=---x=x=', re=RE_BASE, rp=RE_BASE,
            ti='-o=o=-x=x=-o=o=-')
toque = [c(**BASE, repeat_begin=True, repeat_end=True,
           texto='Toque — … revisar con la escuela (repi/timbal)', dyn='mf')]

variacion_1 = [
    c(sa='--------xx--fx=x', sg='----x===----x===', sm='--x=xxx=--------',
      re=RE_BASE, ti='---o=---x=--o=--',
      repeat_begin=True, texto='Variación 1 (x4) — … revisar con la escuela'),
    c(sa='x=x=--fx=-x=----', sg='----x===----x===', sm='t=-t=t=-t=x=----',
      re=RE_BASE, ti='---o=---x=--o=--', rp='t=-t=t=-t=x=----',
      repeat_end=True),
]

variacion_2 = [
    c(sa='x=x=--fx=-x=x=--', sg='----x===----x===', sm='x=xxx=x=--xxxx--',
      re=RE_BASE, ti='-x=x=--x=x=-----',
      repeat_begin=True, texto='Variación 2 (x4) — … revisar con la escuela', dyn='p'),
    c(sa='x=x=--fx=-x=----', sg='----x===----x===', sm='x===------------',
      re=RE_BASE, ti='-x=x=--x=x=-----', rp='-x=x=-x=-x=x=---',
      repeat_end=True, dyn='f'),
]

llamada_2 = [
    c(re='x=xx=x=---x=x=--', rp='x=xx=x=---x=x=--', ti='x=xx=x=---x=x=--',
      sg='----x=x=----x=x=', sa='----x=x=----x=x=', sm='----x=x=----x=x=',
      texto='Llamada — … revisar con la escuela', dyn='f'),
    c(re='x=xx=x=x=---x=--', rp='x=xx=x=x=---x=--', ti='x=xx=x=x=---x=--',
      sg='--------x=x=----', sa='--------x=x=----', sm='--------x=x=----'),
]

base_final = [
    c(sa='x=-fx=x=--------', sg='----x===----x===', sm='x=-fx=--x=--x=--',
      re=RE_BASE, rp=RE_BASE, ti='x=-x=-o=x=-o=---',
      repeat_begin=True, texto='Base final', dyn='mf'),
    c(sa='x=--fx=x=-------', sg='----x===----x===', sm='x=x=--x=-x=x=---',
      re=RE_BASE, rp=RE_BASE, ti='x=-x=-o=x=-o=---', repeat_end=True),
    c(sa='x===============', sm='x==-------xxxxx-', dyn='p'),
]

llamada_final = [
    c(re='x=x=x===--------', rp='x=x=x===--------', ti='x=x=x===--------',
      texto='Llamada final (compás de 2/4 completado con silencios)', dyn='f'),
    c(sg='--------x===x===', sa='--------x===x===', sm='--------x===x==='),
]

SCORE = score(TITULO, 'La Chilinga', 88, INSTS, [
    seccion('Llamada', llamada, 1),
    seccion('Toque', toque, 8),
    seccion('Variación 1', variacion_1, 4),
    seccion('Variación 2', variacion_2, 4),
    seccion('Llamada', llamada_2, 1),
    seccion('Base final', base_final, 2),
    seccion('Llamada final', llamada_final, 1),
])
