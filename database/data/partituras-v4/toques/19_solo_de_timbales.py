"""Solo de Timbales (Buscando a Coco) — Cuadernillo pág. 35 (PDF pág. 38).

Desde el compás 11 el cuadernillo escribe dos cabezas a la vez (una cruz abajo
y la nota arriba). El editor admite una sola por golpe: se carga la de arriba,
o la cruz (`dedo`) cuando va sola, y queda marcado.
"""
from cuad import hoja
from dsl import score, seccion

TITULO = 'Solo de Timbales (Buscando a Coco)'
MATCH = {'año': 3, 'orden': 2, 'nombre': 'Solo de timbales I'}
PDF_PAGES = [38]

c = hoja(['timbal'])


def t(pat, rev=None, grid=16):
    return c(timbal=(pat, grid), rev=rev)


DOS = 'falta la cruz de abajo (dos cabezas por golpe)'

solo = [
    t('--sso=o=s=s=o=s=--sso=o=s=s=o=o=', 'tiempos 1 y 3: ¿dos fusas?', grid=32),
    t('-sss-ooo-sss-oo='),
    t('6(ssssss)6(oooooo)6(ssssss)6(oooooo)'),
    t('s==oso-s-o=os=o='),
    t('s==oso-s-o=os=o='),
    t('o=s=s=oossoo--s=', '1ª nota escrita más abajo que el resto'),
    t('o=s=s=oossoo----', '1ª nota escrita más abajo que el resto'),
    t('o=s=s=oo3(sso)3(-ss)', '1ª nota escrita más abajo que el resto'),
    t('6(o-oooo)ss==3(sso)3(-ss)', 'seisillo del 1er tiempo'),
    t('3(o-s)-----s=os=os', 'tresillo del 1er tiempo'),
    t('-s=os=o=oooo-o=o'),
    t('-o=ss===d=o=d=s=', DOS),
    t('d=o=ssood=o=d=s=', DOS),
    t('d=o=ssood=ssoo==', DOS),
    t('ssooos==6(sooooo)6(sooooo)', DOS),
    t('6(sooooo)s===s=ssss=s', DOS + '; tiempos 3 y 4'),
    t('s=s=ss==3(ooo)3(-oo)', 'tresillos: dos cabezas negras por golpe'),
    t('3(o-o)3(oo-)s==s---s', 'tresillos: dos cabezas negras por golpe'),
    t('--s=s===o===----'),
]

SCORE = score(TITULO, 'La Chilinga', 86, ['timbal'], [
    seccion('Solo de timbales', solo, 1),
])
