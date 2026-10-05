# Archivo histórico fotográfico

La memoria fotográfica de La Chilinga, navegable en el tiempo. Vive dentro del mismo
Laravel: misma sesión, mismos permisos con alcance, misma auditoría, mismas
notificaciones y el mismo disco persistente. No es una app aparte.

## 1. Diagnóstico (auditoría previa)

| Área | Qué hay | Decisión |
|---|---|---|
| Frontend web | Blade + Bootstrap 5 (CDN) + CSS/JS estático en `public/` (`biblioteca.css`, `ito-*.js`). Tailwind/Vite solo para el editor de partituras. | Vistas Blade, un CSS y un JS propios del archivo. Sin dependencias nuevas: scroll, visor, gestos y orden con APIs nativas (IntersectionObserver, Pointer Events, HTML5 DnD). |
| Público | `layouts.publico` (Programa, Partituras, Agenda, Biblioteca) sin login. | Layout propio `layouts.archivo` (oscuro, editorial) enlazado desde la barra pública. |
| Autorización | Permisos granulares con alcance (`config/permisos.php`, `acceso()->alcance()`), `Gate::before`, Policies por modelo, middleware `permiso:`. | Grupo nuevo de permisos `archivo.*` + Policies. Rol nuevo `archivista`. |
| Personas | `personas` es la identidad única (alumnos, profesores, cuentas). Tiene datos sensibles y menores. | Las fotos se vinculan a `personas` (sin duplicar). Para gente que no está en el sistema se admite un nombre libre. El público solo ve el nombre. |
| Sedes / Eventos / Shows | `sedes`, `eventos` (agenda operativa con horario), `shows`. | Se reutilizan por FK. Un "acontecimiento" histórico es una entidad editorial (relato, portada, capítulo) que **puede** apuntar a un `evento` o `show` existente; no los reemplaza. |
| Etiquetas | `biblioteca_tags` (hashtags normalizados). | Se reutiliza la misma tabla con un pivot propio `archivo_foto_tag`. |
| Biblioteca | Aportes anónimos multi-formato publicados al instante, sin derivados. | No sirve como base: el archivo necesita moderación, procedencia y derivados. Se comparten tags y el disco. |
| Almacenamiento | Disco `comprobantes` = volumen persistente (`PERSISTENT_STORAGE_PATH`). `public` no persiste en Railway. | Originales y derivados en `comprobantes`. Los derivados se sirven por ruta con caché larga; los originales nunca son públicos. |
| Imágenes | PHP GD + exif (Dockerfile). El `gd` del Dockerfile no se configuraba con JPEG/WebP. | Se configura `gd --with-jpeg --with-webp --with-freetype`. Derivados WebP; si el servidor no tuviera WebP, JPEG. |
| Colas | `QUEUE_CONNECTION=database` sin worker en `start.sh`. | Procesamiento síncrono, **una foto por request** (la carga masiva sube de a una con progreso). Comando `archivo:derivados` para regenerar. |
| Notificaciones | `NotificacionService::enviar()` (interna + push, idempotente). | Se reutiliza para avisar al aportante. |
| Auditoría | Trait `Auditable`. | En capítulos, acontecimientos y fotos, más un historial de moderación visible para el aportante. |
| API / app | `/api/v1` con Sanctum; la app Expo muestra módulos según `/me.modulos`. | Endpoints `/api/v1/archivo/*` con los mismos servicios. Pantallas de aporte y moderación en la app. |

Riesgos: tamaño de originales (límite 40 MB, `memory_limit` subido solo durante el
proceso); HEIC no lo decodifica GD (se pide JPG/PNG/WebP; los navegadores móviles ya
convierten al elegir de la galería); privacidad de personas (solo nombre en público,
la búsqueda de personas del sistema queda para quien gestiona el archivo); GPS del
EXIF (no se copia a la base ni a los derivados; queda solo en el original).

## 2. Modelo de datos

```
archivo_capitulos         capítulo editorial (Fundación, Los Piojos, 30 años…)
  titulo, slug, bajada, descripcion, anio_desde, anio_hasta, portada_foto_id, orden, publicado

archivo_acontecimientos   un hecho fechado dentro de un capítulo
  titulo, slug, fecha, anio, precision(dia|mes|anio|aprox), bajada, descripcion, relato,
  lugar, ciudad, pais, latitud, longitud      ← preparado para el mapa futuro
  capitulo_id, sede_id, evento_id, show_id, portada_foto_id, orden, publicado
archivo_acontecimiento_relacion   (a, b) acontecimientos relacionados

archivo_fotos             la fotografía + su procedencia + su estado
  titulo, slug, descripcion (epígrafe), contexto, notas_aportante, alt_text, tipo
  fecha, anio, mes, precision, lugar, ciudad, pais, latitud, longitud
  fotografo  (quién la tomó)   fuente + fuente_detalle (de dónde viene)   credito, licencia
  aportada_por (quién la subió) + mostrar_aportante
  capitulo_id, acontecimiento_id, sede_id, destacada, orden
  estado: borrador | pendiente | cambios | rechazada | publicada | oculta
  enviada_at, revisada_por, revisada_at, notas_revision, motivo_rechazo, publicada_at
  path (original), nombre_original, mime, bytes, ancho, alto, hash (sha256), exif, derivados, placeholder, color
archivo_foto_tag          foto ↔ biblioteca_tags
archivo_foto_persona      foto ↔ persona_id | nombre libre, detalle ("tercera desde la izquierda")
archivo_revisiones        historial de moderación (acción, estado anterior/nuevo, notas, quién)
```

Aportante, fotógrafo y fuente son tres campos distintos y no se mezclan.

## 3. Flujo de estados

```
aporte de usuario:  borrador → pendiente → publicada
                                   ├→ cambios → (el aportante corrige) → pendiente
                                   └→ rechazada
carga del equipo:   borrador → publicada ⇄ oculta
```

Un usuario común solo edita o borra lo suyo mientras está en borrador, pendiente o
cambios (borrar también si fue rechazado). Lo publicado solo lo toca el equipo.

## 4. Permisos

| Permiso | Para |
|---|---|
| (estar logueado y activo) | Aportar y seguir los propios aportes |
| `archivo.view` | Ver el backoffice y el material no publicado |
| `archivo.manage` | Cargar, editar, ordenar, capítulos, acontecimientos, etiquetas |
| `archivo.moderate` | Aprobar, rechazar y pedir cambios |
| `archivo.publish` | Publicar y ocultar |
| `archivo.delete` | Eliminar material |

Alcance: con alcance global se opera todo; con alcance de sede, el material de esa
sede. Los capítulos son de toda la escuela (solo alcance global). Rol nuevo
`archivista` con todos los `archivo.*`. Administración ya los tiene por `*`.

## 5. Rutas

Públicas (indexables, OpenGraph, Twitter card, canonical y JSON-LD):

```
/archivo                         portada + línea de tiempo + capítulos
/archivo/historia                Story Mode (relato continuo)
/archivo/{anio}                  un año
/archivo/capitulos/{slug}
/archivo/eventos/{slug}          acontecimiento
/archivo/fotos/{slug}            ficha de una foto
/archivo/buscar                  búsqueda + filtros
/archivo/img/{id}/{ancho}        derivado WebP (400|800|1200|2048), caché 1 año
```

Usuario registrado: `/archivo/aportar`, `/archivo/mis-aportes`, `/archivo/mis-aportes/{id}`.

Equipo: `/archivo/gestion` (tablero), `/fotos`, `/subir`, `/capitulos`, `/eventos`,
`/etiquetas`, `/moderacion`.

API: `/api/v1/archivo/...` (lectura pública + aportes + gestión), ver `docs/API.md`.

## 6. Imágenes

```
original (intacto, con EXIF; nunca público)  archivo/originales/AAAA/MM/uuid.ext
derivados WebP sin metadatos                 archivo/derivados/{id}/{400|800|1200|2048}.webp
placeholder 24px en base64 + color medio      en la fila (pinta antes de cargar)
```

Se respeta la orientación EXIF al generar derivados. `srcset`/`sizes` y
`loading="lazy"` en todas las grillas; el visor pide 2048 solo al abrir una foto.
Duplicados: mismo hash SHA-256 → aviso, nunca borrado automático.

## 7. Experiencia

- **Escritorio**: portada con foto a sangre, índice de décadas fijo a la izquierda con
  el año activo, capítulos como secciones a pantalla completa (hero, editorial,
  collage, retrato, secuencia), progreso de lectura, teclado (←/→, Esc, `/` busca).
- **Móvil**: lectura vertical, selector de década deslizable abajo (`‹ 1990 — 1999 ›`),
  fotos a ancho completo, visor con swipe y pinch, metadatos en hoja desplegable,
  filtros en bottom sheet.
- `prefers-reduced-motion`: sin parallax ni revelados.
- Audio ambiente: no se implementa en esta etapa (no hay material sonoro curado); el
  Story Mode deja el lugar para un botón opcional que nunca suena solo.

## 8. Pendientes conocidos

- Mapa (los campos de lugar y coordenadas ya existen).
- Hash perceptual para duplicados visuales.
- AVIF (el GD del servidor no lo trae).
