# Identidad visual de La Chilinga

Guía para diseñar piezas (redes, afiches, merch, pantallas) que se sientan **Chilinga**.

> **No existe un manual de marca oficial publicado.** Una búsqueda extensa en la web
> (octubre de 2026) no encontró ninguna fuente sobre los logos, su autoría, la paleta, la
> tipografía, el merch ni el estilo de los flyers. Esta guía se reconstruyó a partir de los
> logos que hay en el repo, la paleta del sistema y lo que la escuela comunica.
> Lo **medido** sale de las imágenes; lo **a confirmar** está marcado. Ante una pieza oficial
> nueva de la escuela, gana la pieza: actualizá esta guía.

---

## 1. Dos marcas, dos usos

| Marca | Qué es | Archivo | Dónde va |
|---|---|---|---|
| **La Chilinga** | La escuela y su comunidad. Versión vigente: logo **30 años · 1995–2025** | `public/images/brand/chilinga-30.png` (JPEG 1024×566, **fondo negro**) | Toda pieza para la comunidad: flyers, historias, afiches, merch, portadas, páginas públicas |
| **ITO** | La plataforma: "Sistema de gestión popular" | `public/images/brand/logo.png` (PNG 1376×768, fondo transparente; favicons en la misma carpeta) | La interfaz del sistema (login, menú, errores). Nunca reemplaza a La Chilinga en una pieza de la escuela |

Regla: **la escuela habla con La Chilinga; el sistema firma con ITO.**

## 2. Lo que se ve en los logos

### La Chilinga 30 años
- Fondo **negro**. El nombre en **blanco**: `·LA CHILINGA·`, en mayúsculas condensadas y muy
  pesadas, con **letras de alturas alternadas** (la *A* chica, *CHI* grande, la *I* con punto
  cuadrado): se lee como letras recortadas o de sello, con ritmo propio.
- **Puntos que flanquean el nombre** (`·` a cada lado). Es un recurso de marca, también presente en
  ITO.
- El **30** se arma con **arcos gruesos** verde, celeste y naranja que se cruzan: aros, parches,
  ondas de tambor.
- **AÑOS** en naranja, condensada; **1995 • 2025** en celeste con un punto en el medio.

### ITO
- Letras **pintadas a mano** (borde irregular, como pincel o fibrón): *i* celeste, *T* naranja,
  *O* verde, y puntos a los costados.
- Bajada "SISTEMA DE GESTIÓN POPULAR" en mayúsculas condensadas.

**Lectura de la identidad:** popular, artesanal, de calle; color pleno sobre negro; formas
redondas como parches de tambor; ritmo en la propia tipografía. Nada corporativo, nada infantil.

## 3. Color

| Token (`public/css/chilinga-admin.css`) | Sistema | Medido en el logo 30 años | Sobre negro | Sobre blanco | Uso |
|---|---|---|---|---|---|
| `--ch-negro` | `#000000` | fondo | — | — | Fondo de marca. Las piezas de comunidad funcionan mejor sobre negro |
| `--ch-blanco` | `#ffffff` | nombre | 21:1 | — | Nombre y textos sobre negro |
| `--ch-naranja` | `#f26422` | ≈ `#e8540c` | 6,6:1 ✓ | 3,2:1 (solo títulos grandes) | Color protagonista: acción, "AÑOS", acentos |
| `--ch-verde` | `#3daf3a` | ≈ `#5aac34` | 7,4:1 ✓ | 2,8:1 ✗ texto | Formas, arcos, ilustración |
| `--ch-celeste` | `#3ec8ea` | ≈ **`#0093cf`** (más profundo) | 10,6:1 ✓ | 2,0:1 ✗ texto | Formas, fechas sobre negro |
| `--ch-rojo` | `#e31b23` | **no aparece** | 4,5:1 | 4,7:1 | Solo en la franja de 4 colores del login. Uso de marca **a confirmar** |

Para la interfaz (sobre blanco) el sistema usa variantes accesibles: `--accent-strong` `#b8470f`
(texto naranja, 5,3:1) y `--primary` `#c64c12` (botón con texto blanco, 4,7:1).

Reglas:
1. **Sobre negro**, los tres colores de marca se usan libres, incluso para texto.
2. **Sobre blanco**, verde y celeste no van en texto: solo formas o bloques grandes. El naranja,
   solo en títulos grandes (≥ 24 px negrita); para texto chico, `--accent-strong`.
3. Color pleno, sin degradados entre colores de marca. El "arcoíris" de la marca es una
   **franja de bloques** (`naranja · verde · celeste · rojo`, ver `.auth-aside::after`), no un
   degradé.
4. **A confirmar con la escuela:** celeste exacto (el logo 30 años usa un azul cian más profundo
   que `#3ec8ea`) y si el rojo es color de marca.

## 4. Tipografía

- **Logotipo:** no se identificó la familia (condensada pesada, alturas alternadas). **No se
  redibuja el nombre con una fuente**: se usa el archivo del logo. *A confirmar:* fuente o archivo
  vectorial original.
- **Interfaz y piezas digitales:** **Manrope** (títulos 800, cuerpo 400–600). Cada persona puede
  elegir otra en Configuración › Apariencia (Space Grotesk, Sora, Outfit, DM Sans para títulos;
  Inter, Source Sans 3, Nunito Sans, IBM Plex Sans para cuerpo): el diseño no debe depender de una
  sola.
- **Afiches y redes:** títulos en mayúsculas condensadas y pesadas, en diálogo con el logo; cuerpo
  en Manrope. Jerarquía fuerte, pocas palabras.
- **Partituras:** negro sobre blanco, tipografía de partitura (`--font-score`), sin decoración.

## 5. Recursos gráficos

- **Los puntos** `·` flanqueando nombres y fechas (`·LA CHILINGA·`, `1995 • 2025`).
- **Arcos y círculos**: parches, aros, ondas. Usados con peso, nunca como clip-art de tambor.
- **Ritmo como forma**: grillas de pasos, patrones que se repiten, compases (el editor de ritmos ya
  dibuja grillas). Sofisticado, nunca un dibujito.
- **La franja de 4 colores** como firma (login, separadores, cierre de piezas).
- **Lo artesanal**: trazo de pincel o fibrón (como ITO), letras recortadas. Una crónica de 2006
  describe la sede de Martín Coronado con tambores *"dibujados en las paredes"* (Ciudad.com,
  20/12/2006): la escuela se dibuja a mano.
- **Tambores y pañuelo**: la remera de la escuela para el 24 de Marzo lleva *"los tambores y
  el pañuelo insignia de las Madres de Plaza de Mayo"* (Liska, *TRANS* 26, 2022). Es la única
  pieza de indumentaria institucional documentada; el pañuelo blanco es parte de la identidad.

## 6. Fotografía e ilustración

- La escuela **tocando**: el bloque en la calle, en la sede, en marchas y fechas (sobre todo el
  24 de Marzo), en la gira de Villa Gesell, en los aniversarios (30 años: más de 300 tambores en
  la Plaza de los Aviadores de El Palomar). Gente real de todas las edades, en movimiento, con sus tambores.
- Nada de fotos de stock ni de "batucada genérica".
- **Menores:** solo con consentimiento y sin datos que los identifiquen (nombre completo, escuela,
  sede + horario). El Archivo histórico y la Biblioteca son las fuentes de fotos del editor de
  Diseño.

## 7. Voz

- Castellano **rioplatense con voseo**, cálido, directo, popular. Comprometido con los derechos
  humanos, nunca solemne de más.
- La escuela se nombra **"Escuela Popular de Percusión"**; en Instagram, "La Chilinga
  Percusión".
- Frases con fuente (citarlas tal cual y con su origen):
  - *"Todos/as queremos, podemos y debemos tocar el tambor. ¡Hace bien!"* — Programa de la
    escuela (fuente interna; no aparece en la web).
  - *"no hay exámenes: todos tocan, nadie se queda afuera"* — Página/12, 28/12/2020. Es la
    descripción del periodista, **no un lema de Buira**: no firmarla con su nombre.
  - De Dani Buira: *"Donde está La Chilinga está el pañuelo de las Madres"*; *"Si no la podés
    pagar, no la pagás. Porque es popular"*; *"el tambor es el primer instrumento del ser
    humano"* (Sudestada, 16/03/2024); *"Los tambores son de lucha, no de baile"* (título de
    Página/12).
- Nunca lenguaje de evaluación ("nivel", "aprobado", "avanzado").

## 8. Formatos (editor de Diseño)

| Formato | Medida (px) | Para |
|---|---|---|
| `flyer_feed` | 1080 × 1350 | Instagram feed |
| `post_cuadrado` | 1080 × 1080 | Feed cuadrado |
| `historia` | 1080 × 1920 | Historias / reels (dejá libres ~250 px arriba y abajo) |
| `afiche_a4` | 1240 × 1754 | Afiche A4 |
| `flyer_a5` | 874 × 1240 | Volante A5 |
| `banner_web` | 1200 × 628 | Web y vista previa de enlaces |
| `hoodie_20x40` | 2362 × 4724 | Estampa de buzo (20 × 40 cm) |
| `parche_10x10` | 1181 × 1181 | Parche bordado (10 × 10 cm): pocos colores, nada de detalle fino |

Maquetas de merch: `public/images/diseno/mockups/` (buzo, campera, camiseta, bolsa). Kit de marca
compartido: pestaña **Marca** del editor (`DisenoKitAsset`).

## 9. Qué evitar

- Dashboard o pieza **SaaS azul/gris** genérica; plantillas de Bootstrap sin intervenir.
- **Infantil o caricatura**: tambores con carita, tipografías "divertidas", exceso de colores.
- Clichés de batucada brasilera (verde-amarelo, plumas, carnaval). La Chilinga es rioplatense.
- Deformar, recolorear o recortar el logo; ponerlo sobre fondos sin contraste (el 30 años va
  sobre negro).
- Emojis y degradados pesados en la interfaz; animación que no aporta.

## 10. Canales

- **Instagram `@lachilinga`** es el único canal oficial verificable (unos 34.500 seguidores en
  octubre de 2026). La bio lista las sedes y enlaza un formulario. Destacadas: "En Lucha",
  "Taller Inicial", "Gira Gesell", "Discografía", "Muestras", entre otras.
- **lachilinga.com.ar no resuelve** (7/10/2026). No hay canal oficial de YouTube conocido.

## 11. Pendiente con la escuela

- [ ] Manual de marca o piezas oficiales de referencia.
- [ ] Logo en **vector** (SVG o PDF) y versión para fondo claro: hoy solo hay el JPEG sobre negro.
- [ ] Familia tipográfica del logotipo.
- [ ] Celeste exacto y si el rojo es parte de la marca.
- [ ] Logo histórico (anterior a los 30 años) y reglas de convivencia entre ambos.
- [ ] Autoría del logo de los 30 años.
- [ ] Si "nadie se queda afuera" y "queremos, podemos y debemos tocar el tambor" se adoptan
      formalmente como frases de la escuela.
