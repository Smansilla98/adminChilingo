# Investigación sonora — La Chilinga

Fecha: 2026-09-08.

Este directorio **no** contiene audio de Spotify/YouTube. No hay MIDI oficial.
Los `.mid` de `midi/` son **MIDI GENERADO A PARTIR DE ANÁLISIS** del
*Cuadernillo de Toques* (JSON v4). El arreglo de disco es otra obra.

## Correspondencia

```
cuadernillo PDF  →  toques/*.py  →  NN-slug.json  →  investigacion/midi/NN-slug.mid
                         ↕
                    validar_v4.py
```

El JSON pedagógico **no** es onset-transcripción del álbum. Un mismo título
(p. ej. *Makuta*) puede coincidir de nombre y diferir en fills, canto, tempo
y instrumentación de estudio.

## Qué se pudo determinar sin audio autorizado

| Dato | Disco | Cuadernillo |
| --- | --- | --- |
| Título / tracklist | sí (fuentes públicas) | sí |
| Duración de pista | a veces | no aplica |
| BPM / swing / fills de la grabación | **desconocido** | tempo pedagógico 80–90 |
| Instrumentos de escuela | no afirmar desde el mix | sí, en el PDF |
| Patrón | **no inventar** si no hay audio | sí, transcripción visual |

## Validación

```
python3 database/data/partituras-v4/validar_v4.py
python3 database/data/partituras-v4/midi_from_v4.py
```

## Archivos

- `catalogo.json` — fichas de las 16 referencias pedidas.
- `midi/*.mid` — MIDI generado (canal 10, TPQ=48). Meta-texto: no oficial.
- MusicXML: exportar desde el editor; no se duplica acá.

## Caso Oxosi

En `26-toque-a-oxosi.json` el Final usa golpe `agudo` (`a`) en **redoblante y
repique**: `----3(-a-)----3(-a-)`. El patrón se conserva.

- Sample `repique_agudo` y `redoblante_agudo` están en `MAPA_SAMPLES` (proxy CC0).
  El golpe `agudo` no se sustituye por `nota`.
