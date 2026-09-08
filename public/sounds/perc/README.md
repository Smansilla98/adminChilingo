# Samples de percusión — playback de partitura

Ruta pública: `/sounds/perc/` → `public/sounds/perc/`

Formato: **WAV** 44.1 kHz estéreo.
Nomenclatura: `{instrumento}_{articulacion}.wav`

El golpe pleno se llama `nota` en el modelo y el archivo usa `normal`.

## Estado actual

27 one-shots, 1:1 con `MAPA_SAMPLES` (incluye `redoblante_agudo` para el Final de Oxosi).

Banco **procesado** a partir de librerías CC0 (no FluidR3):

- [Versilian Community Sample Library](https://github.com/sgossner/VCSL) (CC0)
- [FreePats World Percussion 2020-09-05](https://freepats.zenvoid.org/Percussion/world-percussion.html) (CC0; darbuka, cajón, claves, palmas, bongos)

Cada hit se afinó, filtró y recortó al vocabulario de la escuela: surdo grave **corto**
(~0,20 s, no boom de batucada), caixa nítida, repique más agudo, golpe agudo = ping de
triángulo muted (el glifo del cuadernillo), agogó real, palmas.

**No es la batería grabada de La Chilinga.** No va a ser posible grabar esos audios con
la escuela por ahora. Cuando haya one-shots propios, se tiran encima con el mismo nombre
de archivo.

Regenerar (lo hace `start.sh` al arrancar si hay `ffmpeg`; las fuentes van en el repo):

```
python3 scripts/build-chilinga-kit.py scripts/chilinga-kit-src
```

Para no regenerar en el arranque: `PERC_KIT_REBUILD=0`.

El script viejo `scripts/build-perc-kit.py` sigue siendo el fallback FluidR3 (toms largos
y 808). No usarlo para el playback del bloque.

## Qué se usó

| Archivo | Origen | Ajuste |
|---|---|---|
| `surdo_grave_normal` | VCSL bass + darbuka doom + cajón | afinado abajo, lowpass, fade ~0,20 s |
| `surdo_grave_tapado` | bass + conga muted | decay ~0,10 s |
| `surdo_grave_chapa` | sidestick + claves | highpass |
| `surdo_medio_*` | tom / frame muted / sidestick | afinación media, corto |
| `surdo_agudo_*` | tom + frame / claves | más agudo, más corto |
| `redoblante_normal` / `_acentuado` | VCSL snare | highpass suave |
| `redoblante_chapa` | sidestick | — |
| `redoblante_agudo` | triángulo muted | ping de borde (Oxosi) |
| `repique_normal` / `_acentuado` | rope snare | +3 / +4 semitonos, highpass |
| `repique_chapa` | rope sidestick | — |
| `repique_agudo` | triángulo muted | un poco más agudo que redo |
| `timbal_abierto` | quinto + cowbell bajo | piel + ataque de metal |
| `timbal_slap` | conga slap | — |
| `timbal_presionado` | tumba muted | choke |
| `timbal_dedo` | bongo | más agudo |
| `timbal_palma` / `palmas_*` | hand clap FreePats | — |
| `agogo_*` | VCSL agogó | volumen bajo (no pincha el tutti) |

El MIDI exportado usa el mapa GM2: surdo grave **87 open / 86 mute**, caixa **38**,
repique **40**, timbal **66/65**, palmas **39**, agogó **67/68**. Así un DAW con
GeneralUser GS o FluidR3 dispara instrumentos *aproximados*; el timbre de escuela
está en estos WAV, no en el MIDI.

Atribución: Versilian Studios LLC (VCSL, CC0); FreePats / Xavimart, Gonzalo y Roberto
(World Percussion, CC0).
