#!/usr/bin/env python3
"""Genera database/data/partituras-v4/*.json desde los módulos de toques/.

Cada módulo `toques/NN_slug.py` define:
  TITULO — título de la partitura
  MATCH  — cómo encontrar el toque en programa_ritmos (año/orden/nombre)
  SCORE  — partitura en modelo v4 (armada con el DSL de dsl.py)

Uso: python3 database/data/partituras-v4/generar.py
"""
import importlib.util
import json
import os
import sys

BASE = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, BASE)

TPQ = 48


def cargar(path):
    nombre = os.path.splitext(os.path.basename(path))[0]
    spec = importlib.util.spec_from_file_location(nombre, path)
    mod = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(mod)
    return mod


def ticks(n):
    base = {'w': TPQ * 4, 'h': TPQ * 2, 'q': TPQ, '8': TPQ // 2,
            '16': TPQ // 4, '32': TPQ // 8}[n['dur']]
    t = base * {0: 1, 1: 1.5, 2: 1.75}[n['dots']]
    if n.get('tuplet'):
        t = t * n['tuplet']['den'] / n['tuplet']['num']
    return round(t)


def validar(score):
    cap = TPQ * 4 * score['timeSignature']['num'] // score['timeSignature']['den']
    insts = {i['id'] for i in score['instruments']}
    problemas = []
    for si, sec in enumerate(score['sections'], 1):
        for mi, m in enumerate(sec['measures'], 1):
            for inst, notas in m['voces'].items():
                if inst not in insts:
                    problemas.append(f'{sec["name"]} c{mi}: instrumento desconocido {inst}')
                t = sum(ticks(n) for n in notas)
                if t != cap:
                    problemas.append(f'{sec["name"]} c{mi} {inst}: {t} ticks (esperado {cap})')
    return problemas


def main():
    dir_toques = os.path.join(BASE, 'toques')
    archivos = sorted(f for f in os.listdir(dir_toques) if f.endswith('.py') and f[0].isdigit())
    manifest, errores = [], 0

    for f in archivos:
        mod = cargar(os.path.join(dir_toques, f))
        score = mod.SCORE
        problemas = validar(score)
        slug = f[:-3].replace('_', '-')
        out = os.path.join(BASE, f'{slug}.json')
        with open(out, 'w', encoding='utf-8') as fh:
            json.dump(score, fh, ensure_ascii=False, indent=2)
        compases = sum(len(s['measures']) for s in score['sections'])
        golpes = sum(1 for s in score['sections'] for m in s['measures']
                     for v in m['voces'].values() for n in v if not n['rest'])
        estado = 'OK' if not problemas else f'{len(problemas)} PROBLEMAS'
        print(f'{slug}.json: {len(score["sections"])} partes, {compases} compases, '
              f'{golpes} golpes — {estado}')
        for p in problemas:
            print(f'    ! {p}')
            errores += 1
        manifest.append({
            'file': f'{slug}.json',
            'title': mod.TITULO,
            'match': mod.MATCH,
            'pdf_pages': getattr(mod, 'PDF_PAGES', None),
        })

    with open(os.path.join(BASE, 'manifest.json'), 'w', encoding='utf-8') as fh:
        json.dump(manifest, fh, ensure_ascii=False, indent=2)
    print(f'\nmanifest.json: {len(manifest)} partituras, {errores} problemas')
    return 1 if errores else 0


if __name__ == '__main__':
    sys.exit(main())
