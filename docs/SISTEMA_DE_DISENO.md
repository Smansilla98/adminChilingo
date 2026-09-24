# Sistema de diseño del panel

Guía para mantener la interfaz consistente. Todo vive en `public/css/chilinga-admin.css` (sin frameworks nuevos: Bootstrap 5.3 por CDN + bootstrap-icons).

## Principios

- Claro por defecto, con modo oscuro opcional. Menos decoración y más jerarquía: sin degradados, sin sombras pesadas y sin emojis.
- La identidad de La Chilinga aparece en el logo, el menú lateral oscuro, el naranja de las acciones y del estado seleccionado, y la franja de 4 colores del login.
- Una acción principal por pantalla (`btn-primary`). El resto usa `btn-outline-secondary`, o `btn-ghost` dentro de las tablas.
- Los estados no se comunican solo con color: siempre llevan un texto (`<x-ito.status>`).

## Tokens

Se definen en `:root` / `[data-bs-theme="light"]` y `[data-bs-theme="dark"]`, y también alimentan las variables de Bootstrap (`--bs-*`).

| Token | Uso |
|---|---|
| `--bg`, `--s1`, `--s2`, `--s3` | Fondo general, superficie (tarjetas), superficie tenue (cabeceras de tabla), relleno (chips) |
| `--border`, `--border-strong` | Bordes suaves / bordes de campos |
| `--text`, `--text-2`, `--muted` | Texto principal, secundario y de apoyo (todos ≥ 4.5:1) |
| `--accent` | Naranja de marca `#f26422`: indicadores, foco, día de hoy |
| `--primary`, `--primary-hover`, `--primary-on` | Botón principal (`#c64c12` con texto blanco = 4.7:1) |
| `--accent-strong` | Enlaces y texto en naranja (5.3:1) |
| `--success/-soft/-line`, `--warning…`, `--danger…`, `--info…` | Estados |
| `--sb-*` | Menú lateral (oscuro en los dos temas) |

Las tipografías (`--font-display`, `--font-body`) y el acento se pueden personalizar por usuario desde **Configuración › Apariencia**. `AparienciaTema::cssVariables()` ajusta el acento elegido hasta que tenga contraste AA en claro y en oscuro.

## Tema

- Preferencia por usuario: `claro` (predeterminado), `oscuro` o `sistema`. Se guarda en `users.apariencia_json.tema`, sin migración.
- `layouts/partials/apariencia-head.blade.php` fija `data-bs-theme` en `<html>` antes de pintar la página, así no hay parpadeo.
- Cambio rápido desde el menú de usuario (ruta `apariencia.tema`).

## Estructura

- **Menú lateral**: se arma en `App\Support\Navegacion` (con los mismos permisos que antes) y se dibuja en `sidebar-nav.blade.php`. Grupos plegables que se recuerdan en el navegador. En escritorio se puede contraer a solo íconos; en celular es un cajón.
- **Barra superior** (`topbar-hub.blade.php`): migas (salen de `Navegacion`), título, buscador (Ctrl+K), campana de avisos (notificaciones internas) y menú de usuario (contexto, tema, texto grande, alto contraste, cerrar sesión).
- **Contenido**: ancho máximo de 1440 px.

## Componentes Blade (`resources/views/components/ito`)

| Componente | Para qué |
|---|---|
| `x-ito.list-page` | Listado: encabezado, filtros (plegables en celular), tabla, pie con paginación |
| `x-ito.shell-page` | Alta, edición y ficha: encabezado + tarjeta |
| `x-ito.actions` | Menú de acciones por fila (⋮) |
| `x-ito.status` | Insignia de estado con punto y texto (`success`, `warning`, `danger`, `info`, `neutral`) |
| `x-ito.empty` | Estado vacío con ícono, texto y acción |
| `x-ito.person` | Avatar con iniciales + nombre |
| `x-ito.filter-chips` | Filtros activos con "Limpiar" |
| `x-ito.form-steps` / `x-ito.form-step` | Formularios por pasos |
| `x-ito.skeleton` | Esqueleto de carga para tablas |
| `x-ito.form-section` | Bloque de formulario con título, ícono y ayuda |
| `x-ito.form-actions` | Pie de formulario: Cancelar + acción principal (fijo abajo al hacer scroll) |
| `x-ito.detail-section` | Sección de ficha con título y acciones propias (`:flush` para tablas) |
| `x-ito.facts` / `x-ito.fact` | Tira de datos clave arriba de una ficha |
| `x-ito.tabs` / `x-ito.tab` | Pestañas de una ficha (se puede abrir una con `#clave` en la URL) |

## Patrones de pantalla

- **Formulario**: `<x-ito.shell-page :plain="true">` → `<form>` con varias `x-ito.form-section` y al final `x-ito.form-actions`. Alta y edición comparten un `_form.blade.php`; el ancho máximo es 1040 px.
- **Ficha**: `<x-ito.shell-page :plain="true">` con estado + Volver + Editar (primario) en `actions`, después `x-ito.facts`, y luego `x-ito.detail-section` (en `.ito-detail-grid` de dos columnas) o `x-ito.tabs` si hay mucha información.
- **Campos**: `label.form-label` + control. `ito-a11y.js` asocia la etiqueta si falta el `for`, marca los obligatorios con * a partir de `required` y ubica los errores del servidor debajo de cada campo.
- **Blade**: no pegar directivas a una palabra (`texto@if(...)` no compila) ni mezclar `@php(...)` en línea con bloques `@php ... @endphp` en el mismo archivo.

## Editor de Diseño (OpenDesign)

Usa Tailwind 4 propio (`resources/opendesign/src/styles.css`) con los mismos tokens: `primary` (botón), `accent` (marca), `accent-strong` (texto naranja) y tipografía Manrope. El editor se mantiene en tema claro (el lienzo se diseña sobre fondo claro).

## Comportamientos globales (JS)

- `ito-shell.js`: cajón, grupos, menú contraído, buscador en celular, avisos flotantes (`window.itoToast(msg, 'success')`) y botón en espera al enviar formularios POST (se desactiva con `data-no-loading`).
- `ito-a11y.js`: confirmaciones con `data-confirm="¿Eliminar este alumno?"`. El título, la descripción y el botón se deducen del mensaje. Si hace falta, se sobrescriben con `data-confirm-text`, `data-confirm-ok` y `data-confirm-tone="danger"`.
- `ito-tables.js`: en celular las tablas de listados pasan a fichas. Para excluir una tabla: `data-ito-no-cards`.

## Mensajes

- `session('success')` → aviso flotante que se cierra solo.
- `session('error')` y errores de operación → aviso flotante que queda hasta que lo cierren.
- Errores de validación → resumen arriba + mensaje bajo cada campo (`.invalid-feedback`).

## Páginas de error

`resources/views/errors/` (403, 404, 419, 429, 500, 503). No dependen de la base de datos.
