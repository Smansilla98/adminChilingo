# Agente músico

Base estructurada del **Cuadernillo de Toques** (PDF, 61 páginas).

| Documento | Contenido |
| --- | --- |
| [FUENTES.md](FUENTES.md) | Jerarquía y ficha del PDF |
| [TOQUES_PDF_INDEX.md](TOQUES_PDF_INDEX.md) | 61 páginas |
| [TOQUES.md](TOQUES.md) | Inventario JSON / MIDI / MusicXML |
| [INSTRUMENTOS.md](INSTRUMENTOS.md) | Nombres PDF ↔ ids |
| [MIDI_MAPPING.md](MIDI_MAPPING.md) | GM2 convención (no está en el PDF) |
| [AUDIO_BACKLOG.md](AUDIO_BACKLOG.md) | Samples faltantes |
| [MODELO.md](MODELO.md) | Correspondencia con el editor v4 |

PDF inmutable: `database/data/fuentes/Toques_chilinga_compressed.pdf`

```
python3 .cursor/skills/agente-musico/ingestar_toques_pdf.py
python3 database/data/partituras-v4/generar.py
python3 database/data/partituras-v4/validar_v4.py
python3 database/data/partituras-v4/midi_from_v4.py
node scripts/exportar_musicxml.mjs
npm run test:partitura
```
