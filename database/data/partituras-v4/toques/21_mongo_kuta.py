"""Mongo kutá — Cuadernillo págs. 37-39 (PDF págs. 40-42).

Figuras muy chicas en el escaneo: los guiones de tapado del surdo agudo y las
cruces del timbal (dedo) son lectura probable.
"""
from cuad import agudos, hoja
from dsl import INSTS, score, seccion

TITULO = 'Mongo kutá'
MATCH = {'año': 3, 'orden': 6, 'nombre': 'Mongokuta I'}
PDF_PAGES = [40, 41, 42]

c = hoja(INSTS)

intro = [
    c(timbal='o=dd-ddo-ddd-ddd', surdo_agudo='tt-tt=x=tt-tt=x=',
      rev='surdo agudo: cuáles llevan guión (tapado)'),
    c(timbal='o=dd-ddo-ddo-ddd', surdo_agudo='t===============', surdo_grave='-------x---x----'),
]

SA = 'tt-tt=x=tt-tt=x='
SG = 't==x------------'
SM_A = '-c==---xx=x=xx=='
SM_B = '-c==-c==--------'
RE = 'xx>x>xxx>xx>xxx>'
TI = 'odd-ddo-ddd-dddd'
RP_A = '-cxx--------x==='
RP_B = '-cxx-cxx----x==='

toque = [
    c(surdo_agudo=SA, surdo_grave=SG, surdo_medio=SM_A, redoblante=RE, timbal=TI, repique=RP_A,
      rev='timbal (cruces) y guiones del surdo agudo'),
    c(surdo_agudo=SA, surdo_grave=SG, surdo_medio=SM_B, redoblante=RE, timbal=TI, repique=RP_B),
    c(surdo_agudo=SA, surdo_grave=SG, surdo_medio=SM_A, redoblante=RE, timbal=TI, repique=RP_A),
    c(surdo_agudo=SA, surdo_grave=SG, surdo_medio=SM_B, redoblante=RE, timbal=TI,
      repique='-cxx-cxx-----x=='),
]

CORTE = '---ccc----------'       # cruces (chapa) de cierre
RR = 'xx>>' * 4
variacion1 = [
    c(timbal='---------------s', texto='Entrada del timbal'),
    c(dyn='p', surdo_agudo=SA, surdo_grave=SG, **agudos(RR), timbal=TI,
      rev='todos p < f (regulador) en 4 compases; acentos'),
    c(surdo_agudo=SA, surdo_grave=SG, **agudos(RR), timbal=TI),
    c(surdo_agudo=SA, surdo_grave=SG, **agudos(RR), timbal=TI),
    c(dyn='f', surdo_agudo='tt-t---ccc------', surdo_grave=CORTE, surdo_medio=CORTE,
      **agudos('>>>x---ccc------'), timbal='oo-o---sss-s---s',
      rev='cierre: figuras de las cruces, tiempos 2 y 3'),
]

SMR = '-c==c==c-c==c==c'
variacion2 = [
    c(surdo_medio=SMR, repique=SMR, redoblante='xx>>xx>>x>x>6(>>>>>-)',
      rev='redoblante: grupo de 6 del 4º tiempo'),
    c(surdo_medio='-c==c==c-c==c===', repique='-c==c==c-c==c===', redoblante=RE),
]

REDONDA = 'x==============='
GA_FIN = 'x==xx==xx==xx==='
llamada_v2 = [
    c(rb=True, surdo_grave=REDONDA, surdo_agudo=REDONDA),
    c(surdo_grave=REDONDA, surdo_agudo=REDONDA),
    c(surdo_grave=REDONDA, surdo_agudo=REDONDA, timbal='ooooo==o-o==----',
      texto='Timbal: sólo sobre la 2ª vuelta de los surdos'),
    c(re=True, surdo_grave=GA_FIN, surdo_agudo=GA_FIN, timbal='ooooo==oo==oo==='),
]

llamada_rp = [
    c(repique=RR, texto='Repique: después de surdos grave y agudo'),
    c(repique=RR),
]

SM_B2 = '-c==-cx=x=x=xx=='
vuelve = [
    c(timbal='---------------s', texto='Entrada del timbal'),
    c(surdo_agudo=SA, surdo_grave=SG, surdo_medio=SM_A, redoblante=RE, timbal=TI, repique=RP_A),
    c(surdo_agudo=SA, surdo_grave=SG, surdo_medio=SM_B2, redoblante=RE, timbal=TI, repique=RP_B,
      rev='surdo medio, tiempo 2'),
    c(surdo_agudo=SA, surdo_grave=SG, surdo_medio=SM_A, redoblante=RE, timbal=TI, repique=RP_A),
    c(surdo_agudo=SA, surdo_grave=SG, surdo_medio=SM_B2, redoblante=RE, timbal='oo-o---sss-s---s',
      repique=RP_B),
]

SCORE = score(TITULO, 'La Chilinga', 84, INSTS, [
    seccion('Introducción', intro, 1),
    seccion('Toque', toque, 1),
    seccion('Variación 1', variacion1, 4),
    seccion('Variación 2', variacion2, 1),
    seccion('Llamada sobre Variación 2', llamada_v2, 2),
    seccion('Llamada', llamada_rp, 1),
    seccion('Toque (vuelve)', vuelve, 1),
])
