# Cómo escribe la escuela (Cuadernillo de Toques)

Leído del PDF completo (61 páginas) y verificado ampliando las páginas 4, 5 y 6. VIEWED 2026-10-13.
Es la referencia de grabado del editor (`instruments.js`, `renderer.js`).

## Pentagrama

| Qué | Cómo lo escribe el cuadernillo | En el editor |
| --- | --- | --- |
| Pentagrama | 5 líneas, clave de percusión, un instrumento (o grupo) por pentagrama | igual |
| Cabeza | todas en el **2º espacio**, sin importar el instrumento | `pitch: a/4` |
| Plica | **arriba**; barras horizontales arriba | `stem: 1`, `flat_beams` |
| Barras | por tiempo: 2 corcheas / 4 semicorcheas / 8 fusas; en 6/8, de a 3 corcheas | `gruposDeBeam()` |
| Acento `>` y guión `—` | **debajo** del pentagrama, todos a la misma altura | articulación forzada fuera del pentagrama |
| Grupos irregulares | corchete con número arriba (`3`, `6`) | `Tuplet` con corchete |
| Compás | `C` (nunca «4/4»); también 6/8, y 2/4 o 1/4 intercalados | `C` y 6/8; ver *Pendiente* |
| Cierre de parte | doble barra fina; la gruesa sólo al final del toque | `DOUBLE` / `END` |
| Repetición | `(x4)` junto al nombre de la parte; barras de repetición y casillas 1/2 en pocos toques | `section.repeatX`, `repeatBegin/End`, `ending` |
| Matices | sólo `p` y `f` con regulador (crescendo; diminuendo en Buscando a Coco) | `dyn` por nota; el regulador no se dibuja |
| Rótulos | parte en MAYÚSCULAS, debajo el instrumento, los dos **arriba** del pentagrama | parte arriba, instrumento a la izquierda |

## Golpes (hoja Nomenclatura, PDF pág. 5)

| Instrumento | Golpe | Signo | `stroke` |
| --- | --- | --- | --- |
| Surdos | Nota | cabeza negra | `nota` |
| Surdos | Chapa | cruz | `chapa` |
| Surdos | Tapado | negra + guión debajo | `tapado` |
| Redoblante | Nota / Acentuado / Chapa | negra / negra + `>` debajo / cruz | `nota` / `acentuado` / `chapa` |
| Timbal | Abierto | cabeza negra | `abierto` |
| Timbal | Slap | **cabeza hueca** (lleva plica y barras: no es una blanca) | `slap` |
| Timbal | Palma | **rombo negro** | `palma` |
| Timbal | Presionado | negra + guión debajo | `presionado` |
| Timbal | Dedo | cruz | `dedo` |
| Repique | Nota / Acentuado / Chapa | como el redoblante | `nota` / `acentuado` / `chapa` |
| Repique | Agudo | **triángulo negro** | `agudo` |

Fuera de la hoja pero usado en los toques: mordente (notita con ligadura, Muñequitos I),
ligadura de prolongación, y dos cabezas a la vez en el mismo golpe (negra + cruz en surdos:
La Meta, Malamakuá, Solo de Surdos).

## Agrupación de instrumentos

El cuadernillo junta en un pentagrama a los que tocan lo mismo y lo dice en el rótulo:
«Todos», «Surdos Grave, Agudo y Medio», «Surdos Grave y Agudo», «Redoblante y Repique»,
«Redoblante, Timbal y Repique», «Surdo Medio y Repique». No es fijo: cambia en cada parte.
Otros nombres: «Surdo Base» / «Surdo Melodía» (Muñequitos I), «Director (Timbaletas)»
(Buscando a Coco), «Agogó», «Palmas», «Campana».

## Qué se corrigió en el editor (2026-10-13)

- Cabeza en el 2º espacio y plica arriba (estaba en la línea del medio con plica abajo).
- Slap con cabeza hueca (salía como cruz dentro de un círculo).
- Palma con rombo negro (salía igual que el golpe abierto).
- Triángulo y rombo siempre negros, sin depender de la figura.
- Acentos y guiones debajo del pentagrama (caían adentro).
- Doble barra fina al cerrar cada parte.
- Redoblante y repique comparten pentagrama sólo cuando tocan lo mismo en esa parte.
- Los compases se reparten por línea según su densidad; ya no se salen del pentagrama.
- MusicXML: plica `up`, `display-step` A, slap como `<notehead filled="no">`.

## Pendiente

**Datos.** Los 26 toques se retranscribieron contra el PDF el 2026-10-13 siguiendo la
Nomenclatura. Lo que el escaneo no dejó leer con certeza quedó marcado `Revisar: …` en el
compás; la lista está en
[`revision/PENDIENTES.md`](../../database/data/partituras-v4/revision/PENDIENTES.md).
Los más flojos: Chilinga II (toda la página), el solo de repique y los seisillos de La Meta,
Mongo kutá, el Solo de Timbales y los acentos de Toque a Oxosi.

**Editor.**

- Rótulo del instrumento arriba del pentagrama y agrupaciones por parte (hoy sólo
  «Redoblante y Repique» y «Todos»).
- Cambio de compás dentro del toque (2/4 en Marcha Camión, Rumba, Sacateca y Chiruda; 1/4 en
  Makuta; 6/8 ↔ C en los solos). El modelo tiene un solo `timeSignature`.
- Dos cabezas en un mismo golpe (negra + cruz).
- Reguladores `p < f`.
- Palmas y Campana se escriben con cruz; el Agogó mezcla cruz y negra (las dos campanas).
  Falta confirmar con la escuela cuál es cuál antes de tocar el catálogo.
