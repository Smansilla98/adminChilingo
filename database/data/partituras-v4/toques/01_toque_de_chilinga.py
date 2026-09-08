"""Toque de Chilinga — Cuadernillo pág. 3 (PDF pág. 6).

Llamada en pantalla: 3 compases, agudos llaman / graves responden
(seña de escuela: ta-ca-tá | ta-ca-tá | ta-ca-tá, pum-pum).
El Toque sigue las figuras del PDF.
"""
from dsl import INSTS, SURDOS, VACIO, compas, score, seccion, tutti

TITULO = 'Toque de Chilinga'
MATCH = {'año': 1, 'orden': 1, 'nombre': 'Ritmo Chilinga'}
PDF_PAGES = [6]

# ta-ca-tá = 2 corcheas + negra acentuada (tiempos 1–2)
# pum pum  = 2 negras (tiempos 3–4)
AGUDOS = 'x=x=>===--------'
GRAVES = '--------x===x==='


def frase_llamada(primero=False, ultimo=False, texto=None):
    return compas({
        'redoblante': AGUDOS,
        'repique': AGUDOS,
        **tutti(GRAVES, SURDOS),
        'timbal': VACIO,
    }, repeat_begin=primero, repeat_end=ultimo, texto=texto)


llamada = [
    frase_llamada(),
    frase_llamada(),
    frase_llamada(),
]

# --- TOQUE (×8) — PDF pág. 6
# Grave: negras 1 y 3 · Agudo: negras 2 y 4
# Medio: (2 corcheas + negra) × 2
# Redo/Repi: 16 semis; acento en la 1ª de cada tiempo
# Timbal: (2 corcheas + sil. negra) × 2
toque = [
    compas({
        'surdo_grave': 'x===----x===----',
        'surdo_agudo': '----x===----x===',
        'surdo_medio': 'x=x=x===x=x=x===',
        'redoblante': '>xxx>xxx>xxx>xxx',
        'repique': '>xxx>xxx>xxx>xxx',
        'timbal': '----x=x=----x=x=',
    }, repeat_begin=True, repeat_end=True, texto='Toque'),
]

intermedia = [
    frase_llamada(primero=True),
    frase_llamada(),
    frase_llamada(ultimo=True),
]

SCORE = score(TITULO, 'La Chilinga', 88, INSTS, [
    seccion('Llamada inicial y final', llamada, 1, agrupar='agudos-graves'),
    seccion('Toque', toque, 8),
    seccion('Llamada intermedia', intermedia, 4, agrupar='agudos-graves'),
])
