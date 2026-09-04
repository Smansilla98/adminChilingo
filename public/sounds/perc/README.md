# Samples de percusión (opcional)

Colocá archivos nombrados `{instrumento}_{golpe}.wav` (también `.mp3` / `.ogg`).

Ejemplos:
- `timbal_abierto.wav`  (MIDI 48)
- `timbal_slap.wav`     (MIDI 49)
- `timbal_palma.wav`    (MIDI 50)
- `redoblante_nota.wav` (MIDI 38)
- `redoblante_chapa.wav`(MIDI 39)
- `surdo_grave_nota.wav`
- `surdo_grave_chapa.wav`

Si falta el archivo, el motor usa síntesis Web Audio con filtros
(highpass para slap/chapa, lowpass para abiertos/graves).
