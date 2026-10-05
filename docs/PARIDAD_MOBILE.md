# Paridad Web ↔ App móvil

Rama: `feature/mobile-full-parity`. Objetivo: que ninguna operación administrativa
requiera abrir el panel web. La web y la app son dos clientes del mismo backend.

## Cómo se hizo

- **Una sola lógica de negocio.** Las reglas que vivían dentro de los controladores web
  se movieron a servicios de `app/Domain` (o a `FormRequest`). El controlador web y el
  de la API (`app/Http/Controllers/Api/V1`) llaman al mismo servicio y usan las mismas
  Policies. La web no cambió de comportamiento (la suite existente sigue en verde).
- **Sin endpoints duplicados.** Donde la web ya tenía una API JSON (editor de Diseño)
  se expuso la misma con token Sanctum.
- **La app no decide permisos.** El menú sale de `/me.modulos`; cada ficha trae
  `acciones` calculadas por el backend con alcance (sede/bloque). Ocultar un botón es
  solo UX: cada endpoint vuelve a autorizar.

| Servicio compartido | Lo usan |
|---|---|
| `Personas\PersonaService`, `FichaPersona`, `PersonaRequest` | Personas web + API |
| `Personas\ProfesorService` + `ProfesorPolicy` (nueva) | Profesores web + API |
| `Personas\AlumnoService` | Alumnos web + API |
| `Agenda\SedeService`, `EventoService`, `ShowService`, `BloqueService` | Sedes, eventos, shows, bloques |
| `Finanzas\CuotaService`, `RegistroPagoService`, `PagosDocenteService` | Cuotas, pagos, vista docente |
| `Finanzas\BecaService` + `BecaRequest`/`BecaUpdateRequest` | Becas |
| `Finanzas\GastoService`, `FacturacionService`, `ComprobanteService` | Gastos, facturación, comprobantes |
| `Operativo\CierreMesService`, `Reportes\ReportesService` | Cierre de mes, reportes y Excel |
| `Compras\CompraService`, `PlanComprasService` | Órdenes y plan de compras |
| `Acceso\UsuarioService` | Usuarios |
| `VillaGesell\VillaGesellAdmin` (+ `VillaGesellGiraService`) | Gira |
| `Biblioteca\BibliotecaService`, `Asistencias\SeguimientoService` | Biblioteca, bitácora pedagógica |

## Correcciones de seguridad y robustez en el backend (también aplican a la web)

| Qué | Antes | Ahora |
|---|---|---|
| Profesores ver/editar/eliminar | Solo permiso, sin alcance (IDOR entre sedes) | `ProfesorPolicy` por bloque/sede |
| Facturación cargar/editar | No verificaba la sede | `facturacion.manage` en la sede (o global) |
| Plan de compras | Mostraba todas las sedes | Respeta el alcance de `compras.view` |
| Registro de pagos | Sin transacción; duplicados posibles con envíos simultáneos | Transacción + bloqueo de cuotas; `client_uuid` en la API |
| Aprobar comprobante | Dos aprobaciones simultáneas podían generar dos pagos | Bloqueo de fila |
| Editar orden de compra (web) | Reemplazaba el creador | Se conserva el creador |
| Comprobante de pago reemplazado | Se borraba antes de guardar el cambio | Se borra después |

## Matriz de paridad

✅ disponible en la app · ◐ parcial · — no existe en la web (no se inventó)

| Módulo | Web | App | Notas |
|---|---|---|---|
| Personas | listar, buscar, filtrar, alta, edición, ficha (funciones, cuenta, cuotas, becas, asistencias, eventos, inventario, permisos), fusionar | ✅ todo | Ficha central con pestañas y acciones (inscribir, sumar al plantel, crear cuenta, registrar pago, otorgar beca, historial) |
| Profesores | listar, alta (desde persona), edición (bloques con rol, roles de sede, cuenta), baja | ✅ todo | |
| Alumnos | listar, alta, edición, baja, ficha, exportar Excel, importar Excel | ✅ todo | Importación por `POST /alumnos/importar` |
| Bloques | CRUD, horarios, alumnos | ✅ todo | |
| Sedes | CRUD, activar/desactivar | ✅ todo | Edición parcial no pisa la liquidación |
| Eventos | CRUD con ámbito | ✅ todo | |
| Shows | CRUD, convocatoria de bloques | ✅ todo | |
| Cuotas | CRUD, activar/desactivar, alumnos asignados, cobros, recordatorios | ✅ todo | |
| Pagos | registrar (liquidación docente), editar, anular, comprobante, filtros, vista docente | ✅ todo | Registro idempotente |
| Comprobantes | revisar, archivo, marcar visto, aprobar (genera pago), cargar; envío del alumno | ✅ todo | El alumno ya no usa el formulario web público |
| Becas | otorgar, cambiar estado/vigencia | ✅ todo | Listado global nuevo (solo lectura del alcance) |
| Gastos | CRUD, aprobar/rechazar, filtros | ✅ todo | — sin comprobante adjunto en la web |
| Facturación | carga mensual, corrección, listado, cierre de mes | ✅ todo | — no hay "generar/recalcular" en la web |
| Compras | órdenes (ítems, estados, aprobación), plan | ✅ todo | — el plan es un cálculo |
| Reportes | tablero del período, Excel, PDF, profesores | ✅ todo | PDF generado en el teléfono con el HTML imprimible del servidor |
| Usuarios y permisos | CRUD de cuentas, estado, contraseña, roles/permisos con alcance | ✅ todo | + consulta de sesiones de la app por dispositivo |
| Auditoría | listado filtrable, detalle | ✅ todo | + historial dentro de cada ficha |
| Villa Gesell | datos, inscriptos, calendario de tocadas, gastos, insumos, altas rápidas, plan | ✅ todo | |
| Diseño | diseños, páginas, plantillas, imágenes, kit, edición en lienzo | ✅ todo | Arrastre, giro, tipografía básica y miniatura generada al guardar. El editor de escritorio sigue existiendo para quien lo use en la web |
| Biblioteca | buscar, filtrar, ver, subir, ocultar/publicar, eliminar | ✅ todo | — sin carpetas ni renombrar en la web |
| Inventario | CRUD, movimientos, QR | ✅ todo | |
| Asistencia | planilla por día (offline), corrección | ✅ todo | Matriz mensual y borrado de una celda por la API |
| Seguimiento pedagógico | notas por alumno | ✅ todo | |
| Partituras | visor, PDF, partes, videos; administración, escritura y audio | ✅ todo | Grilla de semicorcheas (no el pentagrama VexFlow). Escucha por cuerda con los WAV de la API. Alta, baja, publicar y PDF |
| Archivo histórico | aportes (subida múltiple, seguimiento, corrección), moderación, fotos (edición, publicar/ocultar, eliminar), capítulos, acontecimientos, orden, lote | ✅ aportes, moderación, fotos | Capítulos, acontecimientos, lote y orden están en la API; en la app se gestionan desde la ficha de cada foto. La experiencia pública (línea de tiempo, Story Mode) es web y se abre desde la app |
| Operativo | Pendientes, resumen por WhatsApp/mail, chatbot | ✅ todo | Chat, WhatsApp y mail por `/recordatorios`, sin abrir el panel |

La app no abre páginas del panel. La partitura se lee con `lectura`, se escribe con el
`score` v4 (`PUT /partituras/{slug}/score`) y se escucha con los WAV de
`GET /partituras/muestras`. El PDF original se baja de `GET /partituras/{slug}/archivo`.
Un enlace de biblioteca o un video solo se abre afuera si el host no es el de la API.

## Pendientes

1. Pentagrama grabado tipo MuseScore (VexFlow). En la app la escritura es la grilla y el sonido es el sampler.
2. Matriz legacy de visibilidad de módulos por usuario (`/accesos`), reemplazada por
   roles y permisos con alcance.
3. Pruebas manuales en dispositivos contra producción.
