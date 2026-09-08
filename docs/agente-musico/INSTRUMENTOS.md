# Instrumentos y golpes (PDF págs. impresas 1–2)

Fuente: PDF archivo 4 (Equivalencias) y archivo 5 (Nomenclatura). VIEWED 2026-09-08.

Nombres internos = ids del editor (`instruments.js`). No se inventó un id `snare`.

## Tabla maestra de instrumentos

| Instrumento | Nombre exacto PDF | Nombre interno | Tipo de golpe (hoja) | Representación gráfica (PDF) | MIDI | Sample | Fuente | Página archivo | Estado |
| --- | --- | --- | --- | --- | --- | --- | --- | ---: | --- |
| Surdo grave | SURDOS GRAVE, AGUDO Y MEDIO | `surdo_grave` | Nota | óvalo negro | CONVENTION (no en PDF) | `surdo_grave_normal` | Nomenclatura | 5 | VIEWED |
| Surdo grave | Golpe de Chapa | `surdo_grave` | chapa | cabeza X | CONVENTION | `surdo_grave_chapa` | Nomenclatura | 5 | VIEWED |
| Surdo grave | Golpe Tapado | `surdo_grave` | tapado | óvalo + guión encima | CONVENTION | `surdo_grave_tapado` | Nomenclatura | 5 | VIEWED |
| Surdo agudo | (mismo bloque SURDOS) | `surdo_agudo` | igual | igual | CONVENTION | `surdo_agudo_*` | Nomenclatura | 5 | VIEWED |
| Surdo medio | (mismo bloque SURDOS) | `surdo_medio` | igual | igual | CONVENTION | `surdo_medio_*` | Nomenclatura | 5 | VIEWED |
| Redoblante | REDOBLANTE | `redoblante` | Nota | óvalo negro | CONVENTION | `redoblante_normal` | Nomenclatura | 5 | VIEWED |
| Redoblante | Golpe Acentuado | `redoblante` | acentuado | óvalo + `>` debajo | CONVENTION | `redoblante_acentuado` | Nomenclatura | 5 | VIEWED |
| Redoblante | Golpe de Chapa | `redoblante` | chapa | X | CONVENTION | `redoblante_chapa` | Nomenclatura | 5 | VIEWED |
| Redoblante | Golpe Agudo | `redoblante` | agudo | **triángulo** (mismo glifo que repique) | CONVENTION | **MISSING** | Toque a Oxosi Final + Nomenclatura | 5 y 60 | TRANSCRIPTION=VALID glifo; AUDIO_SAMPLE_MISSING |
| Timbal | TIMBAL / Golpe Abierto | `timbal` | abierto | óvalo negro | CONVENTION | `timbal_abierto` | Nomenclatura | 5 | VIEWED |
| Timbal | Golpe de Slap | `timbal` | slap | círculo vacío | CONVENTION | `timbal_slap` | Nomenclatura | 5 | VIEWED (círculo, no X) |
| Timbal | Golpe de Palma | `timbal` | palma | cabeza pequeña / rombo — **ambigua en el escaneo** | CONVENTION | `timbal_palma` | Nomenclatura | 5 | PENDING_HUMAN_REVIEW |
| Timbal | Golpe Presionado | `timbal` | presionado | óvalo + guión debajo | CONVENTION | `timbal_presionado` | Nomenclatura | 5 | VIEWED |
| Timbal | Golpe de Dedo | `timbal` | dedo | X | CONVENTION | `timbal_dedo` | Nomenclatura | 5 | VIEWED |
| Repique | REPIQUE | `repique` | Nota | óvalo | CONVENTION | `repique_normal` | Nomenclatura | 5 | VIEWED |
| Repique | Golpe Acentuado | `repique` | acentuado | óvalo + `>` debajo | CONVENTION | `repique_acentuado` | Nomenclatura | 5 | VIEWED |
| Repique | Golpe de Chapa | `repique` | chapa | X | CONVENTION | `repique_chapa` | Nomenclatura | 5 | VIEWED |
| Repique | Golpe Agudo | `repique` | agudo | **triángulo** | CONVENTION | `repique_agudo` | Nomenclatura | 5 | VIEWED |
| Todos | Todos | `todos` | unísono | un pentagrama | n/a | expansión a reales | Toque de Chilinga | 6 | VIEWED |
| Agogó | no está en Nomenclatura p.2 | `agogo` | — | — | CONVENTION | `agogo_*` | aparece en toques (Iyesá, Makuta, Claves) | — | PENDING_HUMAN_REVIEW nombre en PDF |
| Palmas | no está en Nomenclatura p.2 | `palmas` | — | — | CONVENTION | `palmas_*` | Iyesá | — | PENDING_HUMAN_REVIEW nombre en PDF |

## Discrepancia Equivalencias vs Nomenclatura (no resolver)

- Equivalencias (archivo 4): todas las figuras en la **línea del medio**, plica abajo.
- Nomenclatura (archivo 5): algunas cabezas parecen en espacios distintos (surdo vs redo).

El renderer actual usa `pitch: b/4` y `stem: -1` para todos (criterio Equivalencias + Toque de Chilinga). **No se cambió** porque las dos hojas del PDF no coinciden. Estado: `PENDING_HUMAN_REVIEW`.

## Eventos simultáneos

Redoblante y Repique comparten pentagrama («Redoblante y Repique»). Un triángulo en ese pentagrama es **dos eventos** (redo + repi, golpe agudo) en el mismo tick, no un instrumento nuevo.
