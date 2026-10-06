# Editor de ritmos de La Chilinga — especificación corregida

Versión consolidada de los dos pedidos ("Rediseño profundo del editor" y "Referencia
PercuSignal"). Se unificaron las secciones repetidas, se resolvieron contradicciones y
se ajustó todo a lo que **ya existe** en el código (auditoría del 2026-10-06).

Idea central, sin cambios: **el ritmo es la fuente única**; partitura, grilla, audio,
MIDI y exportaciones son vistas o salidas de ese mismo modelo. No es "otro MuseScore".

---

## 1. Lo que ya existe (auditoría)

| Pregunta del pedido | Respuesta |
|---|---|
| ¿Qué editor se usa? | Propio, en `resources/js/partitura/` (vanilla JS + Vite, ~5.400 líneas): `model.js`, `editor.js`, `renderer.js` (VexFlow), `audio.js`, `samples.js`, `instruments.js`, `importers.js`, `exporters.js`, `tour.js`, `viewer.js`. Vista: `/programa/toque/{slug}/editor`. |
| ¿Cómo se representa una nota? | `{ dur, dots, rest, tuplet, stroke (golpe), dyn (dinámica), dig (D/I) }` dentro de la voz de un instrumento. Duraciones en ticks, **TPQ = 48** por negra. |
| ¿Cómo se guarda? | JSON **v4** (`PartituraScore.php` valida y normaliza en el servidor): `secciones → compases → voces por instrumento → notas`, con `tempo`, `timeSignature`, instrumentos, marcas (D.C., Coda, Corte, Llamada…). Se guarda en `programa_ritmos.medios`. |
| ¿Representación JSON? | Sí: v4 es la representación canónica. |
| ¿MusicXML? | Sí: importar (`importarMusicXML`) y exportar (`generarMusicXML`). |
| ¿MIDI? | Exportar sí (`generarMIDI`, canal 10). Importar no. Teclado MIDI no. |
| ¿Cómo se renderiza? | VexFlow (SVG), pentagrama de percusión de una línea por instrumento. |
| ¿Cómo se reproduce? | Web Audio: `MotorAudio` con scheduler sobre `AudioContext.currentTime` (lookahead), sampler con `AudioBuffer` + `GainNode`, unísono con micro-desfase, metrónomo sintético, **cuenta previa y loop ya implementados**. |
| ¿Fuente única para audio y dibujo? | **Ya existe**: `eventosMusicales(score)` y `expandirTimeline(score)` alimentan audio; el renderer lee el mismo `score`. |
| Sonidos | 27 one-shots WAV en `public/sounds/perc/` procesados desde librerías **CC0** (VCSL, FreePats). Nomenclatura `{instrumento}_{golpe}.wav`. Hay guía para grabar la batería propia (`COMO-GRABAR.md`). |
| Deshacer / rehacer | Sí (Ctrl+Z, Ctrl+Y y Ctrl+Shift+Z). |
| Exportar | PDF y PNG (desde el SVG), MusicXML, MIDI. |
| Persistencia | Botón Guardar. Web: `POST /programa/toque/{slug}/editor` (pide nombre de quien edita; edición pública solo si `chilinga.edicion_publica_programa`). App: `PUT /api/v1/partituras/{slug}/score` (`partituras.admin`). **Sin autoguardado, sin versiones, sin borrador/publicado.** |
| Tests | `tests/js/partitura-modelo|render|interop.test.js` (`npm run test:partitura`). |
| App móvil | Escribe el mismo v4 con una grilla de semicorcheas y escucha con los WAV (`mobile/src/app/(app)/partituras/[slug].tsx`). |

**Conclusión:** la arquitectura que pide el documento (modelo → renderer / audio)
ya está separada. El trabajo es **evolucionar**, no reescribir: sumar la vista de
grilla como segundo renderer del mismo modelo, mejorar la interacción y agregar
autoguardado, versiones, mixer y modo ensayo.

---

## 2. Correcciones al pedido original

### 2.1 Instrumentos: no son los de candombe
El pedido usa **Chico, Repique, Piano, Tambor, Bombo, Campana, Clave, Shaker**. *Chico,
repique y piano* son los tambores del candombe uruguayo; no es la nomenclatura de La
Chilinga. El catálogo real (Cuadernillo de Toques, `instruments.js`) es:

`Surdo grave · Surdo medio · Surdo agudo · Redoblante · Repique · Timbal · Agogó · Palmas` (+ `Todos`).

Golpes por instrumento: pleno, acentuado, chapa/aro, tapado, presionado, abierto, slap,
palma, dedos, agudo (borde), flam. El catálogo sigue siendo ampliable (instrumento →
golpes → sample), pero los ejemplos y atajos se definen sobre estos.

### 2.2 Atajos: había choques entre sí y con el editor actual
El pedido asignaba la misma tecla a dos cosas y pisaba atajos en uso:

| Tecla | Pedido | Hoy en el editor | Decisión |
|---|---|---|---|
| `D` | Piano **y** Duplicar | Digitación derecha | Sigue **digitación D**. Duplicar pasa a `R`. |
| `H` | Campana **y** Ayuda | — | Ayuda pasa a **`?`**. |
| `R` | Repetir | **Silencio** | `R` = **repetir selección** (como MuseScore). Silencio pasa a **`0`**. |
| `1`–`5` | 1/16 … 1 compás | `1` redonda … `5` semicorchea, `6` fusa | Se **mantiene el actual** (ya está en la guía y en el tour). "1 compás" no es una figura: en 4/4 es la redonda. |
| `Enter` | Play/Pause | Insertar nota | Sigue **insertar**. Play/Pause = `Space`. |
| `Q W E R T Y` | — | Golpes del instrumento | La `R` hoy nunca llega al 4.º golpe (la captura el silencio): golpes pasan a **`Q W E T Y U`**. |
| `A S D F G H J K` | Instrumentos en el editor | — | Con foco en la **grilla** (escriben y avanzan) y en **modo Tocar** (suenan y graban). Con foco en la partitura no hacen nada, así `D` sigue siendo digitación. |

### 2.3 El foco decide qué hacen las letras
"Seleccionar posición → presionar A → aparece el golpe" (edición) y "el teclado es un
instrumento que suena y graba" (tocar) son dos usos distintos. Se separan:

- **Partitura** (foco en el pentagrama): flechas mueven el cursor, números eligen figura,
  `Q W E T Y U` eligen golpe, `D`/`I` digitación, `N` entrada continua.
- **Grilla** (foco en la grilla): `A S D F G H J K` ponen un golpe del instrumento 1…8 en
  el paso del cursor y avanzan (`N` alterna el avance); `Q W E T Y U` cambian el golpe;
  `4`/`5`/`6` eligen resolución (corcheas, semicorcheas, fusas). Así se hace
  `5 → A A S A → Espacio`.
- **Modo Tocar** (`P`): las mismas letras suenan al instante con feedback visual. Con
  **Grabar** los golpes se registran y al parar se cuantizan a la grilla.

### 2.4 La grilla no reemplaza a la partitura en todo
El modelo admite tresillos, sextillos, puntillos y flams. Una grilla de pasos fijos no
puede representarlos sin perder información. Regla: la grilla edita lo que cae en la
resolución elegida (1/8, 1/16, 1/32); las notas fuera de grilla se muestran marcadas y
**solo se editan en la partitura**. Nunca se convierten en silencio por pasar por la grilla.

### 2.5 Intensidad: una sola fuente
El pedido mezcla velocity numérica, dinámicas p–ff, accent y ghost como si fueran
cuatro cosas. En el modelo hay **golpe** (acentuado, tapado…) y **dinámica**
(`dyn`), y `velocidadDeNota()` ya calcula la velocity. Se agrega:
- golpe **fantasma** (ghost) al catálogo, con su ganancia, y
- un ajuste fino opcional `vel` por nota (0–127) que, si existe, manda sobre el cálculo.
`<` / `>` bajan y suben la dinámica de la selección. Visualmente: tamaño y forma del
golpe (● pleno, ◦ fantasma, ◆ acentuado), nunca solo color.

### 2.6 Versiones vs. autoguardado
"Si se modifica, guardar una nueva versión" + "autoguardado con debounce" generaría
cientos de versiones. Se separa:
- **Autoguardado** del **borrador** (debounce ~3 s; solo quien tiene permiso de editar;
  quien edita sin cuenta guarda el borrador en el navegador).
- **Versión** = cada vez que se **publica** (o "Guardar versión" a mano), con autor y
  nota. Se puede restaurar cualquier versión anterior.

### 2.7 Lo que ya existe no se "agrega"
Cuenta previa, loop, metrónomo, undo/redo, exportar PDF/PNG/MusicXML/MIDI e importar
MusicXML **ya funcionan**. Se mejoran (loop por selección, subdivisión y volumen del
metrónomo, cuenta previa de 1 o 2 compases), no se reimplementan.

### 2.8 "Señas" ya tienen un punto de partida
Las marcas `Corte` y `Llamada` del modelo son el germen de `RhythmCue`. La entidad
futura se diseña extendiendo esas marcas (compás + seña + acción), no aparte.

### 2.9 Tecnología
- Web MIDI (teclado MIDI) solo funciona en navegadores Chromium: se ofrece como extra.
- Exportar **WAV** se hace en el navegador con `OfflineAudioContext` (sin backend).
- **Video**: fuera de alcance.
- No se agregan librerías musicales grandes: VexFlow y Web Audio alcanzan.

---

## 3. Modelo (v4 → v5, compatible)

```
Ritmo (score v5)
├── tempo, timeSignature            (tempo por sección: preparado, no en esta etapa)
├── instrumentos[]                  { id, mute, solo, volumen, pan, sonido }   ← nuevo: mixer
├── secciones[] → compases[] → voces{ instId: notas[] }
│                 nota { dur, dots, rest, tuplet, stroke, dyn, dig, vel? }   ← vel opcional
└── marcas[]                        (D.C., Coda, Corte, Llamada… → futuro RhythmCue)

Vistas:  Partitura (VexFlow) · Grilla · Mixta     ← las tres leen el mismo score
Audio:   eventosMusicales(score) → MotorAudio (Web Audio)
Salidas: MusicXML · MIDI · PDF · PNG · WAV
```

- `normalizarPartitura()` acepta v4 y devuelve v5 con valores por defecto: **las
  partituras existentes abren igual y nada se borra**.
- El mixer (mute, solo, volumen) **no cambia la música**: se guarda como preferencia
  del toque; el modo Clase lo usa sin tocar las notas.

## 4. Base de datos (no destructiva)

| Hoy | Problema | Propuesta |
|---|---|---|
| `programa_ritmos.medios` guarda la partitura publicada | Guardar = publicar; no hay historial ni borrador | Tabla nueva **`partitura_versiones`** (`programa_ritmo_id`, `numero`, `score` JSON, `autor`, `nota`, `created_at`) y columna **`score_borrador`** (JSON, nullable) |

Migración: copiar la partitura actual de cada toque como **versión 1**. `medios`
sigue siendo lo publicado, así las URLs, la app y la vista pública no cambian.

## 5. Atajos (tabla final, generada desde un `RegistroAtajos`)

| Acción | Tecla |
|---|---|
| Play / Pausa | `Space` |
| Stop (vuelve al inicio) | `Shift+Space` |
| Entrada continua | `N` |
| Mover cursor / cambiar instrumento | `← →` / `↑ ↓` |
| Seleccionar | `Shift + ← →` (`Shift + ↑ ↓` suma instrumentos) |
| Figura | `1` redonda · `2` blanca · `3` negra · `4` corchea · `5` semicorchea · `6` fusa · `.` puntillo |
| Tresillo / sextillo | `Ctrl+3` / `Ctrl+6` |
| Silencio | `0` |
| Insertar nota | `Enter` |
| Golpe | `Q W E T Y U` (según instrumento) |
| Digitación | `D` derecha · `I` izquierda |
| Repetir/duplicar selección | `R` |
| Dinámica − / + | `<` / `>` |
| Borrar | `Delete` / `Backspace` |
| Copiar / Pegar | `Ctrl+C` / `Ctrl+V` |
| Deshacer / Rehacer | `Ctrl+Z` / `Ctrl+Y` o `Ctrl+Shift+Z` |
| Guardar versión | `Ctrl+S` |
| BPM − / + | `-` / `+` |
| Loop | `L` |
| Metrónomo | `M` |
| Modo Tocar | `P` (adentro: `A S D F G H J K` = instrumentos) |
| Grilla: instrumento 1…8 / poner-sacar / resolución | `A`…`K` / `X` o `Enter` / `4` `5` `6` |
| Stop | `Shift+Space` |
| Vista Partitura / Grilla / Mixta | `Alt+1` / `Alt+2` / `Alt+3` |
| Ayuda de atajos | `?` |

Reglas del registro: registrar, quitar, resolver por modo, detectar conflictos y
generar la ayuda; ningún atajo actúa con el foco en un campo de texto; las teclas de
golpe e instrumento son personalizables y se guardan por usuario (`apariencia_json`).

## 6. Prioridades corregidas

**P0 — base**
1. Registro de atajos (reemplaza el `keydown` con `if`s) con la tabla de arriba y la ayuda `?`.
2. Vista **Grilla** + **Mixta** sobre el mismo modelo, selección sincronizada.
3. Clic/tap en celda agrega o quita; clic derecho / pulsación larga = golpe y dinámica.
4. Escuchar al editar: cada golpe agregado suena al instante (preview del sample).
5. Compases: insertar antes/después, duplicar, borrar (ya existen agregar/borrar).
6. Autoguardado del borrador + estado visible (✓ Guardado / ● Cambios sin guardar).
7. Cursor de reproducción en la grilla (columna actual iluminada).

**P1 — práctica y clase**
8. Mixer: mute / solo / volumen por instrumento.
9. Loop por compás, selección o todo; metrónomo con subdivisión y volumen; cuenta previa 1–2 compases; BPM con slider, ± y **Tap tempo**.
10. Golpe fantasma, dinámica con `<`/`>`, `vel` opcional.
11. Selección múltiple, copiar/pegar, repetir ×2/×4/×8.
12. Modo **Ensayo** / **Clase** (pantalla simple, mixer a mano).
13. Grilla en el móvil web (celdas grandes, play fijo, instrumentos en selector horizontal).

**P2**
14. Versiones (tabla + restaurar) y Publicar.
15. Modo Tocar + Grabar + cuantizar.
16. Exportar WAV; importar MIDI; teclado MIDI (Chromium).
17. Atajos personalizables; biblioteca de sonidos administrable (volumen, afinación, sample propio).

**P3**
18. Señas (`RhythmCue`), modo Aprendizaje, comentarios, colaboración.

## 7. Qué no se toca

URLs actuales, formato v4 (se lee siempre), MusicXML, la app móvil (sigue escribiendo
v4/v5 por la misma API), la vista pública del programa, la biblioteca. Sin samples con
copyright: se mantiene el banco CC0 y se documenta cómo reemplazarlo por la grabación
propia.

## 8. Estado de implementación (2026-10-06)

Hecho (P0–P2 y la base de P3):

- **Registro de atajos** (`atajos.js`): 64 acciones, contextos partitura/grilla/tocar,
  sin conflictos (hay test), ayuda `?` generada, personalización con aviso de conflicto
  guardada en el navegador.
- **Grilla** (`grilla.js`, `grilla-vista.js`): 1/8, 1/16, 1/32; vistas Grilla / Partitura /
  Mixta con selección sincronizada; clic/tap pone o saca; clic derecho o pulsación larga
  abre golpe y dinámica; intensidad por tamaño y forma (◆ acento, ● pleno, ◦ fantasma);
  lo fuera de grilla se marca y no se toca; al editar se redibuja solo ese compás.
- **Escuchar al editar**: cada golpe suena al ponerlo. Tempo 40–180 con slider, ± y **TAP**;
  cambia en vivo sin cortar. Loop todo / compás / selección / 2-4-8 compases; cuenta de
  0-2 compases; metrónomo con subdivisión, volumen y acento; cursor en grilla y partitura.
- **Mixer**: mute, solo, volumen, **paneo** y **afinación** por instrumento.
- **Compases**: insertar antes/después, duplicar, repetir ×2/×4/×8, selección con
  `Shift+←/→`, copiar/pegar (también entre toques).
- **Golpe fantasma**, dinámica con `<`/`>`, intensidad fina (`vel`) en el inspector.
- **Autoguardado** del borrador (3 s, una petición por ráfaga), estado visible,
  recuperación al abrir; **versiones** al publicar, restaurables desde el editor y la API.
- **Modos**: edición, ensayo, clase, presentación (pantalla completa) y aprendizaje
  (partitura oculta hasta que se pide).
- **Tocar + Grabar + cuantizar**; **MIDI**: importar `.mid`, teclado/pad MIDI (Chromium).
- **WAV** por OfflineAudioContext. MusicXML: el fantasma viaja como cabeza entre paréntesis.
- **Señas** por compás (texto, tipo, a quién): se dibujan sobre el pentagrama y aparecen
  grandes mientras suena el compás.
- **Teléfono**: la grilla es la vista principal, celdas de 40 px, transporte fijo abajo.

Pendiente: sample propio por instrumento subido por administración (hoy se ajustan volumen,
paneo y afinación sobre el banco CC0), tempo por sección, comentarios y colaboración en
tiempo real, comparación automática en el modo aprendizaje.

## 9. Tests

- Modelo: crear/insertar/duplicar/borrar compás, agregar/borrar nota, repetir selección, normalizar v4→v5 sin pérdida, grilla ↔ modelo (las notas fuera de grilla sobreviven).
- Atajos: resolución por modo y detección de conflictos (`Space`, `N`, `0`, `R`, `L`, `M`, `P`, `Ctrl+Z`, `Ctrl+S`).
- Audio: eventos por BPM (timing), loop, mute/solo excluye eventos, cuenta previa.
- Persistencia: autoguardado con debounce (una sola petición), versión al publicar, restaurar.
- Se corren con `npm run test:partitura` y `composer test`.
