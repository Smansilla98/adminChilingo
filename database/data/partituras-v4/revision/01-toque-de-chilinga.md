# Revisión — Toque de Chilinga (figuras = cuadernillo pág. 3 / PDF 6)

Fuente: `Toques_chilinga_compressed.pdf` pág. 6 + seña de escuela (Santiago, 2026-09-08).

## LLAMADA INICIAL Y FINAL / INTERMEDIA — 3 compases

Seña: `ta-ca-tá | ta-ca-tá | ta-ca-tá` (agudos llaman) y `pum-pum` (graves responden).

En pantalla: dos pentagramas, **Agudos (llaman)** y **Graves (responden)**.

| Voz | Sílabas | Figuras (por compás) | DSL |
|---|---|---|---|
| Agudos (redo + repi) | ta · ca · **tá** | 2 corcheas + negra acentuada, tiempos 1–2 | `x=x=>===--------` |
| Graves (surdos) | pum · pum | 2 negras, tiempos 3–4 | `--------x===x===` |

Tres compases iguales. El audio de graves dispara los tres surdos; el pentagrama muestra una sola línea.

El PDF imprime la llamada inicial en unísono `Todos` con semis densas. En pantalla manda la seña (agudos / graves). El **Toque** sigue el PDF.

## TOQUE (×8)

| Instrumento | Figuras |
|---|---|
| Surdo Grave | negras 1 y 3 |
| Surdo Agudo | negras 2 y 4 |
| Surdo Medio | (2 corcheas + negra) × 2 |
| Redoblante / Repique | 16 semis, acento en 1ª de cada tiempo |
| Timbal | (sil. negra + 2 corcheas) × 2 |

## Render

- Compás **C**
- Clave de percusión
- Cabeza en la **línea del medio** (`b/4`)
- **Plicas abajo** (Equivalencias); en Redo+Repi del toque, voz de arriba / abajo
- Barras por negra (2 corcheas / 4 semis)
- Llamada: `section.agrupar = agudos-graves`

```bash
python3 database/data/partituras-v4/generar.py
php artisan partituras:bootstrap --force
```
