"""Muñequitos I — Cuadernillo págs. 50-54 (PDF págs. 53-57).

«Surdo Base» = surdo grave, «Surdo Melodía» = surdo agudo. En el redoblante la
cruz es golpe de chapa. El cuadernillo no deja claro cómo se superponen las
líneas de la introducción ni de la llamada intermedia: quedan marcadas.
"""
from cuad import hoja, surdos
from dsl import INSTS, score, seccion

TITULO = 'Muñequitos I'
MATCH = {'año': 4, 'orden': 5, 'nombre': 'Muñequitos I'}
PDF_PAGES = [53, 54, 55, 56, 57]

c = hoja(INSTS)

CORCHEAS = 'x=x=x=x=x=x=x=x='
MORD = 'f=x=x=x=x=x=x=x='          # mordente + 8 corcheas
CIERRE = 'x==x------------'
RE = 'c=>>' * 4
TI = 'o==so=oos==ss=oo'
RP = '--xx' * 4
REDONDA = 'x==============='
ORDEN = 'cómo se superponen las líneas'

intro = [
    c(timbal='-----------ss===', rev='introducción: ' + ORDEN),
    c(timbal='---so=oos==ss=oo'),
    c(repique=CORCHEAS, dyn='p', rev='repique p < f > p en 4 compases'),
    c(repique=CORCHEAS), c(repique=CORCHEAS), c(repique=CORCHEAS),
    c(rb=True, repique=MORD),
    c(re=True, repique=CIERRE),
    c(repique='x==x' * 4), c(repique='x==x' * 4),
    c(repique='x===----3(-xx)3(xxx)'),
    c(**surdos(REDONDA), timbal=TI, redoblante=RE),
]

llamada_intro = [
    c(repique=REDONDA), c(repique=REDONDA), c(repique='x=======x======='),
    c(repique='x===x===3(xxx)3(xxx)'),
]

# --- Toques
B1 = '--x=------------'
B3 = '--x=------x=----'
B4 = '--x=----x==x--x='
M1 = '--x=--xxxxxxxxxx'
BASE_1 = [B1, B1, B3, B4]
MELO_1 = [M1, M1, B3, B4]
BASE_2 = [B1, B1, B3, B1]
MELO_2 = [M1, M1, B3, M1]
TRES = '3(o-s)3(ooo)3(s-s)3(soo)'
TI_2 = [TI, TRES, '-o=ss=ss-o=ss=ss', '-o=ss=ss-o=ss=ss']


def toque(base, melo, timbal, rev=None):
    return [c(surdo_grave=base[i], surdo_agudo=melo[i], redoblante=RE, timbal=timbal[i],
              repique=RP, rev=rev if i == 1 else None) for i in range(4)]


toque1 = toque(BASE_1, MELO_1, [TI] * 4)

corte = [
    c(repique='f=x=x=x=x==x-xxx', rev='repique, 4º tiempo (¿fusas?)'),
    c(repique='x=x=x==x--------'),
]

REV_TI = 'timbal: tresillos y cabezas'
toque2 = toque(BASE_2, MELO_2, TI_2, rev=REV_TI)
toque2_var = [c(timbal=t, rev=REV_TI if i == 1 else None) for i, t in enumerate([TI, TRES, TRES, TRES])]

SEMIS = 'x' * 16
variacion = [
    c(surdo_agudo=SEMIS, redoblante=RE, timbal='oo==' * 4),
    c(surdo_agudo=SEMIS, redoblante=RE, timbal='oo==' * 4),
]

TO = 'oo==' * 4
INTERMEDIA = [
    # repique, surdo melodía, surdo base, redoblante, timbal
    (MORD, SEMIS, None, RE, TO),
    (CIERRE, SEMIS, None, RE, 'oo==oo==ss==ss=='),
    (MORD, SEMIS, None, RE, TO),
    (CIERRE, SEMIS, None, RE, 'oo==oo==s=s=ss=='),
    (REDONDA, SEMIS, None, RE, TO),
    (REDONDA, SEMIS, None, RE, TO),
    ('x=======x=======', SEMIS, None, RE, TO),
    (REDONDA, None, None, None, TO),
    ('x===----x===----', '------xxxxxxxxxx', B1, RE, TI),
    (None, None, None, None, 'oossoossoossooss'),
]


def fila(i, f):
    voces = {k: v for k, v in zip(('repique', 'surdo_agudo', 'surdo_grave', 'redoblante', 'timbal'), f) if v}
    return c(rev=('llamada intermedia: ' + ORDEN) if i == 0 else
             ('timbal: cabezas huecas' if i in (1, 3) else None), **voces)


intermedia = [fila(i, f) for i, f in enumerate(INTERMEDIA)]

toque3 = toque(BASE_1, MELO_1, TI_2, rev=REV_TI) + toque(BASE_2, MELO_2, TI_2, rev=REV_TI)

final = [
    c(repique='3(xxx)3(xxx)x===----', timbal='--------3(-oo)3(ooo)', redoblante=RE,
      surdo_grave=B1, surdo_agudo=M1),
    c(repique='----3(xxx)3(xxx)x===', timbal='o===============', redoblante=RE,
      surdo_grave=B1, surdo_agudo=M1),
    c(repique=RP, timbal='-o=ss=ss-o=ss=ss', redoblante=RE, surdo_grave=B3, surdo_agudo=B3),
    c(repique='--xx--xxx==x--x=', timbal='-s=ss=ooo==o--o=', redoblante='c=>>c=>>>==>-->=',
      surdo_grave=B4, surdo_agudo=B4, rev='timbal: cabezas de los tiempos 1 y 2'),
]

SCORE = score(TITULO, 'La Chilinga', 86, INSTS, [
    seccion('Introducción', intro, 1),
    seccion('Llamada de introducción', llamada_intro, 1),
    seccion('Toque 1', toque1, 4),
    seccion('Corte', corte, 1),
    seccion('Toque 2', toque2, 4),
    seccion('Toque 2: timbal en la 4ª vuelta', toque2_var, 1),
    seccion('Variación', variacion, 1),
    seccion('Llamada intermedia', intermedia, 1),
    seccion('Toque 3', toque3, 2),
    seccion('Llamada final', final, 1),
])
