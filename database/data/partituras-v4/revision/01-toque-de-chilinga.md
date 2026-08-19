# Revisión contra el Cuadernillo — Toque de Chilinga

Sección auditada: **LLAMADA INICIAL Y FINAL** (voz "Todos").
Fuente: `Toques_chilinga_compressed.pdf`, pág. 6 del PDF (pág. 3 del cuadernillo),
primer pentagrama. Lectura a 300 dpi con zoom por tiempo.

> Estado: **APLICADO** (fix 1-6 completo, incluida la opción A para "Todos").
> Escrito en `toques/01_toque_de_chilinga.py` y regenerado en
> `01-toque-de-chilinga.json`. Falta correr el seeder en el entorno real
> (`php artisan db:seed --class=PartiturasEjemploSeeder`) para que la base deje de
> tener la versión vieja.

---

## 1. Lo que dice el PDF

- Encabezado de sección: `LLAMADA INICIAL Y FINAL` (en mayúsculas).
- Etiqueta de voz: `Todos` (un único pentagrama, no seis).
- Compás: `C` = 4/4. Dinámica: **no hay ninguna dinámica impresa en esta sección**.
- No hay barras de repetición ni `×N`: la frase de 2 compases está **escrita dos
  veces** (4 compases en el pentagrama), y cierra con doble barra final.
- Sin acentos `>` en esta sección (los `>` aparecen en Redoblante y Repique del TOQUE).

Lectura por tiempo (grilla de semicorcheas):

| Compás | T1 | T2 | T3 | T4 |
|---|---|---|---|---|
| 1 | `xx-x` | `xx-x` | `xx-x` | `xx-x` (?) |
| 2 | negra | 8ª silencio + 2 semicorcheas | negra | negra silencio |
| 3 | igual al compás 1 | | | |
| 4 | igual al compás 2 | | | |

Compás 2 en detalle: negra · silencio de corchea · dos semicorcheas ligadas por
barra · negra · silencio de negra = 4 tiempos exactos.

## 2. Lo que hay cargado hoy (v4)

Sección `Llamada inicial y final`, `repeatX = 1`, **2 compases** con
`repeatBegin` / `repeatEnd`, mismas 6 voces con patrón idéntico (réplica tutti):

| Compás | Patrón cargado |
|---|---|
| 1 | `xxxx-xxx xxxx-xxx` → T1 `xxxx`, T2 `-xxx`, T3 `xxxx`, T4 `-xxx` |
| 2 | negra · 8ª silencio · 2 semicorcheas · negra · negra silencio |

## 3. Diferencias

| # | Qué | Cargado | PDF | Gravedad |
|---|---|---|---|---|
| D1 | Ubicación de los silencios de semicorchea del compás 1 | silencio al **inicio** de los tiempos 2 y 4 (`xxxx` / `-xxx`) | silencio en la **3ª semicorchea de cada tiempo** (`xx-x` ×4) | **Alta** — cambia el groove entero de la llamada |
| D2 | Compás 2 | negra · 8ª sil. · 2 semis · negra · negra sil. | idéntico | Sin diferencia ✅ |
| D3 | Cantidad de compases | 2 con barras de repetición | 4 escritos literalmente, sin barras de repetición | Media — suena igual, pero no es literal |
| D4 | Dinámica | `f` en el primer compás | el PDF **no imprime dinámica** en esta sección | Media — dinámica inventada |
| D5 | Nombre de sección | `Llamada inicial y final` | `LLAMADA INICIAL Y FINAL` | Baja (cosmética) |
| D6 | Voz "Todos" | replicada en 6 pentagramas idénticos | un solo pentagrama `Todos` | Media — ver limitación del modelo |

Nota sobre la premisa del pedido: en el modelo v4 actual la llamada **no** está
"separada en 6 pentagramas con patrones distintos" — los 6 son idénticos (réplica
tutti). Si en la pantalla se ven patrones distintos, lo que está cargado en la base
es la partitura **v3 vieja**: el seeder v4 todavía no se corrió sobre ese registro
(`php artisan db:seed --class=PartiturasEjemploSeeder`).

## 4. Pasajes ambiguos → revisar con la escuela

- **Compás 1, tiempo 4**: el escaneo no permite decidir entre `xx-x` (igual a los
  tiempos 1-3) y `xxx-` (silencio al final del tiempo, justo antes de la barra).
  Se lee un silencio de semicorchea pegado a la barra, lo que empuja hacia `xxx-`,
  pero la barra de unión sugiere el mismo grupo que los tiempos anteriores.
- **Compases 3-4**: se leen idénticos a 1-2, pero la mancha del escaneo tapa la
  3ª semicorchea del tiempo 3 del compás 3.

Propuesta: transcribir `xx-x` ×4 y dejar el aviso en `measure.texto` como
`"revisar con la escuela: T4"`, en vez de elegir en silencio.

## 5. Limitación del modelo: la voz "Todos"

El modelo v4 no tiene forma de representar un unísono como **una sola voz**:

- `measure.voces` es un mapa `instrumentId -> notas`, y los ids válidos están
  fijados por lista blanca en `resources/js/partitura/instruments.js`
  (`surdo_grave`, `surdo_agudo`, `surdo_medio`, `redoblante`, `repique`, `timbal`,
  `agogo`, `palmas`) y validados de nuevo server-side en
  `app/Support/PartituraScore.php`.
- El renderer dibuja **un `Stave` por instrumento de `score.instruments`**, así que
  hoy "Todos" sólo puede existir como la misma línea replicada en N pentagramas.
- No hay campo de vínculo entre voces: si mañana se edita la llamada, hay que
  editar las 6 copias a mano y pueden divergir (esto es probablemente el origen del
  problema que se ve en pantalla).

Opciones, **ninguna aplicable sin tocar código**:

| Opción | Qué implica | Costo |
|---|---|---|
| **A. Instrumento virtual `todos`** | agregar `{ id: 'todos', label: 'Todos', pitch: 'c/5', ... }` a `instruments.js` + lista blanca de `PartituraScore.php`; el renderer ya lo dibujaría como un pentagrama más; `expandirTimeline` tiene que mapear `todos` a todos los ids reales para el audio (si no, el unísono suena con un solo timbre) | medio, toca 3-4 archivos |
| **B. Flag `unisono` en la sección/compás** | `measure.unisono = true` + una sola voz canónica; renderer colapsa a un pentagrama etiquetado "Todos"; audio ya resuelto porque se expande a los ids reales | medio-alto, toca modelo + renderer + editor |
| **C. Dejar la réplica tutti** (estado actual) | cero código; suena bien; se ve como 6 pentagramas iguales en vez de uno "Todos" y puede divergir al editar | cero |

Decisión: se aplicó la **opción A** (aditiva, no rompe partituras ya cargadas). B queda
como mejora posterior si se quiere colapsar visualmente sin instrumento virtual.

Estado de la implementación:

- `instruments.js`: instrumento `todos` (`label 'Todos'`, `pitch 'b/4'`, `midi 38`) al
  inicio de `INSTRUMENTOS`, con sus golpes y los helpers `UNISONO`, `esUnisono()`,
  `vocesDeUnisono(score)`.
- `PartituraScore.php`: `'todos' => 'Todos'` en la lista blanca `const INSTRUMENTOS`.
- `audio.js`: la voz `todos` se expande a **todos los instrumentos reales** del score
  (`vel * 0.85` cuando hay más de un destino), respetando `mute`/`solo` del canal virtual.
- `dsl.py`: `unisono(pat)` escribe la voz `todos` y da de alta el instrumento al inicio de
  `instruments`; un instrumento que no toca ningún golpe en toda la partitura queda
  `visible: false` (no se dibuja, pero presta su timbre al unísono).
- `tutti(pat, INSTS)` queda reservado para **subgrupos** (p. ej. sólo los surdos), que sí
  llevan una línea por instrumento.
- Pendiente conocido: `exporters.js` (MusicXML/MIDI) exporta `todos` como una parte más,
  sin expandir a los timbres reales.

## 6. Fix aplicado

1. ✅ Compás 1 (y su repetición) a `xx-x xx-x xx-x xx-x` — corrige **D1**.
2. ✅ Quitada la dinámica `f` inventada — corrige **D4**.
3. ✅ Los 4 compases escritos literalmente, sin `repeatBegin`/`repeatEnd` — corrige **D3**.
4. ✅ Sección renombrada a `LLAMADA INICIAL Y FINAL` — corrige **D5**.
5. ✅ `measure.texto` del compás 1: `Todos — revisar con la escuela: T4`.
6. ✅ **D6 / "Todos"**: opción A aplicada — la sección se escribe con `unisono(...)` y
   queda en **un solo pentagrama** `Todos`, expandido a los 6 timbres en el audio.

Verificado sobre el JSON generado: sección `LLAMADA INICIAL Y FINAL` con 4 compases,
`voces.todos` con 12 golpes y `surdo_grave` sin notas; tempo 88; timeline de 52 compases;
`PartituraScore::normalizar()` no pierde compases ni golpes.

## 7. Alcance más allá de esta sección

Los 26 toques ya cargados se transcribieron con el criterio anterior
("transcribir el pasaje más probable, sin marcas de duda"). El criterio nuevo pide
lo contrario: marcar explícitamente lo ambiguo como *revisar con la escuela*. Eso
implica una segunda pasada toque por toque contra el PDF, con el mismo formato de
auditoría de este documento — empezando por Toque de Chilinga y siguiendo el orden
del cuadernillo.
