"""Rumba — Cuadernillo págs. 21-22 (PDF págs. 24-25)."""
from cuad import T24, hoja, p24
from dsl import INSTS, score, seccion, unisono

TITULO = 'Rumba'
MATCH = {'año': 2, 'orden': 7, 'nombre': 'Ritmo de Rumba'}
PDF_PAGES = [24, 25]

c = hoja(INSTS)

CLAVE = 'x==x---x--x=x==='      # clave de rumba en un compás

llamada = [c(**unisono(CLAVE))]

RE = '>xx>xxx>xx>x>xxx'
TI = 'ps-odd-s-ooo-ooo'
RP = 'xx-xxx-xx=x=xx-x'

toque = [
    c(rb=True, re=True, surdo_grave='t==x------------', surdo_agudo='tt-tt=x=tt-tt=x=',
      surdo_medio='--------x==xx===', redoblante=RE, timbal=TI, repique=RP,
      rev='timbal (cabezas y guiones); guiones del surdo agudo'),
]

llamada_medio = [
    c(surdo_medio=CLAVE),
    c(surdo_medio='-x=x-xx=-x=xx===', rev='surdo medio, tiempos 1 a 3'),
    c(surdo_medio='3(xxx)3(xxx)x==xx==='),
    c(surdo_medio='3(xxx)3(xxx)x==xx==='),
]

TRES = '3(xxx)3(xxx)'
CIERRE = p24('x==xx===')
final_unisono = [
    c(surdo_medio='--------' + TRES, surdo_grave='x==x----' + TRES, surdo_agudo='--------' + TRES,
      redoblante='>xx>xxx>3(>>>)3(>>>)', timbal='ps-odd-s3(ooo)3(ooo)',
      repique='xx-xxx-x' + TRES),
    c(texto=T24, surdo_medio=CIERRE, surdo_grave=CIERRE, surdo_agudo=CIERRE,
      redoblante=p24('>==>>==='), timbal=p24('o==oo==='), repique=CIERRE),
]

variacion = [
    c(timbal='6(ssssss)6(oooooo)6(ssssss)6(oooooo)'),
    c(timbal='s=s=s=s=ssoo----'),
    c(timbal='ssooss==3(ooo)3(ooo)'),
    c(timbal='s==s---s--s=s==='),
]

SCORE = score(TITULO, 'La Chilinga', 86, INSTS, [
    seccion('Llamada inicial y final', llamada, 1),
    seccion('Toque', toque, 4),
    seccion('Llamada (sobre el toque)', llamada_medio, 1),
    seccion('Final unísono de llamada', final_unisono, 1),
    seccion('Variación (sobre el toque)', variacion, 1),
])
