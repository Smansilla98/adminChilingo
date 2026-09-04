"""Sacateca — Cuadernillo págs. 26-27 (PDF págs. 29-30).

Transcripción literal del PDF (figuras = escritura de la escuela).
"""
from dsl import INSTS, VACIO, compas, score, seccion, tutti, unisono

TITULO = 'Sacateca'
MATCH = {'año': 2, 'orden': 4, 'nombre': 'Sacateca'}
PDF_PAGES = [29, 30]

V = VACIO


def c(sg=V, sa=V, sm=V, re=V, rp=V, ti=V, **kw):
    return compas({'surdo_grave': sg, 'surdo_agudo': sa, 'surdo_medio': sm,
                   'redoblante': re, 'repique': rp, 'timbal': ti}, **kw)


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

# Llamada repique: frase sincopada del PDF (sin xx-x inventado)
LL_RP = '-x=x=xxx=-x=xxx='
llamada = [
    c(rp=LL_RP, texto='Llamada (repique) — … revisar con la escuela', dyn='f'),
    c(rp='xxxxxxxx--------', texto='(compás de 2/4 completado con silencios)'),
]

BASE = dict(sg='x-------x-------', sa='----x-------x---',
            sm='----------x=x=x=', re='xx>>xx>>xx>>xx>>',
            rp='--xx--xx--xx--xx', ti='--oo--x=--oo--x=')

toque = [
    c(**BASE, repeat_begin=True, texto='Toque', dyn='mf'),
    c(**{**BASE, 'sm': '-x=x=-x=xxxxx=x='}, repeat_end=True,
      texto='Toque — … revisar con la escuela (surdo medio)'),
]

llamada_2 = [
    c(ti='xxxoxxxoxxxoxxxo', texto='Llamada 2 — … revisar con la escuela', dyn='mf'),
    c(ti='o=-o=-o=-o==x==='),
    c(rp='xxxxxxxxxxxxx===', sm='--------------x-',
      texto='Repique cresc. p → f', dyn='f'),
]

BASE_SOLOS = dict(sg='x-------x-------', sa='----x-------x---',
                  sm='x=-t-x--t=--x-t-', re='xxxxxx>>xx>>xx>>')

base_solos = [
    c(**BASE_SOLOS, repeat_begin=True, repeat_end=True,
      texto='Base de solos de timbal — … revisar con la escuela', dyn='mf'),
]

llamada_solos = [
    c(sg='x==x--x=--------', sa='x==x--x=--------', sm='x==x--x=--------',
      texto='Llamada de solos (surdos)', dyn='f'),
    c(),
]

llamada_final = [
    compas(unisono(LL_RP), texto='Llamada final — … revisar con la escuela', dyn='f'),
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
