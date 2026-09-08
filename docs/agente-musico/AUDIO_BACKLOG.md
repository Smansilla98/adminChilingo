# Backlog de audio

Los 26 WAV de `public/sounds/perc/` son **FluidR3 GM**, no la batería de La Chilinga.
Estado de catálogo `MAPA_SAMPLES`: AVAILABLE (redistribuible).
Timbre de escuela: MISSING (grabar one-shots propios).

## Faltantes musicales (el JSON ya tiene el golpe)

### redoblante + agudo

```
Toque:       Toque a Oxosi
Página:      archivo 60 / impresa 57 (Final); glifo en Nomenclatura archivo 5
Instrumento: redoblante
Stroke:      agudo (triángulo)
Evento:      mismo tick que repique agudo en el pentagrama compartido
Fuente:      PDF Toques, Nomenclatura + Final Oxosi
Prioridad:   alta
Estado:      AUDIO_SAMPLE_MISSING
             TRANSCRIPTION = VALID (el glifo existe; no sustituir por nota)
```

El playback no debe disparar `redoblante_normal` en su lugar (thump de aviso hasta que exista el WAV).

## Paleta vs PDF

| Par | En Nomenclatura | En MAPA_SAMPLES | Nota |
| --- | --- | --- | --- |
| redoblante / agudo | sí (mismo triángulo que repique; confirmado en Oxosi) | no | backlog |
| agogó / * | no en p.2 | sí | instrumento entra en toques, no en la hoja de nomenclatura |
| palmas / * | no en p.2 | sí | idem |

## Prioridad de grabación (escuela)

1. redoblante_agudo
2. kit propio de surdos (grave corto de zinguero) en lugar de FluidR3
3. caixa / repique reales
