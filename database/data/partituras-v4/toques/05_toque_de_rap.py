"""Toque de Rap — Cuadernillo págs. 9-10 (PDF págs. 12-13)."""
from cuad import hoja, surdos
from dsl import INSTS, score, seccion

TITULO = 'Toque de Rap'
MATCH = {'año': 1, 'orden': 8, 'nombre': 'Rap - Murga'}
PDF_PAGES = [12, 13]

c = hoja(INSTS)

GOLPE = '--x=----x===--x='          # sil. corchea + corchea · sil. negra · negra · sil. corchea + corchea

llamada = [
    c(dyn='p', **surdos('3(>xx)3(>xx)3(>xx)3(>xx)'), rev='surdos p < f (regulador)'),
    c(dyn='f', **surdos('>===x=x=--x=x==='), redoblante=GOLPE, timbal=GOLPE, repique=GOLPE),
    c(repique='----------x=x=x='),
]

SA = '----x===----x==='
RE1 = 'xxxx>xxxxxxx>xxx'
RE2 = 'xxxx>xxxxx>x>x>x'
TI = '--ss--oo--ss--oo'
RP1 = '----x===----x==='
RP2 = '----x===--x=x=x='


def base(m1, m2):
    return [
        c(rb=True, surdo_grave=m1, surdo_medio=m1, surdo_agudo=SA, redoblante=RE1, timbal=TI, repique=RP1),
        c(re=True, surdo_grave=m2, surdo_medio=m2, surdo_agudo=SA, redoblante=RE2, timbal=TI, repique=RP2),
    ]


base1 = base('xx==--xx--------', 'xx==--xx--------')
base2 = base('xxxx--xx--xx----', 'xxxx--xx--------')

intermedia = [
    c(repique='-x=xx==x-xx=-x=x'),
    c(repique='x==x-xx=--x=x=x='),
]

SCORE = score(TITULO, 'La Chilinga', 82, INSTS, [
    seccion('Llamada', llamada, 1),
    seccion('Base 1', base1, 4),
    seccion('Llamada intermedia', intermedia, 1),
    seccion('Base 2', base2, 4),
])
