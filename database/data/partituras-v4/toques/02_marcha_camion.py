"""Marcha Camión — Cuadernillo págs. 4-6 (PDF págs. 7-9)."""
from cuad import T24, agudos, hoja, p24, rtr, surdos
from dsl import INSTS, score, seccion, unisono

TITULO = 'Marcha Camión'
MATCH = {'año': 1, 'orden': 3, 'nombre': 'Marcha Camión'}
PDF_PAGES = [7, 8, 9]

c = hoja(INSTS)

ENTRADA = '-----------xx=x='           # sil. blanca · sil. corchea c/punto + semi · 2 corcheas
TI = '--ss--oo--ss--oo'                # (sil. corchea + 2 slap · sil. corchea + 2 abiertas) × 2
TI_SEXT = '6(oooo--)--oo--ss--oo'      # seisillo de 4 + silencio en el 1er tiempo
RE = 'xx>x' * 4                        # 16 semis, acento en la 3ª de cada tiempo
RP = '--x=' * 4                        # corcheas a contratiempo
RP_SEXT = '--x=--x=--x=6(xxxx--)'
RP_FUSAS = ('----x===----x===----x===--xxx===', 32)   # cierra con sil. semi + 2 fusas + corchea

llamada = [
    c(**surdos('x=xxx=xx-x=xx=x=')),
    c(**surdos(ENTRADA), **rtr('xx-xxx-xx===----')),
]

intro = [
    c(surdo_grave='x===----x===----', surdo_agudo='----x===----x===', surdo_medio=ENTRADA,
      **agudos('>=xx' * 4), timbal=TI),
    c(surdo_grave='x===----x===----', surdo_agudo='----x===----x===', surdo_medio=ENTRADA,
      **agudos('>=xx' * 4), timbal=TI),
    c(texto=T24, **surdos(p24('---xx=x=')), **agudos(p24('>===----')), timbal=p24('o===----')),
]

base1 = [
    c(rb=True, **surdos('x==x----x=x=----'), redoblante=RE, timbal=TI, repique=RP),
    c(re=True, **surdos('x==x----x=x=x==='), redoblante=RE, timbal=TI, repique=RP),
]

base2 = [
    c(rb=True, **surdos('x==xx===x=x=----'), redoblante=RE, timbal=TI, repique=RP_SEXT),
    c(re=True, **surdos('x==xx===x=x=x==='), redoblante=RE, timbal=TI_SEXT, repique=RP_FUSAS),
]

base3 = [
    c(rb=True, **surdos('xxxxx===x=x=----'), redoblante=RE, timbal=TI, repique=RP_SEXT),
    c(re=True, **surdos('xxxxx==xx==xx==='), redoblante=RE, timbal=TI_SEXT, repique=RP_FUSAS),
]

variacion = [
    c(**surdos('xxxxx===x=x=----'), redoblante=RE, timbal=TI, repique=RP),
    c(dyn='p', **surdos('xxxxxxxxxxxxx==='), redoblante=RE, timbal='--ss--oo--ss-s-s', repique=RP,
      rev='timbal, 4º tiempo; surdos y redoblante p < f'),
    c(**surdos('xxxxx===x=x=----'), redoblante=RE, timbal='ss-ss=s=6(oooo--)----',
      repique=RP_SEXT, rev='timbal, tiempos 1 y 2'),
    c(**surdos('xxxxx==xx==xx==='), redoblante=RE, timbal='6(oooo--)----6(oooo--)o===',
      repique='----6(xxxx--)----x==='),
]

final = [c(**unisono('xxxxx==xx==xx==='))]

SCORE = score(TITULO, 'La Chilinga', 86, INSTS, [
    seccion('Llamada', llamada, 1),
    seccion('Introducción', intro, 1),
    seccion('Base 1', base1, 4),
    seccion('Base 2', base2, 4),
    seccion('Base 3', base3, 4),
    seccion('Variación Base 3', variacion, 1),
    seccion('Llamada final', final, 1),
])
