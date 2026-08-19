# La Chilinga: contexto y por qué el sistema no se normaliza

> Documento de fundamento para las decisiones de producto del módulo de partituras.
> No es historia por la historia: cada punto cierra con la implicancia concreta para el
> editor, el modelo de datos y el criterio de digitalización del Cuadernillo de Toques.
> Fecha de relevamiento: agosto 2026.

---

## 1. Qué es La Chilinga

Escuela de **percusión popular** fundada el **3 de octubre de 1995** por **Daniel Buira**
(baterista fundador de Los Piojos, después con Vicentico y ~100 discos como sesionista).
Nació en la sala de ensayo de la "casa piojosa" de Ciudad Jardín, Tres de Febrero
(Avenida Libertad y Palazzo, la "Esquina Libertad" de la canción), con unas quince
personas tocando en el pasillo.

Hoy es una de las escuelas de percusión más grandes de América Latina: **cientos de
alumnos** (Wikipedia habla de +900 entre Argentina y Uruguay) repartidos en múltiples
sedes. Las comunicaciones oficiales de 2026 (`@lachilinga`, "30 años 1995-2025") listan
**Palomar, Saavedra, Avellaneda, Quilmes, Florencio Varela** y más, con antecedentes en
Martín Coronado, El Palomar, Lanús, San Justo, Santos Lugares, Lomas de Zamora, Villa
Bosch, microcentro, Morón y Córdoba (Villa General Belgrano), más anexos en el Centro
Cultural Sábato y el ECUNHI. Suma **"La Chilinguita"** (infantil, 6-16) y talleres de
verano.

Discografía: *Percusión* (1998), *Viejos Dioses* (2001), *Muñequitos del tambor* (2004),
*Raíces* (2007) — grabado con ~200 alumnos —, *Banda Fantasma* (2010). Grabaron o
tocaron con Mercedes Sosa, Fito Páez, Pedro Aznar, Peteco Carabajal, Kevin Johansen,
Los Cafres, Vicentico y **Calle 13** (los nombra "La Perla", junto a Rubén Blades).

**Daniel Buira murió el 21 de marzo de 2026, a los 54 años**, en la sede de Morón
(Marconi 183). La escuela continúa.

> **Implicancia para el sistema:** el cuadernillo dejó de tener un autor al que
> preguntarle. Cada normalización silenciosa que hagamos ("esto seguro quiso decir X")
> es una pérdida irreversible de fuente. El sistema tiene que poder **guardar la duda**
> como dato legítimo, no resolverla: de ahí el estado `revisar con la escuela` en las
> auditorías, y no una corrección inventada.

---

## 2. La pedagogía: sin exámenes, sin niveles, sin técnica impuesta

Esto es el corazón del proyecto y la razón por la que la notación es como es.

- **"En La Chilinga todos tocan, nadie se queda afuera"** (lema de Buira, Página/12,
  28/12/2020). No hay examen, no hay calificaciones, no se divide por niveles.
- Buira, textual (Sudestada, 16/03/2024): *"En la escuela no tenés que estudiar. Está
  prohibido estudiar. Está prohibido el examen, está prohibido pasar de año."*
- Hay **cuota social**, pero no es condición: *"si no la podés pagar, no la pagás.
  Porque es popular."* Cuando un alumno deja de venir, se averigua por qué —muchas veces
  es económico.
- **No se enseña técnica**: *"no les enseñamos técnica, no les enseñamos a decir 'esto se
  toca así'. Solo dejamos que toquen como quieran. (...) Nosotros solamente ordenamos el
  toque, el ritmo."*
- Y el motivo explícito: *"Si vos a una persona la corregís, ya la vas a hacer imitar. Y
  la imitación te lleva a un lugar más profesional, a un lugar donde ya tenés que
  estudiar."*
- Si a alguien le cuesta, **baja el tempo el grupo entero**, no se lo deja atrás.

Está catalogada como **educación no formal / educación popular** (no como conservatorio),
y da clases en cárceles (Ezeiza) como ONG.

> **Implicancias para el sistema:**
> 1. La partitura es **guía de bloque, no evaluación**. Nada en la UI debe leerse como
>    corrección, puntaje, nivel alcanzado o "ejecución correcta". No hay feedback de
>    acierto/error, no hay progreso obligatorio.
> 2. El editor **ordena el toque**, no la técnica. Por eso el modelo describe *qué golpe
>    y cuándo* (`nota`, `acentuado`, `tapado`, `chapa`, `abierto`, `slap`, `palma`,
>    `dedo`, `agudo`, `flam`) y **no** manos (R/L), digitación, ni sticking. Nunca
>    agregar campos de técnica.
> 3. El "programa por año" que ya está en `ProgramaRitmosSeeder` (1° a 6°, con ritmos
>    opcionales) hay que leerlo como **orden de repertorio del cuadernillo**, no como
>    niveles de alumnos ni como habilitación para avanzar. La UI no debe decir "año 3
>    bloqueado" ni "aprobaste el año 2".
>    → **Aclarado (Santiago):** el agrupamiento por años es de **saber acumulado**: un
>    toque de 3° da por sabido lo que se vio en 1° y 2°, y por eso es más difícil. No es
>    una etiqueta de "nivel" del toque en abstracto ni de nivel del alumno. La UI puede
>    mostrar el año como *orden y dificultad acumulada*, nunca como aprobación.
> 4. El tempo es una **sugerencia**: el `tempo` del score arranca en el del cuadernillo,
>    pero el reproductor tiene que permitir bajarlo sin friccion (el grupo baja el ritmo
>    para el que le cuesta).
>    → **Aclarado (Santiago):** el repertorio se toca **entre 80 y 90 bpm**. Los 26 JSON
>    quedaron en esa franja y `dsl.score()` la valida (falla fuera de rango). Los valores
>    exactos por toque son de referencia, no dogma.

---

## 3. La transmisión es oral; el cuadernillo es apoyo, no canon

La escuela nació sin internet, sin discos de referencia accesibles y sin tambores
comprables: Buira le encargó los surdos a un **zinguero** amigo pidiéndole
explícitamente que **no** sonaran como los brasileros — más graves y más cortos, "el
sonido al sur tiene que ser más grave". Los instrumentos de la escuela son, literalmente,
un diseño propio.

De ahí que *"un samba reggae de origen brasilero no va a sonar nunca a brasilero"*: no se
copia la técnica de origen, se ordena el ritmo y cada uno lo toca a su manera. Buira lo
llama, polémicamente, "ritmo blanco": nace de lo afro, pero pasa por el tango, la
milonga, la cancha y la murga rioplatense. **"Chilinga 1"** —el primer ritmo que se
enseña, el de *Verano del '92* de Los Piojos— es exactamente eso.

> **Implicancias para el sistema:**
> 1. **La fuente de verdad es el PDF del cuadernillo**, no la notación estándar de
>    samba/batucada ni lo que "debería" ser un samba-reggae. Si el cuadernillo escribe
>    algo que un manual brasilero escribiría distinto, **gana el cuadernillo**.
> 2. Los nombres de instrumentos son los de la escuela (`surdo_grave`, `surdo_medio`,
>    `surdo_agudo`, `redoblante`, `repique`, `timbal`, `agogo`, `palmas`) y la lista
>    blanca de `instruments.js` / `PartituraScore.php` no se amplía con instrumentos
>    "de género" que la escuela no usa.
> 3. El **timbre** del sampler debería tender al surdo grave y corto de la escuela, no
>    a librerías de samba brasilero. (Backlog.)
> 4. Los nombres de secciones y su **orden** se copian tal cual, en MAYÚSCULAS, con la
>    cantidad de compases como los escribe el cuadernillo. No se renombran ni se
>    reordenan por prolijidad.

---

## 4. Lo que se toca es un bloque en la calle, no un ensamble de concierto

La escuela se forma tocando en la vereda, en escraches, en marchas: nació el mismo año
que H.I.J.O.S., toca cada **24 de marzo** con Madres de Plaza de Mayo (Buira: *"somos
familia, cuidamos a Las Madres"*), en Ni Una Menos, en actos populares. Repertorio
afro-rioplatense y afrobrasileño: candombe uruguayo y argentino, samba-reggae, murga,
marcha camión, makuta, bembé, rumba, columbia, abakuá, son, candomblé, baguala, alcatraz,
guaguancó, iyesá.

Un bloque en la calle se dirige con **señas y llamadas**, no leyendo un atril. La
notación del cuadernillo está escrita para eso: se memoriza, se repite indefinidamente
hasta la próxima señal, y las "llamadas" son puntos de sincronización, no material
temático.

> **Implicancias para el sistema:**
> 1. **`×N` va a `section.repeatX` (1-16) y nunca se expande en compases reales.** Así
>    se lee en la calle: un patrón corto y una indicación de repetición. `expandirTimeline`
>    lo expande sólo para generar el audio; el documento guardado conserva la forma
>    compacta del cuadernillo.
> 2. La voz **"Todos"** del cuadernillo es un **unísono real** (llamada / corte / final),
>    no seis partes que casualmente coinciden. Representarla como seis pentagramas
>    idénticos es una mentira de notación: dice "cada instrumento tiene su parte" cuando
>    el cuadernillo dice "acá tocamos todos lo mismo".
>    → **Confirmado (Santiago):** "Todos" es **unísono estricto**, sin variantes por
>    instrumento. Ejemplos: la llamada final de **Sacateca** y lo que sigue a la llamada
>    final del **Toque de Chilinga**.
>    → **Implementado (opción A):** instrumento virtual `todos` en `instruments.js` +
>    lista blanca de `PartituraScore.php` + `unisono()` en el DSL + expansión a todos los
>    instrumentos reales en el motor de audio (`vocesDeUnisono`). Se dibuja **un solo
>    pentagrama** y suena con todos los timbres. Es aditiva, no rompe lo ya cargado y hace
>    que la pantalla se lea como la hoja. `tutti()` queda reservado para subgrupos
>    (p. ej. sólo los surdos), que sí llevan una línea por instrumento.
> 3. Las **LLAMADAS** (inicial, final, de corte) son secciones de primera clase, no
>    introducciones decorativas: son la interfaz de dirección del bloque. Deben quedar
>    visibles y saltables/lanzables desde la UI de ensayo.
> 4. Los **acentos (`>`)** son articulación sobre la nota exacta, no una figura distinta
>    ni un cambio de dinámica: en la calle son el gesto que hace que el ritmo se
>    reconozca. Van como articulación (golpe `acentuado`) sobre la nota que el
>    cuadernillo marca, sin desplazar ni redondear.

---

## 5. Criterio de digitalización que sale de todo lo anterior

Reglas operativas, ya aplicadas o a aplicar en `database/data/partituras-v4/`:

1. **PDF como única fuente.** Cada toque se coteja sección por sección contra su página,
   con crops a 300 dpi cuando la impresión es dudosa. El audio del disco (*Percusión*,
   1998, y siguientes) sirve de **control secundario**, nunca para sobreescribir la hoja.
2. **No adivinar.** Pasaje ilegible o ambiguo → se anota `revisar con la escuela` en la
   auditoría del toque y, si corresponde, en el `texto` del compás. No se rellena con
   "lo más probable". (Esto corrige el criterio de la primera pasada, que sí completaba
   por probabilidad, e implica una segunda pasada completa sobre los 26.)
3. **No inventar dinámicas.** Si el cuadernillo no imprime `f`/`mf`, el JSON no la lleva.
   Si la imprime, va una sola vez al inicio y no se agregan cambios.
4. **No simplificar figuras.** Tresillos, sextillos, silencios y prolongaciones tal cual.
   Nada de "esto es lo mismo pero más limpio".
5. **No reordenar ni renombrar** secciones e instrumentos.
6. **Corregir el JSON del modelo, no el renderer** — salvo cuando el modelo ya representa
   bien algo que el renderer dibuja mal.
7. **Auditoría por toque, auditable**: un archivo por toque en
   `database/data/partituras-v4/revision/NN-nombre.md`, con hallazgos numerados
   (`D1`, `D2`, …), qué dice el PDF, qué dice el JSON cargado, y la propuesta. Se aplica
   después de revisión, no en el mismo movimiento.

---

## 6. Preguntas abiertas para la escuela

Lista viva; se completa mientras avanza la auditoría toque por toque.

- Cómo nombrar en la UI el agrupamiento del repertorio (hoy "año 1" a "año 6") sin
  implicar promoción. El criterio ya está: años = **saber acumulado** (aclarado por
  Santiago); falta la palabra que use la escuela ("año", "bloque", "tanda").
- Pasajes concretos ilegibles en el PDF (se van listando en los archivos de `revision/`).
- Tempo fino por toque dentro de la franja 80-90 (el cuadernillo no siempre lo imprime).

Ya resueltas:

- **"Todos" = unísono estricto** (Sacateca, Toque de Chilinga). Implementado como
  instrumento virtual `todos`.
- **Tempos del repertorio: 80 a 90 bpm.**
- **Años = dificultad por saber acumulado**, no nivel del alumno.

---

## Fuentes

- Wikipedia (ES), *La Chilinga*.
- Página/12, "La Chilinga cumplió 25 años durante la pandemia", Sergio Sánchez,
  28/12/2020 — <https://www.pagina12.com.ar/313916-la-chilinga-cumplio-25-anos-durante-la-pandemia/>
- Sudestada, "Daniel Buira: 'el tambor es el primer instrumento del ser humano'",
  Natalia Bericat, 16/03/2024 —
  <https://sudestadarevista.com.ar/daniel-buira-el-tambor-es-el-primer-instrumento-del-ser-humano/>
- Rolling Stone Argentina, fallecimiento de Daniel Buira, 21/03/2026.
- Instagram oficial `@lachilinga` (sedes y talleres 2026).
- Municipalidad de Tres de Febrero (Facebook), 25 años de La Chilinga.
- Discografía: *Percusión* (1998) — track "Sacateca" (1:53), referencia sonora del
  toque 13 del set.
