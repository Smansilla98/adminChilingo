"""Claves (Son, Rumba y Samba) — Cuadernillo pág. 20 (PDF pág. 23).

El cuadernillo las escribe en una sola línea sin nombrar instrumento; acá van
sobre el agogó, que es el timbre metálico disponible en el editor.
"""
from dsl import compas, score, seccion

TITULO = 'Claves'
MATCH = {'año': 2, 'orden': 8, 'nombre': 'Claves'}
PDF_PAGES = [23]

INSTS = ['agogo']

DOS = '----x===x===----'           # sil. negra · negra · negra · sil. negra
SON = 'x===--x=----x==='           # negra · sil. corchea + corchea · sil. negra · negra
RUMBA = 'x===--x=------x='         # negra · sil. corchea + corchea · sil. negra · sil. corchea + corchea
SAMBA_A = 'x===--x=----x==='
SAMBA_B = '----x===--x=----'       # sil. negra · negra · sil. corchea + corchea · sil. negra


def par(a, b):
    return [compas({'agogo': a}, repeat_begin=True), compas({'agogo': b}, repeat_end=True)]


def uno(pat):
    return [compas({'agogo': pat}, repeat_begin=True, repeat_end=True)]


SCORE = score(TITULO, 'La Chilinga', 85, INSTS, [
    seccion('Clave de Son', par(SON, DOS), 2),
    seccion('Clave de Son 2-3', par(DOS, SON), 2),
    seccion('Clave de Son (1 compás)', uno('x==x--x=--x=x==='), 2),
    seccion('Clave de Son 2-3 (1 compás)', uno('--x=x===x==x--x='), 2),
    seccion('Clave de Rumba', par(RUMBA, DOS), 2),
    seccion('Clave de Rumba 2-3', par(DOS, RUMBA), 2),
    seccion('Clave de Rumba (1 compás)', uno('x==x---x--x=x==='), 2),
    seccion('Clave de Rumba 2-3 (1 compás)', uno('--x=x===x==x---x'), 2),
    seccion('Clave de Samba', par(SAMBA_A, SAMBA_B), 2),
    seccion('Clave de Samba 2-3', par(SAMBA_B, SAMBA_A), 2),
    seccion('Clave de Samba (1 compás)', uno('x==x--x=--x=-x=='), 2),
    seccion('Clave de Samba 2-3 (1 compás)', uno('--x=-x==x==x--x='), 2),
])
