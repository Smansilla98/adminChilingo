# OpenDesign en La Chilinga

Este directorio contiene el cliente de **OpenDesign** (https://github.com/clawnify/OpenDesign,
commit `a0f8cbb`, licencia MIT, © 2026 Clawnify — ver `LICENSE`), integrado como editor del
módulo Diseño del panel.

Cambios respecto del original:

- El backend (Hono + Cloudflare D1/R2) se reemplazó por Laravel:
  `App\Http\Controllers\DisenoEditorController` sobre las tablas `disenos` y `diseno_paginas`.
  `api.ts` usa la sesión del panel y el token CSRF; `config.ts` recibe la configuración de la vista Blade.
- Rutas bajo `/disenos` (`hooks/use-router.ts`); sin la navegación embebida de Clawnify.
- Interfaz en español, colores de La Chilinga, tamaños del editor anterior (flyer feed, historia,
  afiches, hoodie, parche…).
- Pestaña **Marca** (`components/marca-panel.tsx`): logos, kit de marca y fotos de la Biblioteca.
- Miniatura del diseño al guardar (`getThumbnailForPage`).
- Confirmación al eliminar y acciones visibles en pantallas táctiles.

Se compila con `vite.opendesign.config.js` (`npm run build`).
