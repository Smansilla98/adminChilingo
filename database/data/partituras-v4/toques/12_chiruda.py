"""Chiruda — Cuadernillo (PDF pág. 26-28)."""
from dsl import INSTS, VACIO, compas, score, seccion, tutti

TITULO = 'Chiruda'
MATCH = {'año': 2, 'orden': 5, 'nombre': 'Chiruda'}

V = VACIO
RE_BASE = '>xx>>xx>x>xx>x>x'


def c(sg=V, sa=V, sm=V, re=V, rp=V, ti=V, **kw):
    return compas({'surdo_grave': sg, 'surdo_agudo': sa, 'surdo_medio': sm,
                   'redoblante': re, 'repique': rp, 'timbal': ti}, **kw)


# ---------------------------------------------------------------------- Llamada
llamada = [
    c(re='xx-x-xx-xx-x-xx-', rp='xx-x-xx-xx-x-xx-', ti='xx-x-xx-xx-x-xx-',
      sg='-x=x=-x=--x=x=--', sa='-x=x=-x=--x=x=--', sm='-x=x=-x=--x=x=--',
      texto='Llamada', dyn='f'),
    c(re='----x=--xx-x-xx-', rp='----x=--xx-x-xx-', ti='----x=--xx-x-xx-',
      sg='xx-x-xx-x=------', sa='xx-x-xx-x=------', sm='xx-x-xx-x=------'),
    c(re='xx-x-xx-x=x=----', rp='xx-x-xx-x=x=----', ti='xx-x-xx-x=x=----',
      sg='x=--x===--------', sa='x=--x===--------', sm='x=--x===--------'),
    c(sg='---------xxxx-x=', sa='---------xxxx-x=', sm='---------xxxx-x='),
]

# ------------------------------------------------------------------------ Toque
BASE = dict(sg='----x===----x===', sa='x=x=-x=x=x=x=x=-',
            sm='x===-x=x=---x=x=', re=RE_BASE, rp=RE_BASE,
            ti='-o=o=-x=x=-o=o=-')
toque = [c(**BASE, repeat_begin=True, repeat_end=True, texto='Toque', dyn='mf')]

# ------------------------------------------------------------------ Variación 1
variacion_1 = [
    c(sa='--------xx--fx=x', sg='----x===----x===', sm='--xx-xxx--------',
      re=RE_BASE, ti='---o=---x=--o=--',
      repeat_begin=True, texto='Variación 1 (x4)'),
    c(sa='x=x=--fx=-x=----', sg='----x===----x===', sm='t=-t=t=-t=x=----',
      re=RE_BASE, ti='---o=---x=--o=--', rp='t=-t=t=-t=x=----',
      repeat_end=True),
]

# ------------------------------------------------------------------ Variación 2
variacion_2 = [
    c(sa='x=x=--fx=-x=x=--', sg='----x===----x===', sm='xx-x-xx--xxxx---',
      re=RE_BASE, ti='-x=x=--x=x=-----',
      repeat_begin=True, texto='Variación 2 (x4)', dyn='p'),
    c(sa='x=x=--fx=-x=----', sg='----x===----x===', sm='x===------------',
      re=RE_BASE, ti='-x=x=--x=x=-----', rp='-x=x=-x=-x=x=---',
      repeat_end=True, dyn='f'),
]

# --------------------------------------------------------- Llamada (2do bloque)
llamada_2 = [
    c(re='xx-x-x=---x=x=--', rp='xx-x-x=---x=x=--', ti='xx-x-x=---x=x=--',
      sg='----x=x=----x=x=', sa='----x=x=----x=x=', sm='----x=x=----x=x=',
      texto='Llamada', dyn='f'),
    c(re='xx-x-x=x=---x=--', rp='xx-x-x=x=---x=--', ti='xx-x-x=x=---x=--',
      sg='--------x=x=----', sa='--------x=x=----', sm='--------x=x=----'),
]

# ------------------------------------------------------------------- Base final
base_final = [
    c(sa='x=-fx=x=--------', sg='----x===----x===', sm='x=-fx=--x=--x=--',
      re=RE_BASE, rp=RE_BASE, ti='x=-x=-o=x=-o=---',
      repeat_begin=True, texto='Base final', dyn='mf'),
    c(sa='x=--fx=x=-------', sg='----x===----x===', sm='x=x=--x=-x=x=---',
      re=RE_BASE, rp=RE_BASE, ti='x=-x=-o=x=-o=---', repeat_end=True),
    c(sa='x===============', sm='x==-------xxxxx-', dyn='p'),
]

llamada_final = [
    c(re='x=x=x===--------', rp='x=x=x===--------', ti='x=x=x===--------',
      texto='Llamada final (compás de 2/4 completado con silencios)', dyn='f'),
    c(sg='--------x===x===', sa='--------x===x===', sm='--------x===x==='),
]

SCORE = score(TITULO, 'La Chilinga', 100, INSTS, [
    seccion('Llamada', llamada, 1),
    seccion('Toque', toque, 8),
    seccion('Variación 1', variacion_1, 4),
    seccion('Variación 2', variacion_2, 4),
    seccion('Llamada', llamada_2, 1),
    seccion('Base final', base_final, 2),
    seccion('Llamada final', llamada_final, 1),
])
