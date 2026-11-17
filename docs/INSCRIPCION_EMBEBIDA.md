# Preinscripción embebida en otra web

El formulario de preinscripción (`/inscripcion`) se pega en la página como el de Patuka: un formulario
que se completa **por pasos** y cuyos envíos llegan a la bandeja interna **Preinscripciones**
(lista de espera; la administración aprueba o rechaza). No hace falta cuenta ni API.

## Pasos

1. **Sede, bloque y nombre.** Crea la preinscripción (en curso) y da un link con token (`?p=…`).
2. **Datos personales:** DNI (opcional) y fecha de nacimiento.
3. **Contacto:** teléfono, correo (opcional) y, si es menor de 18, los datos del tutor.
4. **Confirmación:** comentario opcional. Queda en lista de espera.

El link con `?p=…` deja seguir donde se quedó. Cada paso se envía en orden: no se puede saltar ni
repetir un paso ya completado.

## Avisos al equipo

Cada paso completado avisa a quienes tienen permiso para inscribir alumnos (`alumnos.create`), por la
bandeja interna y por correo si la cuenta tiene uno. La clave de cada aviso incluye el paso, y
`NotificacionService` la deduplica: un mismo paso nunca avisa dos veces. Los avisos muestran lo que
va cargado hasta ese momento; el del paso 4 es «Preinscripción completa».

## Cómo se incrusta

Un iframe apuntando a la URL pública. La página se ajusta sola a la altura del formulario
(avisa su altura con `postMessage`):

```html
<iframe
  id="inscripcion-chilinga"
  src="https://admin-chilingo.up.railway.app/inscripcion"
  title="Preinscripción a La Chilinga"
  style="width: 100%; min-height: 900px; border: 0;"
  loading="lazy"></iframe>
<script>
  // Ajusta el alto del iframe al formulario (solo acepta mensajes de la propia chilinga).
  window.addEventListener('message', function (e) {
    if (!e.origin.startsWith('https://admin-chilingo.up.railway.app')) return;
    if (!e.data || e.data.tipo !== 'chilinga:inscripcion:altura') return;
    document.getElementById('inscripcion-chilinga').style.height = e.data.altura + 'px';
  });
</script>
```

Reemplazá el dominio por el de producción (`APP_URL`).

## Qué hace cada parte

- **Envío:** cada paso es un POST a `/inscripcion/paso/{n}` dentro del iframe (mismo origen): no hace
  falta CORS ni `fetch`. El token viaja en el formulario, no en cookies, así que funciona dentro de un
  iframe de otro sitio.
- **Estilos:** la página usa los estilos de la propia app.
- **Cabeceras:** solo `/inscripcion` y `/inscripcion/*` pueden mostrarse en un iframe de otro sitio
  (`app/Http/Middleware/SecurityHeaders.php`, constante `INCRUSTABLES`). El resto de la app sigue con
  `frame-ancestors 'self'`.
- **Spam:** campo trampa (`sitio_web`) en cada paso y límite de envíos por IP (`preinscripcion`:
  12 por minuto y 60 por día).
- **Menores:** si la fecha de nacimiento es de menos de 18 años, el paso 3 pide los datos del tutor.
