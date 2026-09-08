# MIDI mapping

El PDF **no imprime números MIDI**. Toda nota MIDI es **convención del proyecto** (GM / GM2, canal 10) para interoperar con DAW y MusicXML. No es dato del cuadernillo.

Cadena:

```
PARTITURA (PDF)
  → EVENTO (instrumento + stroke + tick)
  → MIDI (nota GM2, velocity)
```

distinto de:

```
MIDI → SAMPLE → AUDIO
```

La nota MIDI no define el timbre de la escuela.

TPQ del editor = **48** (1 negra). Canal **10**.

Fuente de números: `resources/js/partitura/instruments.js` → `MIDI_POR_GOLPE` / `midiDeGolpe()`.

| MIDI | Instrumento | Stroke | Source page (archivo) | Source name | Sample | Status |
| ---: | --- | --- | ---: | --- | --- | --- |
| 87 | surdo_grave | nota / acentuado | 5 | SURDOS … Nota | surdo_grave_normal | CONVENTION |
| 86 | surdo_grave | tapado | 5 | Golpe Tapado | surdo_grave_tapado | CONVENTION |
| 37 | surdo_* / redo / repi | chapa | 5 | Golpe de Chapa | *_chapa | CONVENTION |
| 41 | surdo_medio | nota | 5 | SURDOS | surdo_medio_normal | CONVENTION |
| 43 | surdo_agudo | nota | 5 | SURDOS | surdo_agudo_normal | CONVENTION |
| 38 | redoblante | nota / flam | 5 | REDOBLANTE Nota | redoblante_normal | CONVENTION |
| 40 | redoblante | acentuado | 5 | Golpe Acentuado | redoblante_acentuado | CONVENTION |
| 37 | redoblante | chapa / tapado | 5 | Golpe de Chapa | redoblante_chapa | CONVENTION |
| 43 | redoblante | agudo | 5 y 60 | triángulo (mismo glifo que REPIQUE Golpe Agudo) | `redoblante_agudo` (proxy CC0) | CONVENTION + sample AVAILABLE |
| 40 | repique | nota / acentuado | 5 | REPIQUE | repique_normal / _acentuado | CONVENTION |
| 43 | repique | agudo | 5 | Golpe Agudo (triángulo) | repique_agudo | CONVENTION + sample AVAILABLE |
| 66 | timbal | abierto / acentuado / nota | 5 | Golpe Abierto | timbal_abierto | CONVENTION |
| 65 | timbal | slap | 5 | Golpe de Slap | timbal_slap | CONVENTION |
| 64 | timbal | presionado / tapado | 5 | Golpe Presionado | timbal_presionado | CONVENTION |
| 39 | timbal | palma | 5 | Golpe de Palma | timbal_palma | CONVENTION |
| 62 | timbal | dedo | 5 | Golpe de Dedo | timbal_dedo | CONVENTION |
| 67 | agogo | nota / acentuado | — | no en Nomenclatura p.2 | agogo_normal | CONVENTION; instrumento PENDING_HUMAN_REVIEW en hoja |
| 68 | agogo | tapado | — | — | agogo_tapado | CONVENTION |
| 39 | palmas | nota / acentuado | — | no en Nomenclatura p.2 | palmas_normal | CONVENTION |

Velocity: `round(96 * gain_del_golpe * volume)`. No está en el PDF.

Toque a Oxosi, Final, pentagrama «Redoblante y Repique»: cabezas **triángulo** = `agudo` en **ambos** instrumentos al mismo tick.
