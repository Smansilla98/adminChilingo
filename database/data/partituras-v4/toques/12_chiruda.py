"""Chiruda — Cuadernillo págs. 23-25 (PDF págs. 26-28)."""
from cuad import T24, agudos, hoja, p24, rtr, surdos
from dsl import INSTS, score, seccion

TITULO = 'Chiruda'
MATCH = {'año': 2, 'orden': 5, 'nombre': 'Chiruda'}
PDF_PAGES = [26, 27, 28]

c = hoja(INSTS)

FRASE = 'x==xx==xx=x=x==='

llamada = [
    c(**rtr('xxx=----xxx=----'), **surdos('----x=x=----x=x=')),
    c(**rtr('xxx=xxx=----x==='), **surdos('--------xxx=----')),
    c(**rtr('----x===----x==='), **surdos('x=x=----x=x=----')),
    c(**rtr('----xxx=xxx=----'), **surdos('x=x=--------x===')),
    c(**rtr(FRASE)),
    c(**surdos(FRASE)),
]

SA = 'x=x=---x-x=x----'
SG = '----x===----x==='
SM = 'x===-x=x------x='
RR = '>xx>>xx>x>xx>x>x'
TI = '--ss--oo--ss--oo'
TI_VAR = '-----s==-----s=='
CRUCES = 'c==c--c=--c=c==='      # golpes de chapa

toque = [
    c(rb=True, re=True, surdo_agudo=SA, surdo_grave=SG, surdo_medio=SM, **agudos(RR), timbal=TI),
]

variacion1 = [
    c(timbal='-------------s==', texto='Entrada del timbal'),
    c(surdo_agudo=SA, surdo_grave=SG, surdo_medio='xxx=xxx=--------', redoblante=RR, timbal=TI_VAR),
    c(surdo_agudo=SA, surdo_grave=SG, surdo_medio=CRUCES, redoblante=RR, timbal=TI_VAR, repique=CRUCES),
]

variacion2 = [
    c(surdo_agudo=SA, surdo_grave=SG, surdo_medio='xxx=xxx=----xxxx', redoblante=RR, timbal=TI_VAR,
      rev='surdo medio p < f en el 4º tiempo'),
    c(surdo_agudo=SA, surdo_grave=SG, surdo_medio='x===------------', redoblante=RR, timbal=TI_VAR,
      repique='--x=-x==x==x--x='),
]

llamada2 = [
    c(**rtr('xxx=----xxx=----'), **surdos('----x=x=----x=x=')),
    c(**rtr('xxx=xxx=----x==='), **surdos('--------xxx=----')),
]

base_final = [
    c(surdo_agudo=SA, surdo_grave=SG, surdo_medio=SM, **agudos(RR), timbal=TI),
    c(surdo_agudo=SA, surdo_grave=SG, surdo_medio=SM, **agudos(RR), timbal=TI),
    c(surdo_agudo=SA, surdo_grave=SG, surdo_medio=SM, **agudos(RR), timbal=TI),
    c(surdo_agudo='x===============', surdo_grave='----x===--------', surdo_medio='x===--xxxxxxx=x=',
      rev='surdo medio: p < f; tiempo 2 (¿semis o fusas?)'),
]

final = [
    c(texto=T24, **rtr(p24('xxx=----')), **surdos(p24('----x=x='))),
]

SCORE = score(TITULO, 'La Chilinga', 88, INSTS, [
    seccion('Llamada', llamada, 1),
    seccion('Toque', toque, 4),
    seccion('Variación 1', variacion1, 4),
    seccion('Variación 2', variacion2, 4),
    seccion('Llamada', llamada2, 1),
    seccion('Base final', base_final, 1),
    seccion('Llamada final', final, 1),
])
