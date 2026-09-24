# Testing

## Correr

```bash
php artisan test                      # SQLite en memoria (phpunit.xml)
DB_CONNECTION=mysql DB_DATABASE=chilinga_test php artisan test   # contra MySQL
npm run test:partitura                # tests JS del modelo de partituras
vendor/bin/pint --test                # estilo
cd mobile && npm run typecheck && npm run lint
```

Si el PHP local no tiene `pdo_sqlite`: `sudo apt install php8.3-sqlite3`, o usar MySQL como
arriba, o `composer test` (usa Docker).

Todos los tests con base de datos usan **las migraciones reales** (`RefreshDatabase`): lo que
se prueba es el esquema de producción. (Antes existía un esquema armado a mano en
`tests/Support/BusinessSchema.php`; se eliminó.)

## Qué cubre

| Archivo | Cubre |
|---------|-------|
| `Feature/IdentidadMultirrolTest` | persona alumno + profesor con una cuenta; varias sedes y bloques; vínculo por DNI; profesor desde persona existente; propagación de datos; fusión; una cuenta por persona |
| `Feature/AutorizacionContextualTest` | **caso combinado obligatorio** (alumno Banfield, profesor Palomar, coordinador Quilmes, encargado Varela, contador global); profesor solo sus bloques; IDOR en sedes/bloques; contador por sede; encargado de inventario; administrador vs superadministrador; último superadmin; roles derivados; permisos sueltos; cuentas desactivadas; asignaciones vencidas; pagos ajenos |
| `Feature/AccesoParidadLegacyTest` | los usuarios existentes conservan lo que podían hacer |
| `Feature/MigracionDatosTest` | backfill sobre datos heredados (une por cuenta y DNI, no adivina homónimos, idempotente, simulación) y diagnóstico |
| `Feature/FinanzasIntegridadTest` | borrado protegido, anulación de pagos, estado de cuenta con becas y alcance de cuotas, becado derivado, gastos pendientes de aprobación |
| `Feature/Api/ApiV1Test` | login/refresh/logout, `/me`, `/inicio`, IDOR por ID, asistencia idempotente y offline, estado de cuenta, inventario por QR, finanzas, asignaciones |
| `Feature/NotificacionesTest` | aviso de pago sin duplicados, cuotas vencidas, eventos por sede, push (Expo simulado) |
| `Feature/SeguridadTest` | límite de intentos de login, cabeceras de seguridad, cierre de edición pública de partituras, login sin rol por defecto |
| `Feature/PantallasWebTest` | render de Personas, Usuarios y permisos, Auditoría, altas desde persona, menú multirrol |
| `Feature/RolesYNegocioTest`, `DisenoOwnershipTest`, `WhatsAppStatusTrackingTest`, `PagoCuotaTokenTest` | tests previos, adaptados al esquema real |

## Helpers

`Tests\Support\Escenarios`: `sede()`, `bloque()`, `persona()`, `usuario()`, `inscribirAlumno()`,
`asignarDocente()`, `rolDocenteEnSede()`, `asignarRol()`, `asignarPermiso()`, `admin()`.

## CI (GitHub Actions)

| Job | Pasos |
|-----|-------|
| Backend (SQLite) | `composer audit`, build Vite, tests JS, Pint, a11y, validación de rutas API, suite completa |
| MySQL | migra base vacía, revierte y re-aplica la migración de plataforma, `chilinga:diagnose`, suite completa sobre MySQL |
| App móvil | `npm ci`, typecheck, lint, bundle Android + iOS |
