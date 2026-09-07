# Samples de percusión — La Chilinga

Ruta pública: `/sounds/perc/` → `public/sounds/perc/`

Formato: **WAV** 44.1 kHz estéreo.
Nomenclatura: `{instrumento}_{articulacion}.wav`

El golpe pleno se llama `nota` en el modelo y el archivo usa `normal`.

## Estado actual

26 one-shots, 1:1 con `MAPA_SAMPLES` (incluye agogó y palmas).

Banco extraído de **FluidR3 GM** (Frank Wen), el soundfont GM libre que empaquetan
Debian/Ubuntu como `fluid-soundfont-gm` (MIT). Cada hit se recortó, afinó y
normalizó al vocabulario de la escuela (surdo grave/medio/agudo, caixa, repique,
timbal, agogó, palmas).

No es la batería grabada de La Chilinga: es el kit GM latino más cercano que se
puede redistribuir. Cuando haya one-shots propios, se tiran encima con el mismo
nombre de archivo.

Regenerar (hace falta el `.sf2`, ~144 MB, no va en el repo):

```
python3 scripts/build-perc-kit.py /ruta/FluidR3_GM_GS.sf2
```

Fuente del SF2: [Internet Archive — FluidR3 GM+GS](https://archive.org/details/fluidr3-gm-gs).

## Qué se usó de FluidR3

| Archivo | Sample GM de origen | Ajuste |
|---|---|---|
| `surdo_grave_normal` | Tom Floor | −4 semitonos, lowpass |
| `surdo_grave_tapado` | Std Kick 7 | grave, decay corto |
| `surdo_grave_chapa` | Sticks | highpass |
| `surdo_medio_*` | Tom Floor / Kick / Rim Tap | afinación media |
| `surdo_agudo_*` | Tom Low / Kick / Woodblock | afinación aguda |
| `redoblante_normal` | Orch Snare | — |
| `redoblante_acentuado` | Power Snare 1 | — |
| `redoblante_chapa` | Rim Tap | — |
| `repique_normal` / `_acentuado` | Power Snare 2 | +3 / +4 semitonos |
| `repique_chapa` | Sticks | más agudo |
| `repique_agudo` | 808 Snare 1 | +5 semitonos |
| `timbal_abierto` | High Timbale | — |
| `timbal_presionado` | Low Timbale | choke |
| `timbal_slap` | Bongo Rim | — |
| `timbal_dedo` | High Conga | más agudo |
| `timbal_palma` / `palmas_*` | Clap | — |
| `agogo_normal` / `_acentuado` | High Agogo | — |
| `agogo_tapado` | Low Agogo | choke |

El MIDI exportado usa el mapa GM2: surdo grave **87 open / 86 mute**, caixa **38**,
repique **40**, timbal **66/65**, palmas **39**, agogó **67/68**. Así un DAW con
GeneralUser GS o FluidR3 dispara los mismos instrumentos.

## Lo que no se pudo meter (gratis, pero no descargable acá)

- **Freesound CC0** (caixa de Sassaby, repinique de BeppeB): el CDN responde 403
  sin cuenta. Siguen siendo la mejor fuente de one-shots de samba; hay que
  bajarlos a mano y renombrarlos.
- **House of Loop / Noiiz tasters**: piden cuenta.
- **JasperCodes Brazilian Bateria .sf2**: mezcla samples de Splice y otros packs
  comerciales; no se incorpora.

Atribución FluidR3: Frank Wen, MIT.
