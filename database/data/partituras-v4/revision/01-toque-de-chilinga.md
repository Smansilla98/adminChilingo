# Revisión — Toque de Chilinga (figuras = cuadernillo pág. 3 / PDF 6)

Fuente: `toque-de-chilinga-cuadernillo.pdf` / `hi/pdf-06.png` + hoja Equivalencias.

## LLAMADA INICIAL Y FINAL (Todos)

El PDF arranca con **semicorcheas**, no con corcheas. Compases 1 y 3 son inversos.

| Compás | Figuras |
|---|---|
| 1 | (4 semis + 2 corcheas) × 2 → `xxxxx=x=xxxxx=x=` |
| 2 | sil. negra · 2 corcheas · sil. negra · 2 corcheas → `----x=x=----x=x=` |
| 3 | (2 corcheas + 4 semis) × 2 → `x=x=xxxxx=x=xxxx` |
| 4 | igual al 2 |

## TOQUE (×8)

| Instrumento | Figuras |
|---|---|
| Surdo Grave | negras 1 y 3 |
| Surdo Agudo | negras 2 y 4 |
| Surdo Medio | (2 corcheas + negra) × 2 |
| Redoblante / Repique | 16 semis, acento en 1ª de cada tiempo |
| Timbal | (sil. negra + 2 corcheas) × 2 |

## LLAMADA INTERMEDIA (×4)

- Redo/Repi: misma frase que la llamada
- Surdos: (sil. negra + 2 corcheas) × 2

## Render (Equivalencias + toque)

- Compás **C**
- Clave de percusión
- Cabeza en la **línea del medio** (`b/4`)
- **Plicas abajo** (hoja Equivalencias); en Redo+Repi, voz de arriba / abajo
- Barras por negra (2 corcheas / 4 semis)
- Sin bracket / barra de sistema
- Solo voces que tocan en la sección

```bash
python3 database/data/partituras-v4/generar.py
php artisan partituras:bootstrap --force
```
