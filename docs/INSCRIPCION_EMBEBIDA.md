# Preinscripción embebida en otra web

El formulario de preinscripción (`/inscripcion`) se puede incrustar en cualquier página, igual que
el formulario de Festival JAS, sin cuenta ni API de por medio. Las cargas quedan en la bandeja
interna **Preinscripciones** (lista de espera; la administración aprueba o rechaza).

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

- **Envío:** el formulario se envía a su propia página (mismo origen dentro del iframe), así que no
  hace falta CORS ni `fetch`. Es el mismo criterio que festival-jas (POST oculto a un destino que
  no navega la página principal), pero el destino es la bandeja de La Chilinga.
- **Estilos:** la página usa los estilos de la propia app; no hereda los de la web que la contiene.
- **Cabeceras:** solo `/inscripcion` puede mostrarse en un iframe de otro sitio
  (`app/Http/Middleware/SecurityHeaders.php`, constante `INCRUSTABLES`). El resto de la app sigue
  con `frame-ancestors 'self'`.
- **Spam:** trampa para robots (campo oculto `sitio_web`) y límite de envíos por IP
  (`preinscripcion`: 3 por minuto y 20 por día).
- **Menores:** si la fecha de nacimiento es de menos de 18 años, pide los datos del tutor.
