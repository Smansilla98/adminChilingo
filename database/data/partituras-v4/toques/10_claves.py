"""Claves (Son, Rumba y Samba) — Cuadernillo (PDF pág. 23).

El cuadernillo las escribe en una sola línea; acá van sobre el agogó, que es
el timbre metálico disponible en el editor.
"""
from dsl import compas, score, seccion

TITULO = 'Claves'
MATCH = {'año': 2, 'orden': 8, 'nombre': 'Claves'}

INSTS = ['agogo']

TRES_SON = 'x=====x=====x==='      # 1 - 2& - 4
DOS = '----x===x======='          # 2 - 3
TRES_RUMBA = 'x=====x=======x='    # 1 - 2& - 4&
TRES_SAMBA = 'x===x=====x====='    # 1 - 2 - 3&

SON_1C = 'x==x==x=--x=x=--'
SON_1C_23 = '--x=x===x==x==x='
RUMBA_1C = 'x==x===x==x=x==='
RUMBA_1C_23 = '--x=x===x==x===x'
SAMBA_1C = 'x==x==x===x===x='
SAMBA_1C_23 = '--x===x=x==x==x='


def par(a, b, texto):
    return [compas({'agogo': a}, repeat_begin=True, texto=texto),
            compas({'agogo': b}, repeat_end=True)]


def uno(pat, texto):
    return [compas({'agogo': pat}, repeat_begin=True, repeat_end=True,
                   texto=texto)]


SCORE = score(TITULO, 'La Chilinga', 85, INSTS, [
    seccion('Clave de Son 3-2', par(TRES_SON, DOS, 'Clave de Son (3-2)'), 2),
    seccion('Clave de Son 2-3', par(DOS, TRES_SON, 'Clave de Son (2-3)'), 2),
    seccion('Clave de Son (1 compás)', uno(SON_1C, 'Clave de Son en 1 compás'), 2),
    seccion('Clave de Son 2-3 (1 compás)',
            uno(SON_1C_23, 'Clave de Son 2-3 en 1 compás'), 2),
    seccion('Clave de Rumba 3-2', par(TRES_RUMBA, DOS, 'Clave de Rumba (3-2)'), 2),
    seccion('Clave de Rumba 2-3', par(DOS, TRES_RUMBA, 'Clave de Rumba (2-3)'), 2),
    seccion('Clave de Rumba (1 compás)',
            uno(RUMBA_1C, 'Clave de Rumba en 1 compás'), 2),
    seccion('Clave de Rumba 2-3 (1 compás)',
            uno(RUMBA_1C_23, 'Clave de Rumba 2-3 en 1 compás'), 2),
    seccion('Clave de Samba 3-2', par(TRES_SAMBA, DOS, 'Clave de Samba (3-2)'), 2),
    seccion('Clave de Samba 2-3', par(DOS, TRES_SAMBA, 'Clave de Samba (2-3)'), 2),
    seccion('Clave de Samba (1 compás)',
            uno(SAMBA_1C, 'Clave de Samba en 1 compás'), 2),
    seccion('Clave de Samba 2-3 (1 compás)',
            uno(SAMBA_1C_23, 'Clave de Samba 2-3 en 1 compás'), 2),
])
