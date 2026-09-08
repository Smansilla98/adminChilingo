#!/usr/bin/env python3
"""Valida JSON v4 del Cuadernillo (ticks por voz = capacidad del compás).

Uso:
  python3 database/data/partituras-v4/validar_v4.py
  python3 database/data/partituras-v4/validar_v4.py 09-iyesa.json 26-toque-a-oxosi.json
"""
import json
import os
import sys

BASE = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, BASE)

from generar import validar  # noqa: E402


def cargar_json(path):
    with open(path, encoding='utf-8') as fh:
        return json.load(fh)


def main(argv):
    args = argv[1:]
    if args:
        paths = []
        for a in args:
            p = a if os.path.isabs(a) else os.path.join(BASE, a)
            paths.append(p)
    else:
        paths = [
            os.path.join(BASE, f)
            for f in sorted(os.listdir(BASE))
            if f.endswith('.json') and f[0].isdigit()
        ]

    errores = 0
    for path in paths:
        if not os.path.isfile(path):
            print(f'{os.path.basename(path)}: NO EXISTE')
            errores += 1
            continue
        score = cargar_json(path)
        problemas = validar(score)
        nombre = os.path.basename(path)
        if problemas:
            print(f'{nombre}: {len(problemas)} PROBLEMAS')
            for p in problemas:
                print(f'    ! {p}')
            errores += len(problemas)
        else:
            print(f'{nombre}: OK  tempo={score.get("tempo")} '
                  f'{score["timeSignature"]["num"]}/{score["timeSignature"]["den"]}')
    return 1 if errores else 0


if __name__ == '__main__':
    sys.exit(main(sys.argv))
