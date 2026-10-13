"""Solo de Tambores (Chiruda) — Cuadernillo pág. 36 (PDF pág. 39).

El cuadernillo no nombra instrumento: va en la voz «Todos» (unísono).
Los compases de 6/8 se escriben como 6 corcheas + silencio de negra.
"""
from cuad import hoja
from dsl import SURDOS, score, seccion, unisono

TITULO = 'Solo de Tambores (Chiruda)'
MATCH = {'año': 3, 'orden': 4, 'nombre': 'Solo de redoblantes (Chiruda)'}
PDF_PAGES = [39]

INST = SURDOS + ['redoblante', 'repique']
c = hoja(INST)
T68 = 'Compás de 6/8 en el cuadernillo'


def u(pat, **kw):
    return c(**unisono(pat), **kw)


def seis(pat):
    """Seis corcheas de un compás de 6/8 (`>` acento, `x` nota, `-` silencio)."""
    return u(''.join(ch + ('=' if ch != '-' else '-') for ch in pat) + '----', texto=T68)


TERNARIO = '>xx>xx>xx>xx>xxx'
PARES = 'x>x>x>x>x>x>>xxx'
UNO = 'x>xxxxxxxxxxxxxx'
CUATRO = 'x>xx' * 4
CHAPAS = 'ccc>xx>xxx>xx>xx'      # tres cruces (chapa) acentuadas al empezar
A, B = '>>xx>>', 'xx>>xx'

solo = [
    u(TERNARIO), u(PARES),
    u(PARES), u(UNO),
    u(UNO), u(CUATRO),
    u(UNO), u(UNO),
    u(UNO), u(CUATRO),
    u(TERNARIO), seis(A), seis(B),
    seis(A), seis(B), seis(A), seis(B),
    seis(A), seis(B),
    u('>=====----------', texto=T68), u('----------------', texto=T68),
    u(CHAPAS), u(CHAPAS),
    u(CHAPAS), u(CHAPAS),
    u('xxxxxxxxxxxxxxxx', dyn='p', rev='p < f (regulador) hasta la redonda'),
    u('x===============', dyn='f'),
]

SCORE = score(TITULO, 'La Chilinga', 86, INST, [
    seccion('Solo de tambores', solo, 1),
])
