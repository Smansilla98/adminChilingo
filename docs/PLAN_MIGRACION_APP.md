# Plan de migración a la plataforma Web + API + App

> **Estado (2026-09-23):** fases 0 a 11 implementadas en la rama `feature/plataforma-multirrol`,
> con 119 tests en verde sobre SQLite y MySQL. Pendientes externos: builds firmados de la app
> (requieren cuenta de Expo/EAS y de las tiendas) y la puesta en producción (requiere backup
> previo). Detalle de lo que queda en la sección *Pendientes* al final.

Cada fase debe cerrar con tests en verde y sin pérdida de datos. Las fases se implementan
en la rama `feature/plataforma-multirrol`.

## Fase 0 — Auditoría ✅
`AUDITORIA_ACTUAL.md`, `ARQUITECTURA_OBJETIVO.md`, este plan. Corrección previa necesaria:
migraciones portables (fallaban en base vacía) para poder testear sobre el esquema real.

## Fase 1 — Personas, usuarios, roles y permisos
- Migraciones aditivas: `personas`, `persona_id` en `alumnos`/`profesores`/`users`,
  `users.activo`, `users.ultimo_acceso_at`, `asignaciones`, `auditoria`, `becas`.
- Migración de datos (idempotente): crea una persona por cada perfil; une alumno + profesor +
  usuario cuando ya compartían `user_id`. **No fusiona por nombre o DNI automáticamente**:
  eso lo reporta `chilinga:diagnose` y se resuelve con `chilinga:personas:fusionar`.
- Catálogo de permisos y roles (`config/permisos.php`) sincronizado a las tablas Spatie.
- Roles legacy `admin`/`direccion` → asignación explícita `administrador` global.
- `App\Domain\Acceso` (permisos efectivos con alcance), `Gate::before`, middleware `permiso`.
- Comando `chilinga:diagnose`.
- Tests: identidad, autorización, caso combinado obligatorio.

## Fase 2 — Adaptar módulos existentes
- Rutas: `role:*` → `permiso:*` por acción.
- Policies con verificación de alcance para alumnos, bloques, sedes, eventos, shows,
  asistencias, cuotas, pagos, gastos, inventario, personas, usuarios.
- Corrección del IDOR de bloques/sedes/eventos.
- Borrado seguro (sin destruir historial financiero).
- Alta de profesor y alumno a partir de una Persona (existente o nueva).
- Pantallas nuevas: **Personas** (ficha central) y **Administración › Usuarios y permisos**.
- Helpers `User::isAdmin()` etc. reimplementados sobre `Acceso` (compatibilidad con vistas).

## Fase 3 — API v1
Sanctum, `/api/v1`, recursos, tests de API (incluye IDOR por ID).

## Fase 4 — App móvil
`mobile/` con Expo + TypeScript: login, home por capacidades, selector de contexto,
asistencia, alumnos, cuotas/pagos, calendario, eventos, inventario con QR, partituras,
notificaciones.

## Fase 5 — Notificaciones
Notificaciones internas + dispositivos push + deduplicación; disparadores: pago registrado,
cuota vencida, evento próximo.

## Fase 6 — Offline
App: caché persistente de consultas + cola de asistencia con `client_uuid`.
API: idempotencia (`sync_operaciones`).

## Fase 7 — Seguridad
Throttle de login, usuarios desactivados, fin del rol por defecto en login, tokens con
vencimiento, auditoría, ocultamiento de datos sensibles en recursos.

## Fase 8 — Tests
Cobertura de identidad, autorización, API, migraciones (MySQL en CI).

## Fase 9 — UX/UI
Menú por permisos, ficha de persona, pantalla "¿qué puede hacer esta persona?", home móvil.

## Fase 10 — Deploy
Railway sin cambios de infraestructura; `start.sh` sincroniza permisos. Documentado en `DEPLOY.md`.

## Fase 11 — Build Android / iOS
`app.config.ts` + `eas.json` (development / staging / production). La compilación en la nube
(EAS) y la publicación en stores **requieren credenciales y cuentas pagas**: quedan preparadas
pero no se ejecutan sin autorización explícita.

## Riesgos y mitigaciones

| Riesgo | Mitigación |
|--------|-----------|
| Pérdida de datos al migrar | Solo migraciones aditivas; backfill idempotente; backup previo documentado |
| Usuarios pierden acceso tras el cambio | Mapeo legacy → roles nuevos con paridad de permisos; tests por rol |
| Fusiones erróneas de personas | Fusión solo manual y auditada |
| Divergencia web/API | Servicios compartidos + mismas Policies |

## Pendientes (fuera del alcance automático)

| Pendiente | Por qué no se hizo | Cómo |
|-----------|--------------------|------|
| Build firmado Android/iOS y publicación | Requiere cuenta de Expo, Apple Developer y Google Play (costo y credenciales) | `npx eas-cli init` + `npm run build:prod` en `mobile/` |
| Deploy a producción | Requiere backup previo de la base real | Checklist en `DEPLOY.md` |
| Cambiar FKs `CASCADE` → `RESTRICT` | Cambio en caliente sobre datos reales | Protección ya activa en la app; ver `MIGRACIONES.md` |
| fabric 7 / jsPDF 4 (avisos de seguridad) | Cambio de versión mayor en Diseño y PDFs; requiere prueba manual | Actualizar y probar el editor de Diseño y los PDFs |
| Registro de pagos desde la app | La liquidación docente está en el controlador web; extraerla requiere validar con tesorería | Mover `PagoController::validarFormularioPago/sincronizarDetallesPago` a un servicio y exponer `POST /pagos` |
