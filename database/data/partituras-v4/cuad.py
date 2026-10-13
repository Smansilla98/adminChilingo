"""Atajos para transcribir el cuadernillo compás por compás sobre `dsl.py`.

`hoja(insts)` devuelve `c(...)`: arma un compás, completa con silencio los
instrumentos que no figuran y acepta una grilla distinta por voz
(`timbal=('--x=...', 32)`).

`rev='...'` deja la marca «Revisar: ...» en el texto del compás (máx. 60
caracteres en el servidor): es lo que el escaneo no deja leer con certeza.
"""
from dsl import nid, voz

REV = 'Revisar: '
T24 = 'Compás de 2/4 en el cuadernillo'


def hoja(insts, num=4, den=4, grid=16):
    vacio = '-' * grid

    def c(texto=None, rev=None, rb=False, re=False, ending=None, dyn=None, **voces):
        if rev:
            texto = (REV + rev)[:60]
        out = {}
        for inst in list(insts) + [k for k in voces if k not in insts]:
            pat = voces.get(inst, vacio)
            g = grid
            if isinstance(pat, tuple):
                pat, g = pat
            out[inst] = voz(pat, dyn=dyn if inst in voces else None, grid=g, num=num, den=den)
        return {'id': nid('m'), 'repeatBegin': rb, 'repeatEnd': re, 'ending': ending,
                'texto': texto, 'voces': out}

    return c


def surdos(pat):
    return {'surdo_grave': pat, 'surdo_agudo': pat, 'surdo_medio': pat}


def agudos(pat):
    """Rótulo «Redoblante y Repique»."""
    return {'redoblante': pat, 'repique': pat}


def rtr(pat):
    """Rótulo «Redoblante, Timbal y Repique»."""
    return {'redoblante': pat, 'repique': pat, 'timbal': pat}


def p24(pat):
    """Compás de 2/4 del cuadernillo dentro de un toque en C: se completa con silencio."""
    return pat + '--------'
