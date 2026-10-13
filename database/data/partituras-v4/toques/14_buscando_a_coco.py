"""Buscando a Coco — Cuadernillo págs. 28-30 (PDF págs. 31-33).

«Director (Timbaletas)» no existe como instrumento en el editor: va en la voz
del timbal, con la aclaración en el texto del compás.
"""
from cuad import hoja, surdos
from dsl import INSTS, score, seccion

TITULO = 'Buscando a Coco'
MATCH = {'año': 3, 'orden': 1, 'nombre': 'Buscando a Coco'}
PDF_PAGES = [31, 32, 33]

c = hoja(INSTS)

D3 = 'xx-x-xx=x==xx==x'
D4 = 'x=x=xx-x--------'
llamada = [
    c(timbal='xx-x-x=x-x=xxx-x', rev='Director (Timbaletas): escaneo ilegible, c. 1-2'),
    c(timbal='-x=xxx-x--------', **surdos('------------x===')),
    c(timbal=D3, repique=D3, rev='Director y repique: 2º tiempo'),
    c(timbal=D4, repique=D4),
]

SA = 'x===----x===----'
SG = '----x===------x='
SM = '------x=----x==x'
RE = 'xx>x>xxx>xx>xxx>'
TI = 'o==o--ss--oos=so'
RP = '>>x>xx>>x>xx>>xx'

base1 = [
    c(surdo_agudo=SA, surdo_grave=SG, surdo_medio=SM, redoblante=RE, timbal=TI,
      repique='>>x>xx>>x>xx>>=='),
    c(redoblante=RE, timbal='-os=o=ss--oos=so', repique='------>>x>xx>>xx',
      rev='timbal, tiempos 1 y 2'),
]

base2 = [
    c(surdo_agudo=SA, surdo_grave=SG, surdo_medio=SM, redoblante=RE, timbal=TI, repique=RP),
    c(surdo_agudo='--x=------------', surdo_medio='-------xx=x=xx==', redoblante=RE, timbal=TI,
      repique=RP),
]

base3 = [
    c(surdo_agudo=SA, surdo_grave=SG, surdo_medio=SM, redoblante=RE, timbal=TI, repique=RP),
    c(surdo_grave='------x=------x=', surdo_medio='----x==x----x==x', redoblante=RE, timbal=TI,
      repique=RP),
]

corte = [
    c(timbal='6(sooooo)6(sooooo)s===----', **surdos('-----------xx=x='),
      rev='timbal: cabezas del seisillo y figura del 3er tiempo'),
]

REDONDA = 'x==============='
B4 = dict(surdo_agudo=REDONDA, surdo_grave='------x=------x=', surdo_medio='----x==x----x==x',
          redoblante=RE, timbal=TI, repique=RP)
base4 = [
    c(**B4),
    c(dyn='f', rev='surdos y redoblante f > p (regulador)', **B4),
]

coda = [
    c(timbal='3(xxx)3(xxx)3(xxx)3(xxx)', **surdos('x==x--c=--------'), repique='------c=--------',
      texto='Director (Timbaletas); timbal y repique siguen la base'),
]

SCORE = score(TITULO, 'La Chilinga', 86, INSTS, [
    seccion('Llamada', llamada, 1),
    seccion('Base 1', base1, 4),
    seccion('Base 2', base2, 4),
    seccion('Base 3', base3, 4),
    seccion('Corte', corte, 1),
    seccion('Base 4', base4, 4),
    seccion('Coda sobre timbal y repique', coda, 1),
])
