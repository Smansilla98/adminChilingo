"""Toque a Oxosi — Cuadernillo págs. 55-57 (PDF págs. 58-60).

El cuadernillo escribe por separado lo que va «sobre Toque 1» (redoblante y
repique, y tres timbales según la vuelta) sin decir cómo se superpone con los
surdos: cada rótulo va en su propia parte y queda marcado.
Cruz en surdos = chapa; triángulo en redoblante y repique = golpe agudo.
"""
from cuad import agudos, hoja, surdos
from dsl import INSTS, score, seccion

TITULO = 'Toque a Oxosi'
MATCH = {'año': 4, 'orden': 6, 'nombre': 'Oxosi II'}
PDF_PAGES = [58, 59, 60]

c = hoja(INSTS)
SOBRE = 'va sobre Toque 1: cómo se superpone con los surdos'

toque1 = [
    c(surdo_grave='c=x=----x===----', surdo_agudo='c=x=------------', surdo_medio='c=x=------------'),
    c(surdo_grave='--------x===----', surdo_agudo='x===------------'),
]

# Redoblante y Repique: semicorcheas corridas con un acento cada tanto.
ENTRA = '-------->xxx>xxx'
CORRE = 'xx>xxxx>xxxx>xxx'
CIERRA = 'xx>xxx>xx===----'
REV_AC = 'redo y repique: posición exacta de los acentos'


def corrida(n):
    return ([c(**agudos(ENTRA), rev=SOBRE)]
            + [c(**agudos(CORRE), rev=REV_AC if i == 0 else None) for i in range(n - 2)]
            + [c(**agudos(CIERRA))])


# Timbal: ciclo de 3 tiempos (2 slap + abierta · abierta + 2 slap · 2 abiertas).
CICLO = 'sso=o=sso=o='
FIN_TI = 'o=sso=oos===----'


def rodando(n):
    tira = CICLO * (n * 2)
    return [c(timbal=tira[i * 16:(i + 1) * 16], rev=SOBRE if i == 0 else None)
            for i in range(n - 1)] + [c(timbal=FIN_TI)]


A, B = 'sso=o=sso=o=----', '--ooo=o=sso=o==='
timbal2 = [c(timbal=p, rev=SOBRE if i == 0 else None)
           for i, p in enumerate([A, B, A, B, A, B, 'sso=o=sso=o=sso=', FIN_TI])]

# --- Toque 2
SU_A, SU_B = 'c=x=--x=xxx=x===', '--xxx=x=xxx=x==='
TI_A = 's=o=o=s=ooo=o=s='
RR_A = '----3(a-a)----3(a-a)'
RR_B = '----3(a-a)3(-xx)3(a-a)'
toque2 = [
    c(**surdos(SU_A), timbal=TI_A, **agudos(RR_A)),
    c(**surdos(SU_B), timbal=TI_A, **agudos(RR_B), rev='redo y repique: tresillos'),
    c(**surdos(SU_A), timbal=TI_A, **agudos(RR_A)),
    c(**surdos(SU_B), timbal='ooo=s=s=ooo=o=s=', **agudos(RR_B)),
]

final = [
    c(**surdos(SU_A), **agudos(RR_A), timbal=TI_A),
    c(**surdos(SU_B), **agudos(SU_B), timbal='--ooo=o=ooo=o==='),
]

SCORE = score(TITULO, 'La Chilinga', 84, INSTS, [
    seccion('Toque 1', toque1, 1),
    seccion('Sobre Toque 1: Redo y Repi (vueltas 1 y 3)', corrida(16), 1),
    seccion('Sobre Toque 1: Redo y Repi (2ª vuelta)', corrida(8), 1),
    seccion('Sobre Toque 1: Timbal 1 (1ª vuelta)', rodando(8), 1),
    seccion('Sobre Toque 1: Timbal 2 (2ª vuelta)', timbal2, 1),
    seccion('Sobre Toque 1: Timbal 3 (3ª vuelta)', rodando(16), 1),
    seccion('Toque 2', toque2, 1),
    seccion('Final', final, 1),
])
