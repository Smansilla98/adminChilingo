# Revisión — Toque de Chilinga (figuras = cuadernillo pág. 3 / PDF 6)

Fuente: `toque-de-chilinga-cuadernillo.pdf` / `hi/pdf-06.png`.

## LLAMADA INICIAL Y FINAL (Todos)

| Compás | Figuras |
|---|---|
| 1 | (2 corcheas + 4 semis) × 2 → `x=x=xxxxx=x=xxxx` |
| 2 | 2 corcheas · sil. negra · 2 corcheas · sil. negra → `x=x=----x=x=----` |
| 3 | (4 semis + 2 corcheas) × 2 → `xxxxx=x=xxxxx=x=` |
| 4 | igual al 2 |

## TOQUE (×8)

| Instrumento | Figuras |
|---|---|
| Surdo Grave | negras 1 y 3 |
| Surdo Agudo | negras 2 y 4 |
| Surdo Medio | (2 corcheas + negra) × 2 |
| Redoblante / Repique | 16 semis, acento en 1ª de cada tiempo |
| Timbal | (2 corcheas + sil. negra) × 2 |

## LLAMADA INTERMEDIA (×4)

- Redo/Repi: misma frase que la llamada
- Surdos: (sil. negra + 2 corcheas) × 2

## Render (todos los toques)

- Compás **C**
- Clave de percusión
- **1 línea** visible
- Plicas arriba, barras planas por negra
- Sin bracket / barra de sistema (no está en el cuadernillo)
- Solo voces que tocan en la sección

```bash
python3 database/data/partituras-v4/generar.py
php artisan partituras:bootstrap --force
```
