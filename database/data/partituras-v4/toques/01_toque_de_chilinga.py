"""Toque de Chilinga — Cuadernillo pág. 3 (PDF pág. 6).

Revisión aplicada: ver `revision/01-toque-de-chilinga.md`.
- La llamada va escrita como en el cuadernillo: 4 compases literales, sin barras
  de repetición y sin dinámica impresa.
- Voz "Todos" = unísono estricto (instrumento virtual `todos`), no réplica en los
  seis pentagramas.
"""
from dsl import INSTS, SURDOS, VACIO, compas, score, seccion, tutti, unisono

TITULO = 'Toque de Chilinga'
MATCH = {'año': 1, 'orden': 1, 'nombre': 'Ritmo Chilinga'}
PDF_PAGES = [6]

# --- LLAMADA INICIAL Y FINAL — voz "Todos", 4 compases literales
# T1..T4 = xx-x (silencio en la 3ª semicorchea de cada tiempo)
LL_A = 'xx-x xx-x xx-x xx-x'.replace(' ', '')
LL_B = 'x===--xx x===----'.replace(' ', '')

llamada = [
    compas(unisono(LL_A), texto='Todos — revisar con la escuela: T4'),
    compas(unisono(LL_B)),
    compas(unisono(LL_A)),
    compas(unisono(LL_B)),
]

# --- TOQUE (1 compás por instrumento)
toque = [
    compas({
        'surdo_grave': 'x===----x===----',
        'surdo_agudo': '----x===----x===',
        'surdo_medio': 'x=xxx===x=x=x===',
        'redoblante': '>xx>>xx>>xx>>xx>',
        'repique': '>xx>>xx>>xx>>xx>',
        'timbal': '--oo--ss--oo--ss',
    }, repeat_begin=True, repeat_end=True, texto='Toque'),
]

# --- LLAMADA INTERMEDIA — 4 compases (×4 va en repeatX de la sección)
INT_RE_1 = 'xxxxxxxxx===--xx'
INT_RE_4 = 'xxxxxxxxx===----'
INT_SU = '----------x=x==='

intermedia = [
    compas({**tutti(INT_SU, SURDOS), 'redoblante': INT_RE_1, 'repique': INT_RE_1,
            'timbal': VACIO}, repeat_begin=True, texto='Llamada intermedia'),
    compas({**tutti(INT_SU, SURDOS), 'redoblante': INT_RE_1, 'repique': INT_RE_1,
            'timbal': VACIO}),
    compas({**tutti(INT_SU, SURDOS), 'redoblante': INT_RE_1, 'repique': INT_RE_1,
            'timbal': VACIO}),
    compas({**tutti(INT_SU, SURDOS), 'redoblante': INT_RE_4,
            'repique': INT_RE_4, 'timbal': VACIO}, repeat_end=True),
]

SCORE = score(TITULO, 'La Chilinga', 88, INSTS, [
    seccion('Llamada inicial y final', llamada, 1),
    seccion('Toque', toque, 8),
    seccion('Llamada intermedia', intermedia, 4),
])
