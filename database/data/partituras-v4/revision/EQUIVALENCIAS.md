# Equivalencias (Cuadernillo de Toques — pág. 1)

Fuente obligatoria: hoja **Equivalencias** del PDF (`revision/equivalencias.png`,
`storage/app/cuadernillo-pages/hi/pdf-04.png`). Toda figura y silencio del editor
y del DSL se mide en **tiempos** (= negras en 4/4).

## Figuras

| Nombre (escuela) | Tiempos | Código v4 | Grilla 16 (4/4) | Barras (agrupación) |
|---|---|---|---|---|
| Redonda | 4 | `w` | `x===============` (16 celdas) | — |
| Blanca | 2 | `h` | `x=======` (8) | — |
| Negra | 1 | `q` | `x===` (4) | — |
| Corchea | 1/2 | `8` | `x=` (2) | de a **2** por tiempo |
| Semicorchea | 1/4 | `16` | `x` (1) | de a **4** por tiempo |
| Fusa | 1/8 | `32` | grilla 32: `x` | de a **8** por tiempo |

## Silencios

Misma duración que la figura homónima (`-` / `--` / `----` / etc. en el DSL).

| Nombre | Tiempos | Código | Grilla 16 |
|---|---|---|---|
| Redonda | 4 | `wr` | `----------------` |
| Blanca | 2 | `hr` | `--------` |
| Negra | 1 | `qr` | `----` |
| Corchea | 1/2 | `8r` | `--` |
| Semicorchea | 1/4 | `16r` | `-` |
| Fusa | 1/8 | `32r` | grilla 32: `-` |

## Reglas de escritura

1. **No inventar** silencios de semicorchea sueltos tipo `xx-x` si el PDF muestra corcheas o grupos de 4 semis.
2. Preferir la figura más grande que represente exactamente la duración (negra antes que 2 corcheas ligadas, corchea antes que 2 semis, etc.).
3. Barras: el renderer agrupa por **1 tiempo** (negra), igual que la hoja: 2 corcheas / 4 semis / 8 fusas.
4. TPQ = 48 ticks por negra → divisible por 2, 3 y 4 (corcheas, tresillos, semis).
