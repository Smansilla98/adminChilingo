"""Solo de Surdos (Malamakuá) — Cuadernillo (PDF pág. 52). Compás de 6/8.

Recopilación: Luciano Molina - Pablo Cuffia (Bloque Lunes Saavedra).
Los dos compases marcados en el cuadernillo con C (4/4) se escriben en 6/8
aproximando la figuración; queda aclarado en el texto del compás.
"""
from dsl import INSTS, compas, score, seccion

TITULO = 'Solo de Surdos (Malamakuá)'
MATCH = {'año': 3, 'orden': 10, 'nombre': 'Solo de Surdos (Malamakuá)'}

V = '------------'


def c(sg=V, sa=V, sm=V, re=V, rp=V, ti=V, **kw):
    return compas({'surdo_grave': sg, 'surdo_agudo': sa, 'surdo_medio': sm,
                   'redoblante': re, 'repique': rp, 'timbal': ti},
                  grid=12, num=6, den=8, **kw)


def surdos(pat, **kw):
    return c(sg=pat, sa=pat, sm=pat, **kw)


# --------------------------------------------------------- Solo de surdos (x2)
solo = [
    surdos('x=--t=--x=--', repeat_begin=True,
           texto='Solo de surdos (x2) — 6/8', dyn='mf'),
    surdos('x-t=--t=x=--'),
    surdos('t=--x-x=x=--'),
    surdos('t=--x=--x=x-'),
    surdos('t=--x-x=t=--'),
    surdos('x=x-x=--t=--'),
    surdos('t=--x=--t=x-'),
    surdos('x-x-x-x-x-x-',
           texto='Cierre en 4/4 en el cuadernillo (cresc. p → f)',
           dyn='p', repeat_end=True),
]

# ------------------------------------------------ Entrada al acompañamiento
entrada = [
    c(re='-x=--x=-x=--', ti='-x=--x=-x=--', rp='-x=--x=-x=--',
      texto='Entrada al acompañamiento', dyn='mf'),
    c(re='x=----x-x=--', ti='x=----x-x=--', rp='x-x-x-x-x=--'),
]

# ---------------------------------------------------- Acompañamiento de surdos
acompanamiento = [
    c(re='>x=>x=>x=>x=', ti='x=x-x=x-x=--', rp='x-x=x-x=x-x=',
      repeat_begin=True, texto='Acompañamiento de surdos', dyn='mf'),
    c(re='>x=>x=>x=>xx', ti='x=--x-x=x=--', rp='x-x=x-xxx-x=',
      repeat_end=True),
]

# --------------------------------------- Después de la repetición de surdos
despues = [
    c(re='x-x-x-x-x-x-', ti='x-x-x-x-x-x-', rp='x-x-x-x-x-x-',
      texto='Después de repetición de surdos (4/4 en el cuadernillo) — p → f',
      dyn='p'),
    surdos('x-x-x-x-x-x-', texto='Surdos grave, agudo y medio — p → f',
           dyn='p'),
]

# ------------------------------------------------------------ Llamada final
llamada_final = [
    c(sg='-x=-x=-x-x=-', sa='-x=-x=-x-x=-', sm='-x=-x=-x-x=-',
      re='-x=-x=-x-x=-', rp='-x=-x=-x-x=-', ti='-x=-x=-x-x=-',
      texto='Llamada final — todos', dyn='f'),
    c(sg='x=--x-x-x=--', sa='x=--x-x-x=--', sm='x=--x-x-x=--',
      re='x=--x-x-x=--', rp='x=--x-x-x=--', ti='x=--x-x-x=--'),
]

SCORE = score(TITULO, 'La Chilinga', 100, INSTS, [
    seccion('Solo de surdos', solo, 2),
    seccion('Entrada al acompañamiento', entrada, 1),
    seccion('Acompañamiento de surdos', acompanamiento, 4),
    seccion('Después de repetición de surdos', despues, 1),
    seccion('Llamada final', llamada_final, 1),
], num=6, den=8)
