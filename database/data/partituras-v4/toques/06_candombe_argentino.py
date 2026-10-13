"""Candombe Argentino — Cuadernillo pág. 11 (PDF pág. 14)."""
from cuad import hoja, rtr, surdos
from dsl import INSTS, score, seccion

TITULO = 'Candombe Argentino'
MATCH = {'año': 2, 'orden': 2, 'nombre': 'Candombe Argentino'}
PDF_PAGES = [14]

c = hoja(INSTS)

LL_HI = '-x==x==x-x==x==='     # sil. semi + corchea c/punto · corchea c/punto + semi · ídem · negra
LL_SU = '---x--x=---x--x='

llamada = [c(**rtr(LL_HI), **surdos(LL_SU))]

# Surdos: corchea c/punto + semi · sil. corchea + corchea · sil. corchea + corchea · negra.
# Los guiones (tapado) cambian en cada surdo.
SG = 't==x--t=--t=t==='
SA = 't==t--x=--t=t==='
SM = 't==t--t=--xxx=xx'
RE = '>xx>xx>xxx>x>xxx'
TI = 's==os=oos==os=oo'
RP = '>=>=->xx->xx->xx'

REV_TOQUE = 'timbal: cabeza hueca con guión; acentos de redo y repique'
toque = [
    c(surdo_grave=SG, surdo_agudo=SA, surdo_medio=SM, redoblante=RE, timbal=TI, repique=RP,
      rev=REV_TOQUE),
    c(surdo_grave=SG, surdo_agudo=SA, surdo_medio=SM, redoblante=RE, timbal=TI, repique=RP),
    c(surdo_grave=SG, surdo_agudo=SA, surdo_medio=SM, redoblante=RE, timbal=TI, repique=RP),
    c(surdo_grave=SG, surdo_agudo=SA, surdo_medio=SM, redoblante=RE, timbal=TI,
      repique='->==>==>->==>==='),
]

final = [
    c(**rtr(LL_HI), **surdos(LL_SU)),
    c(**rtr('x=x=------------'), **surdos('x=x=------------')),
]

SCORE = score(TITULO, 'La Chilinga', 88, INSTS, [
    seccion('Llamada', llamada, 1),
    seccion('Toque', toque, 2),
    seccion('Llamada final', final, 1),
])
