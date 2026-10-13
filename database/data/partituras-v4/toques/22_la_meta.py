"""La Meta — Cuadernillo págs. 40-46 (PDF págs. 43-49).

Cruz en surdos, redoblante y repique = golpe de chapa. En el timbal: hueca =
slap, rombo = palma. Los seisillos de las introducciones y el solo de repique
(triángulos = golpe agudo) son lectura aproximada del escaneo.
"""
from cuad import hoja, surdos
from dsl import INSTS, score, seccion

TITULO = 'La Meta'
MATCH = {'año': 4, 'orden': 2, 'nombre': 'La Meta'}
PDF_PAGES = [43, 44, 45, 46, 47, 48, 49]

c = hoja(INSTS)


def ga(pat):
    return {'surdo_grave': pat, 'surdo_agudo': pat}


OFF = '--c=--c=--c=--c='            # cruces a contratiempo
OFF6 = '--c=--c=--c=6(--xx--)'

intro1 = [
    c(timbal='6(o-oo-o)6(s--o-s)6(oooo-o)6(s--o--)', **surdos(OFF), redoblante=OFF, repique=OFF,
      rev='timbal: seisillos (figuras y cabezas)'),
]

llamada1 = [
    c(repique='6(--x---)6(-x--xx)6(x-x--x)6(--xx--)', surdo_medio=OFF, **ga(OFF),
      rev='repique: seisillos; orden de las líneas de la llamada'),
    c(repique='6(--x---)6(-x--xx)6(x-x--x)----', surdo_medio=OFF6, **ga(OFF)),
    c(repique=OFF, surdo_medio=OFF6, **ga(OFF)),
    c(repique=OFF, surdo_medio=OFF, **ga('x===--c=--c=--c=')),
]

S6 = '6(x-x-xx)'
T6 = '6(s-s-oo)'
corte1 = [
    c(**surdos(S6 * 4), redoblante=S6 * 4, repique=S6 * 4, timbal=T6 * 4),
    c(**surdos(S6 * 3 + 'x==='), redoblante=S6 * 3 + 'x===', repique=S6 * 3 + 'x===',
      timbal=T6 * 2 + '6(s-ss-s)s===', rev='timbal: último seisillo'),
]

GM = 'xx==--xx--------'
intro2 = [
    c(timbal='6(o-os-s)' * 4, redoblante=OFF, surdo_grave=OFF, surdo_medio=OFF, surdo_agudo=OFF,
      repique=OFF, rev='timbal: seisillos (figuras y cabezas)'),
    c(timbal='6(o-os-s)' * 4, redoblante='--c=--c=--x=x=x=', surdo_grave=OFF, surdo_medio=OFF,
      surdo_agudo=OFF, repique=OFF),
    c(timbal='----------ss--oo', redoblante='xxx>xxxxxxx>xxxx', surdo_grave=GM, surdo_medio=GM,
      surdo_agudo='----x===----x===', repique='----x===----x===', rev='acentos del redoblante'),
    c(timbal='--ss--oo--ss--oo', redoblante='xxx>xxxxx>>>xxxx', surdo_grave=GM, surdo_medio=GM,
      surdo_agudo='----x===----x===', repique='----x===--x=x=x='),
]

corte2 = [
    c(timbal='6(o-o---)6(s----s)6(o-so--)6(s----s)', repique='-x=xx==x-xx=-x=x',
      rev='timbal: seisillos (figuras y cabezas)'),
    c(timbal='6(o-o---)6(s----s)6(o-so--)6(s---ss)', repique='x==x-xx=--x=x=x='),
]

GOLPE = '--x=----x===--x='
llamada2 = [
    c(dyn='p', **surdos('3(>xx)3(>xx)3(>xx)3(>xx)'), rev='surdos p < f (regulador)'),
    c(dyn='f', **surdos('x===x=x=--x=x==='), repique=GOLPE, timbal='--s=----s===--s=', redoblante=GOLPE),
    c(timbal='6(ssssss)6(oooooo)6(ssssss)6(oooooo)', redoblante='6(cccccc)6(xxxxxx)6(cccccc)6(xxxxxx)'),
]

# --- Toque 1
G1 = 'cccx----'
M1 = 'cccc----'
R1 = 'ccc>xxx>'
DOS = 'surdo medio: negra y cruz a la vez'
toque1 = [
    c(**ga(G1 + 'c===c==='), surdo_medio=M1 + 'x=x=c===', redoblante='ccc>xx>xxx>x>xxx',
      timbal='--o=-ss=--o=-sss', repique='x===----x===-x==', rev=DOS + ' (3er tiempo)'),
]

variacion1 = [
    c(timbal='-------------oo=', texto='Entrada del timbal'),
    c(**ga(G1 * 2), surdo_medio=M1 * 2, redoblante=R1 * 2, timbal='-----oo=-----oo=',
      repique='x===-x==x===-x=='),
    c(**ga(G1 + 'c===c==='), surdo_medio=M1 + 'x=x=c===', redoblante=R1 + 'xx>x>xxx',
      timbal='-----oo=--o=-sss', repique='x===----x===-x==', rev=DOS + ' (3er tiempo)'),
]

TRES_A, TRES_B = '3:8(xcx)', '3:8(cxc)'
intermedia = [
    c(**ga(G1 * 2), surdo_medio='ccccxxxx' * 2, redoblante=G1 * 2, repique=M1 * 2),
    c(**ga(G1 + TRES_A), surdo_medio=G1 + TRES_A, redoblante='ccc-----3:8(-x-)',
      repique='ccc-----3:8(-x-)', rev='tresillos de negra'),
    c(**ga(TRES_B + 'c=x=----'), surdo_medio=TRES_B + 'c=x=----', redoblante='3:8(x-x)x===----',
      repique='3:8(x-x)x===----', timbal='-----ss=--o=-sss'),
    c(redoblante='----------x=x===', timbal='--o=-ss=--o=-sss'),
]

variacion2 = [
    c(**ga(G1 * 2), surdo_medio=M1 * 2, redoblante=R1 * 2, timbal='-----oo=-----oo=',
      repique='x===-x==x===-x=='),
]

# --- Toque 2
TI2 = 'p=s=p=ppp=s=p=pp'
toque2 = [
    c(surdo_grave='x===t===x===t===', surdo_agudo='t===x===t===x===', surdo_medio=M1 * 2,
      repique=M1 * 2, redoblante=R1 * 2, timbal=TI2),
]

# --- Solo de repique
B = dict(surdo_medio=M1 * 2, redoblante=R1 * 2, timbal=TI2)
GA_X = 'cccx------------'
GA_Y = '--x=--x=-x==----'
REV_SOLO = 'solo de repique: lectura aproximada'
solo = [
    c(repique='--------------x=', texto='Entrada del repique'),
    c(rb=True, repique='a===--a=--aa-aaa', **ga(GA_X), rev=REV_SOLO, **B),
    c(repique='a=aaaaa---x=--x=', **B),
    c(repique='a===--a=--aa-aaa', **ga(GA_X), **B),
    c(re=True, repique='a=aaaaa-------x=', **B),
    c(repique='a=x=a=x=aa-a----', **ga(GA_Y), rev=REV_SOLO),
    c(repique='aa-a-aa=aa-a-aa=', **ga(GA_X), **B),
    c(repique='a=x=a=x=aa-a----', **ga(GA_Y)),
    c(repique='aa-a-aa=aa-a-aa=', **ga(GA_X), rev=REV_SOLO, **B),
    c(repique='--aaa=a=aaa=a=aa'),
    c(repique='a=a=aa-a-a=a-aaa'),
]

SUBE = 'x=x=x=x='
variacion3 = [
    c(**ga(G1 * 2), surdo_medio=M1 + 'x==xc===', redoblante=R1 * 2, repique='x===============',
      rev=DOS + ' (3er tiempo)'),
    c(**ga(G1 + SUBE), surdo_medio=M1 + SUBE, redoblante=R1 + '>x>x>x>x',
      rev='surdos y redoblante p < f en los tiempos 3 y 4'),
]

base_final = [
    c(**ga('-t=x----cccx----'), surdo_medio='-t=x----x==x----', redoblante='-x=x----' + R1,
      timbal='-s=s----ssso-oo=', repique='-x=x----x===-x==', rev=DOS + ' (3er tiempo)'),
    c(**ga(G1 + SUBE), surdo_medio=M1 + SUBE, redoblante=R1 + '>x>x>x>x',
      timbal='ssso-oo=ssso-oo=', repique='x===-x==x===-x==',
      rev='surdos y redoblante p < f en los tiempos 3 y 4'),
]

SCORE = score(TITULO, 'La Chilinga', 86, INSTS, [
    seccion('Introducción 1', intro1, 1),
    seccion('Llamada (sobre timbal)', llamada1, 1),
    seccion('Corte', corte1, 1),
    seccion('Introducción 2', intro2, 1),
    seccion('Corte', corte2, 1),
    seccion('Llamada', llamada2, 1),
    seccion('Toque 1', toque1, 1),
    seccion('Variación (cada 2 vueltas)', variacion1, 1),
    seccion('Llamada intermedia', intermedia, 1),
    seccion('Variación', variacion2, 1),
    seccion('Toque 2', toque2, 1),
    seccion('Solo', solo, 1),
    seccion('Variación', variacion3, 1),
    seccion('Base final', base_final, 1),
])
