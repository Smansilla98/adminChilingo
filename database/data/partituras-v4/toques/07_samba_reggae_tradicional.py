"""Samba Reggae Tradicional — Cuadernillo págs. 12-13 (PDF págs. 15-16)."""
from cuad import hoja, surdos
from dsl import INSTS, score, seccion

TITULO = 'Samba Reggae Tradicional'
MATCH = {'año': 1, 'orden': 6, 'nombre': 'Samba Reggae I y II'}
PDF_PAGES = [15, 16]

c = hoja(INSTS)

SG = 'x===----x===----'
SA = '----x===----x==='
SM = '------x=----xxxx'
RE = 'xx>>' * 4
RP = '>xx>xx>xx>x>xx>x'

toque = [
    c(rb=True, surdo_grave=SG, surdo_agudo=SA, surdo_medio=SM, redoblante=RE,
      timbal='o=ss-sooo=ss-soo', repique=RP, rev='acentos del repique'),
    c(re=True, surdo_grave=SG, surdo_agudo=SA, surdo_medio=SM, redoblante=RE,
      timbal=('o===s=s=--s=o=o=o=s=s=s=--ooo=o=', 32), repique=RP,
      rev='timbal, tiempos 3 y 4 (cabezas y fusas)'),
]

# Repique: corchea c/punto + semi · sil. corchea + corchea · sil. semi + 2 fusas + 2 semis · negra
RP_A = ('x=====x=----x===--xxx=x=x=======', 32)
RP_C = '---------x=x--x='
SU_ENTRA = '--------------x='
SU_SUBE = 'xxxxxxxxx===xxxx'

llamada1 = [
    c(repique=RP_A, **surdos(SU_ENTRA)),
    c(repique=('--x===x=--x===x=--xxx=x=x===x===', 32), **surdos('x===============')),
    c(dyn='p', repique=RP_C, **surdos(SU_SUBE), rev='surdos p < f (regulador)'),
]

llamada2 = [
    c(repique=RP_A, **surdos(SU_ENTRA)),
    c(repique=('--xxx=x=x=======--xxx=x=x=======', 32), **surdos('x===--x=x===--x=')),
    c(dyn='p', repique=RP_C, **surdos(SU_SUBE), rev='surdos p < f (regulador)'),
]

variacion = [
    c(surdo_medio='----xxxx----xxxx'),
    c(surdo_medio='----xxxx----xxxx'),
]

final = [
    c(repique=RP_A, **surdos(SU_ENTRA)),
    c(**surdos('x===============')),
]

SCORE = score(TITULO, 'La Chilinga', 86, INSTS, [
    seccion('Toque', toque, 4),
    seccion('Llamada 1 (sobre Toque)', llamada1, 1),
    seccion('Llamada 2 (sobre Toque)', llamada2, 1),
    seccion('Variación', variacion, 1),
    seccion('Llamada final (sobre Toque)', final, 1),
])
