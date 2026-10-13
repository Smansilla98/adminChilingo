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

Retranscripto del PDF ampliado el 2026-10-13.

| Instrumento | Figuras |
|---|---|
| Surdo Grave | negras 1 y 3 |
| Surdo Agudo | negras 2 y 4 |
| Surdo Medio | corchea + 2 semis · negra · 2 corcheas · negra |
| Redoblante / Repique | (corchea + 2 semis) × 4, acento en la corchea y en la última semi |
| Timbal | (sil. corchea + 2 semis slap · sil. corchea + 2 semis abiertas) × 2 |

## Render

- Compás **C**
- Clave de percusión
- Cabeza en el **2º espacio** (`a/4`)
- **Plicas arriba** (cuadernillo), también en Agudos / Redo+Repi
- Barras por negra (2 corcheas / 4 semis)
- Llamada: `section.agrupar = agudos-graves`

```bash
python3 database/data/partituras-v4/generar.py
php artisan partituras:bootstrap --force
```
