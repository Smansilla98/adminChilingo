# Cómo grabar la biblioteca de sonidos

Los 27 WAV de esta carpeta son el instrumento de la app y de la web. El reproductor
no elige el archivo por el contenido: lo elige por el **nombre**. Si reemplazás
`surdo_grave_normal.wav` por una toma del surdo de la escuela, el próximo ensayo
suena con ese surdo. No hace falta otra versión de la app.

Hoy esos archivos son un reemplazo (librerías libres recortadas). No son la batería
de La Chilinga. Esta guía es para grabar los propios.

## Antes de reemplazar nada

El servidor, al arrancar, **vuelve a generar** estos WAV desde las librerías libres
si encuentra `ffmpeg`. Una grabación propia se pierde en el próximo deploy si no
se lo impedís.

1. Cuando ya tengas aunque sea un archivo propio en esta carpeta, creá un archivo
   vacío llamado `PROPIO` (sin extensión) al lado de los WAV.
2. En el servidor, dejá `PERC_KIT_REBUILD=0`.

Con `PROPIO` presente, `start.sh` no regenera el kit.

## Qué grabar

Un golpe por archivo, aislado. Sin conteo, sin otro tambor, sin sala hablando.
El flam y el acento del surdo **no se graban**: el flam son dos disparos del golpe
pleno, y el acento del surdo es el mismo pleno más fuerte. El acento de caja,
repique, agogó y palmas sí tiene archivo propio.

| Archivo | Qué es, en la mano |
| --- | --- |
| `surdo_grave_normal.wav` | Pleno, mano al centro. Corto: que muera cerca de 0,20 s. No es un boom largo. |
| `surdo_grave_tapado.wav` | La misma mano, apagando la piel. Más corto, cerca de 0,10 s. |
| `surdo_grave_chapa.wav` | Aro o chapa, seco y brillante. |
| `surdo_medio_normal.wav` | Igual que el grave, en el surdo medio. |
| `surdo_medio_tapado.wav` | Tapado del medio. |
| `surdo_medio_chapa.wav` | Aro del medio. |
| `surdo_agudo_normal.wav` | Pleno del agudo, más corto que el medio. |
| `surdo_agudo_tapado.wav` | Tapado del agudo. |
| `surdo_agudo_chapa.wav` | Aro del agudo. |
| `redoblante_normal.wav` | Golpe pleno de caja, con bordón como se usa en el bloque. |
| `redoblante_acentuado.wav` | El mismo golpe, más marcado. No es otro ritmo: es el mismo, más fuerte. |
| `redoblante_chapa.wav` | Aro. |
| `redoblante_agudo.wav` | El ping del borde (el triángulo del cuadernillo, el de Oxosi). No es un tom. |
| `repique_normal.wav` | Pleno de repique, más agudo que la caja. |
| `repique_acentuado.wav` | Acento de repique. |
| `repique_chapa.wav` | Aro de repique. |
| `repique_agudo.wav` | Ping de borde, un poco más agudo que el de la caja. |
| `timbal_abierto.wav` | Abierto, piel con el ataque de metal del timbal de la escuela. |
| `timbal_slap.wav` | Slap. |
| `timbal_palma.wav` | Palma. |
| `timbal_presionado.wav` | Presionado, choke corto. |
| `timbal_dedo.wav` | Dedos, más agudo. |
| `agogo_normal.wav` | Campana grave. Que no pinche: se va a sumar con todo el bloque. |
| `agogo_acentuado.wav` | Campana aguda. |
| `agogo_tapado.wav` | Campana tapada. |
| `palmas_normal.wav` | Una palmada, seca. |
| `palmas_acentuado.wav` | La misma palmada, más marcada. |

El norte del surdo grave es el que la escuela se hizo hacer con el zinguero: grave
y corto, no el surdo largo de una librería de batucada.

## Cómo grabar cada toma

1. Elegí un cuarto quieto. Menos vidrio y menos eco. Una frazada detrás del micrófono
   alcanza si la sala suena hueca.
2. El micrófono (el del teléfono sirve para empezar) a unos 20 o 40 cm, apuntando
   al centro de la piel, no al borde de la sala. En el aro, acercalo un poco más.
3. Un solo golpe. Esperá un segundo de silencio antes y después, para poder cortar.
4. Hacé tres o cuatro tomas y quedate con la que suena como en el bloque, no con la
   más fuerte.
5. El pico tiene que quedar abajo del tope. Si el medidor llega al rojo, alejás el
   micrófono o bajás la ganancia y repetís. Un golpe saturado no se arregla después.
6. Cortá el archivo: el ataque casi al principio (un par de milisegundos de aire
   antes) y el final cuando el sonido ya murió. No dejes la cola de la sala.
7. Exportá WAV, 44.1 kHz, 16 bits. Mono o estéreo, da igual. El nombre tiene que
   ser exactamente el de la tabla, en minúscula y con guión bajo.

Del teléfono al WAV, con ffmpeg:

```bash
ffmpeg -i toma.m4a -ar 44100 -acodec pcm_s16le surdo_grave_normal.wav
```

El corte, si hace falta, se puede hacer a oído en cualquier editor (Audacity,
la app del teléfono) antes de ese comando.

## Cómo dejarlos en la app

1. Copiá los WAV a `public/sounds/perc/`, pisando los que reemplazás. Los que
   todavía no grabaste pueden seguir siendo los actuales.
2. Creá el archivo vacío `public/sounds/perc/PROPIO`.
3. En el servidor, `PERC_KIT_REBUILD=0`.
4. Abrí un toque en la app y dale a Escuchar. La app baja de nuevo un WAV cuando
   cambia la fecha o el tamaño del archivo. Si no cambia nada, cerrá la app y
   volvé a entrar.

No renombres los archivos y no agregues otros: un nombre que no está en la tabla
no suena, y uno que falta deja ese golpe en silencio (el acento del surdo y el
flam siguen sonando, porque salen del pleno).
