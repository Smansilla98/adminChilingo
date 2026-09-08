# Modelo de datos (sin duplicar el editor)

El editor ya guarda un score v4 en `programa_ritmos.medios['partitura_score']`.
No se crearon tablas Eloquent paralelas (Toque / PatternEvent / …) porque duplicarían `PartituraScore`.

Correspondencia:

```
Toque          → score v4 (title, sections) + source PDF
Source         → score.source { type, file, pages } + fuente { origen, hash }
Instrument     → instruments[].id (lista blanca)
Stroke         → note.stroke
Pattern        → section + measures
PatternEvent   → note en voces[inst]  ó  eventosMusicales(score)
Sample         → MAPA_SAMPLES + WAV
MusicXML/MIDI  → exporters.js / midi_from_v4.py
```

`score.source` (PDF):

```json
{
  "type": "pdf",
  "file": "Toques_chilinga_compressed.pdf",
  "pages": [58, 59, 60]
}
```

Si el PDF no imprime tempo, el JSON **sigue** llevando tempo pedagógico 80–90.
El inventario documenta `tempo_pdf: null`. No se pone `tempo: null` en el score porque el reproductor y `dsl.score()` exigen 80–90.

Simultaneidad: dos notas en el mismo `absTick`, instrumentos distintos. No se fusionan en un id nuevo.
