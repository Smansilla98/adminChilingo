"""Sacateca — Cuadernillo (PDF pág. 29-30)."""
from dsl import INSTS, VACIO, compas, score, seccion, tutti, unisono

TITULO = 'Sacateca'
MATCH = {'año': 2, 'orden': 4, 'nombre': 'Sacateca'}

V = VACIO


def c(sg=V, sa=V, sm=V, re=V, rp=V, ti=V, **kw):
    return compas({'surdo_grave': sg, 'surdo_agudo': sa, 'surdo_medio': sm,
                   'redoblante': re, 'repique': rp, 'timbal': ti}, **kw)


# ------------------------------------------------------------------ Introducción
introduccion = [
    c(rp='x---x---x---x---', sm='--------------x-',
      texto='Introducción', dyn='mp'),
    c(rp='x---x---x---x---', sm='--------------x-', sg='o===============',
      sa='o==============='),
    c(rp='x---x---x---x---', sm='--------------x-', sg='o===============',
      sa='o==============='),
    c(rp='x---x---x---x---', sm='--------------x-', sg='o===============',
      sa='o==============='),
]

# ---------------------------------------------------------------------- Llamada
llamada = [
    c(rp='-xx-xxx--xx-xxx-', texto='Llamada (repique)', dyn='f'),
    c(rp='xxxxxxxx--------', texto='(compás de 2/4 completado con silencios)'),
]

# ------------------------------------------------------------------------ Toque
BASE = dict(sg='x-------x-------', sa='----x-------x---',
            sm='---------xx-xx-x', re='xx>>xx>>xx>>xx>>',
            rp='--xx--xx--xx--xx', ti='--oo--x=--oo--x=')

toque = [
    c(**BASE, repeat_begin=True, texto='Toque', dyn='mf'),
    c(**{**BASE, 'sm': '-xx-xx-xxxxx-xx-'}, repeat_end=True),
]

# --------------------------------------------------------------------- Llamada 2
llamada_2 = [
    c(ti='xxxoxxxoxxxoxxxo', texto='Llamada 2', dyn='mf'),
    c(ti='o=-o=-o=-o==x==='),
    c(rp='xxxxxxxxxxxxx===', sm='--------------x-',
      texto='Repique cresc. p → f', dyn='f'),
]

# ------------------------------------------------- Base de solos de timbal
BASE_SOLOS = dict(sg='x-------x-------', sa='----x-------x---',
                  sm='x=-t-x--t=--x-t-', re='xxxxxx>>xx>>xx>>')

base_solos = [
    c(**BASE_SOLOS, repeat_begin=True, repeat_end=True,
      texto='Base de solos de timbal', dyn='mf'),
]

# --------------------------------------------------------- Llamada de solos
llamada_solos = [
    c(sg='x==x--x=--------', sa='x==x--x=--------', sm='x==x--x=--------',
      texto='Llamada de solos (surdos)', dyn='f'),
    c(),
]

# ------------------------------------------------------------- Llamada final
# La llamada final de Sacateca es unísono estricto de todo el bloque.
llamada_final = [
    compas(unisono('-xx-xxx--xx-xxx-'), texto='Llamada final', dyn='f'),
    compas(unisono('xxxxxxxxx==-x=x-')),
]

SCORE = score(TITULO, 'La Chilinga', 88, INSTS, [
    seccion('Introducción', introduccion, 1),
    seccion('Llamada', llamada, 1),
    seccion('Toque', toque, 8),
    seccion('Llamada 2', llamada_2, 1),
    seccion('Base de solos de timbal', base_solos, 8),
    seccion('Llamada de solos', llamada_solos, 1),
    seccion('Llamada final', llamada_final, 1),
])
