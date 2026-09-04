# Revisión — Toque de Chilinga (figuras = cuadernillo)

Fuente: `toque-de-chilinga-cuadernillo.pdf` a 300 dpi.

## LLAMADA INICIAL Y FINAL

| Compás | Figuras |
|---|---|
| 1 | 16 semicorcheas (4 grupos de 4) |
| 2 | 2 corcheas · sil. negra · 8 semis |
| 3 | sil. negra · 2 corcheas · sil. negra · 2 corcheas |

## Render estilo cuadernillo

- Compás **C** (no 4/4 numérico)
- Clave de percusión
- Plicas arriba
- Barras por negra (4 semis / 2 corcheas)
- Altura por instrumento (grave abajo…)
- Solo pentagramas que tocan en la sección (llamada → solo Todos)

```bash
python3 database/data/partituras-v4/generar.py
php artisan partituras:bootstrap --force
```
