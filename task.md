# Transcripción de los 26 toques del cuadernillo a modelo v4

Decisiones del usuario:
- Transcripción **visual** página por página del PDF (no convertir v3).
- Pasajes dudosos: transcribir lo más probable, sin marcas de "revisar".
- Seeder que carga los 26 y **sobreescribe** `medios.partitura_score` si ya existe.

Fuente: /home/user/Attachments/Toques_chilinga_compressed_keKpc-.pdf
Páginas PNG (100 dpi, gris): /home/user/pdf2/pg-06.png … pg-61.png

Estructura de salida:
- `database/data/partituras-v4/dsl.py` — helpers del DSL de grilla
- `database/data/partituras-v4/toques/NN-slug.py` — un módulo por toque, define SCORE + MATCH
- `database/data/partituras-v4/generar.py` — recorre toques/, escribe JSON + manifest.json
- `database/seeders/PartiturasEjemploSeeder.php` — carga los 26 (sobreescribe)

## Mapa PDF -> toque (del manifest v3)
| # | toque | pdf pages | match |
|---|---|---|---|
| 01 | Toque de Chilinga | 6 | año1 orden1 Ritmo Chilinga |
| 02 | Marcha Camión | 7-9 | año1 orden3 |
| 03 | Ochosi | 10 | año1 orden2 |
| 04 | Murga en Comparsa | 11 | año1 orden10 |
| 05 | Toque de Rap | 12-13 | año1 orden8 Rap - Murga |
| 06 | Candombe Argentino | 14 | año2 orden2 |
| 07 | Samba Reggae Tradicional | 15-16 | año1 orden6 Samba Reggae I y II |
| 08 | Samba Reggae Contemporáneo | 17 | año1 orden11 |
| 09 | Iyesá I-II-III | 18-22 | año1 orden9 Ixesa I |
| 10 | Claves | 23 | año2 orden8 |
| 11 | Rumba | 24-25 | año2 orden7 Ritmo de Rumba |
| 12 | Chiruda | 26-28 | año2 orden5 |
| 13 | Sacateca | 29-30 | año2 orden4 |
| 14 | Buscando a Coco | 31-33 | año3 orden1 |
| 15 | Malambo en Comparsa | 34 | año2 orden9 |
| 16 | Toque de Marcha | 35 | año1 orden7 |
| 17 | Chilinga II | 36 | año5 orden2 |
| 18 | Makuta | 37 | año4 orden9 Ritmo de Makuta (Cuba) |
| 19 | Solo timbales (Buscando a Coco) | 38 | año3 orden2 Solo de timbales I |
| 20 | Solo tambores (Chiruda) | 39 | año3 orden4 |
| 21 | Mongo Kuta | 40-42 | año3 orden6 Mongokuta I |
| 22 | La Meta | 43-49 | año4 orden2 |
| 23 | Malamakua | 50-51 | año3 orden3 Malamakua I |
| 24 | Solo surdos (Malamakuá) | 52 | año3 orden10 |
| 25 | Muñequitos I | 53-57 | año4 orden5 |
| 26 | Toque a Oxosi | 58-61 | año4 orden6 Oxosi II |

## Progreso
- [x] 01 Toque de Chilinga (p.6)
- [x] 02 Marcha Camión (p.7-9)
- [x] 03 Ochosi (p.10)
- [x] 04 Murga en Comparsa (p.11)
- [x] 05 Toque de Rap (p.12-13)
- [x] 06 Candombe Argentino (p.14)
- [x] 07-26 hechos (los 26 toques generados y validados)
- [x] README v4 actualizado
- [x] MATCH verificados contra ProgramaRitmosSeeder
- [x] Verificación server-side: PartituraScore::normalizar() sin pérdida de compases ni golpes

Criterio: lectura visual por chunks (3 pentagramas), transcripción del patrón más probable, sin marcas de duda.
