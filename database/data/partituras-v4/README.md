# Partituras del Cuadernillo de Toques (modelo v4)

Los **26 toques** del *Cuadernillo de Toques de La Chilinga* (Recopilación:
Luciano Molina - Pablo Cuffia, Bloque Lunes Saavedra) transcriptos al modelo v4
del editor de partituras.

Estos JSON son la fuente que carga `database/seeders/PartiturasEjemploSeeder.php`
en `programa_ritmos.medios['partitura_score']` (sobreescribe lo que haya).

## Cómo se generan

```bash
python3 database/data/partituras-v4/generar.py          # regenera los 26 JSON + manifest.json
php artisan db:seed --class=PartiturasEjemploSeeder     # los carga en la base
```

- `dsl.py` — DSL de grilla: cada compás se escribe como una línea de tokens por
  instrumento (`-` silencio, `=` prolonga, `x` nota, `>` acentuado, `c` chapa,
  `t` tapado, `r` presionado, `o` abierto, `s` slap, `p` palma, `d` dedo,
  `a` agudo, `f` flam; `3(xxx)` / `6(xxxxxx)` grupos irregulares).
  Valida los ticks de cada compás: si una voz no suma el compás completo, falla.
- `toques/NN_slug.py` — un módulo por toque, con `TITULO`, `MATCH`
  (año + orden + nombre del ritmo en `ProgramaRitmosSeeder`) y `SCORE`.
- `generar.py` — importa los módulos, valida y escribe `NN-slug.json` +
  `manifest.json`.

## Modelo

- Modelo v4 del editor (`resources/js/partitura/model.js` +
  `app/Support/PartituraScore.php`). TPQ = 48.
- Una sola `timeSignature` por partitura. Los toques en 6/8 (Malamakuá y
  Solo de Surdos) usan grilla de 12 semicorcheas; los compases de 2/4 o de 4/4
  intercalados en toques de otro compás se escriben adaptados y se aclara en el
  campo `texto` del compás.
- Instrumentos: `surdo_grave`, `surdo_agudo`, `surdo_medio`, `redoblante`,
  `repique`, `timbal` (más `agogo` para las Claves). En Muñequitos I,
  "Surdo Base" = `surdo_grave` y "Surdo Melodía" = `surdo_agudo`.
- Cabezas del cuadernillo: óvalo/blanca = `abierto`, negra = `nota`,
  rombo = `palma`, cruz = `tapado`.

## Criterio de transcripción

Transcripción visual página por página del PDF. Los pasajes que el escaneo no
permite leer con certeza (llamadas y cortes en fusas/sextillos, solos de timbal,
redoblante en fusas continuas) están transcriptos con la **lectura más probable**,
sin marcas de duda: son aproximaciones y se corrigen directamente en el editor,
que guarda el cambio en la base de datos. Puntos a revisar con oído:
llamadas densas de Iyesá, Chiruda, La Meta, Muñequitos I y Toque a Oxosi, y los
timbales de Samba Reggae.

## Los 26 toques

| Archivo | Título | Ritmo del programa | Compás | Estructura |
|---|---|---|---|---|
| `01-toque-de-chilinga.json` | Toque de Chilinga | 1° / 1 — Ritmo Chilinga | 4/4 | 3 partes / 7 compases |
| `02-marcha-camion.json` | Marcha Camión | 1° / 3 — Marcha Camión | 4/4 | 7 partes / 16 compases |
| `03-ochosi.json` | Ochosi | 1° / 2 — Ochosi | 4/4 | 4 partes / 6 compases |
| `04-murga-en-comparsa.json` | Murga en Comparsa | 1° / 10 — Murga en Comparsa | 4/4 | 3 partes / 7 compases |
| `05-toque-de-rap.json` | Toque de Rap | 1° / 8 — Rap - Murga | 4/4 | 4 partes / 9 compases |
| `06-candombe-argentino.json` | Candombe Argentino | 2° / 2 — Candombe Argentino | 4/4 | 3 partes / 4 compases |
| `07-samba-reggae-tradicional.json` | Samba Reggae Tradicional | 1° / 6 — Samba Reggae I y II | 4/4 | 5 partes / 11 compases |
| `08-samba-reggae-contemporaneo.json` | Samba Reggae Contemporáneo | 1° / 11 — Samba Reggae Contemporáneo | 4/4 | 4 partes / 5 compases |
| `09-iyesa.json` | Iyesá I, II y III | 1° / 9 — Ixesa I | 4/4 | 13 partes / 20 compases |
| `10-claves.json` | Claves | 2° / 8 — Claves | 4/4 | 12 partes / 18 compases |
| `11-rumba.json` | Rumba | 2° / 7 — Ritmo de Rumba | 4/4 | 5 partes / 12 compases |
| `12-chiruda.json` | Chiruda | 2° / 5 — Chiruda | 4/4 | 7 partes / 16 compases |
| `13-sacateca.json` | Sacateca | 2° / 4 — Sacateca | 4/4 | 7 partes / 16 compases |
| `14-buscando-a-coco.json` | Buscando a Coco | 3° / 1 — Buscando a Coco | 4/4 | 7 partes / 16 compases |
| `15-malambo-en-comparsa.json` | Malambo en Comparsa | 2° / 9 — Malambo en Comparsa | 6/8 | 3 partes / 12 compases |
| `16-toque-de-marcha.json` | Toque de Marcha | 1° / 7 — Toque de Marcha | 4/4 | 4 partes / 7 compases |
| `17-chilinga-ii.json` | Chilinga II | 5° / 2 — Chilinga II | 4/4 | 1 partes / 5 compases |
| `18-makuta.json` | Makuta | 4° / 9 — Ritmo de Makuta (Cuba) | 4/4 | 3 partes / 7 compases |
| `19-solo-de-timbales.json` | Solo de Timbales (Buscando a Coco) | 3° / 2 — Solo de timbales I | 4/4 | 1 partes / 19 compases |
| `20-solo-de-tambores.json` | Solo de Tambores (Chiruda) | 3° / 4 — Solo de redoblantes (Chiruda) | 4/4 | 1 partes / 19 compases |
| `21-mongo-kuta.json` | Mongo kutá | 3° / 6 — Mongokuta I | 4/4 | 7 partes / 24 compases |
| `22-la-meta.json` | La Meta | 4° / 2 — La Meta | 4/4 | 15 partes / 41 compases |
| `23-malamakua.json` | Malamakuá | 3° / 3 — Malamakua I | 6/8 | 5 partes / 12 compases |
| `24-solo-de-surdos.json` | Solo de Surdos (Malamakuá) | 3° / 10 — Solo de Surdos (Malamakuá) | 6/8 | 5 partes / 16 compases |
| `25-munequitos.json` | Muñequitos I | 4° / 5 — Muñequitos I | 4/4 | 10 partes / 40 compases |
| `26-toque-a-oxosi.json` | Toque a Oxosi | 4° / 6 — Oxosi II | 4/4 | 8 partes / 24 compases |
