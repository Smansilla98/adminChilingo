# Partituras de ejemplo (modelo v4)

Estos JSON son la fuente de los toques de ejemplo que carga
`database/seeders/PartiturasEjemploSeeder.php` en `programa_ritmos.medios['partitura_score']`.

- Formato: modelo v4 del editor de partituras (`resources/js/partitura/model.js` +
  `app/Support/PartituraScore.php`). TPQ = 48, compás 4/4.
- `generar.py` reconstruye los JSON desde un DSL de grilla de semicorcheas
  (`python3 database/data/partituras-v4/generar.py`, escribe en esta carpeta).
- Transcripción tomada del *Cuadernillo de Toques de La Chilinga*:
  - `01-toque-de-chilinga.json` → pág. 3 (llamada inicial/final, toque, llamada intermedia).
  - `02-marcha-camion.json` → págs. 4-5 (llamada, Base 1, Base 2 con sextillo).
- Las **llamadas** están transcriptas de forma aproximada (el escaneo del cuadernillo
  no permite leer con certeza los grupos de fusas). Las **bases** sí siguen el original.
  Cualquier corrección se hace directamente en el editor de partitura y queda guardada
  en la base de datos.
- La introducción de Marcha Camión mezcla 4/4 y 2/4; el modelo v4 usa un único compás
  por partitura, así que esa parte quedó fuera de la transcripción de ejemplo.
