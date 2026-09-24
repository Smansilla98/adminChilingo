# Auditoría técnica y funcional — Admin Chilingo (estado previo a la plataforma multirrol)

Fecha del relevamiento: 2026-09-23 · Rama base: `main` (`707a6b9`)

Este documento describe el sistema **tal como estaba** antes de la refactorización
Persona / Roles / Permisos. Sirve como línea de base para medir cada cambio posterior.

---

## 1. Arquitectura y stack

| Capa | Tecnología |
|------|------------|
| Backend | Laravel 12 (PHP ≥ 8.2, CI con 8.3), monolito con vistas Blade |
| Base de datos | MySQL en producción (Railway); SQLite en memoria para tests |
| Autorización | `spatie/laravel-permission` v7 (solo roles, **0 permisos definidos**) + columna `users.role` + matriz `users.modulos_access` |
| Frontend | Blade + Bootstrap 5 + Tailwind 4 (Vite), FullCalendar, Chart.js, VexFlow (partituras), Fabric.js (Diseño) |
| Exportaciones | `maatwebsite/excel` (alumnos, reportes); PDF de reportes/comprobantes con vistas imprimibles y jsPDF |
| Mensajería | Twilio (WhatsApp, con webhook de estado firmado), Resend (mail) |
| Deploy | Railway con `Dockerfile` + `start.sh` (espera DB, migra, bootstrap de partituras, `artisan serve`) |
| CI | GitHub Actions: composer, build Vite, tests JS de partituras, Pint, chequeo a11y por grep, `php artisan test` |

Tamaño: 44 controladores, 35 modelos, 17 servicios, 6 comandos Artisan, 160 vistas Blade,
63 migraciones, 61 tests PHP + 3 suites JS. **No existe API REST** (solo endpoints JSON
internos de las vistas) ni app móvil.

### Estructura de módulos

- **Académico**: Alumnos, Profesores, Bloques (+ horarios), Asistencias (matriz tipo Excel),
  Seguimiento pedagógico (cuaderno), Programa / Partituras (editor VexFlow, audio), Biblioteca.
- **Institucional**: Sedes, Eventos, Shows, Calendario, Agenda pública, Villa Gesell (gira).
- **Financiero**: Cuotas (alcance bloque/sede/general), Pagos (+ detalle por alumno/cuota,
  liquidación docente), Comprobantes enviados por alumnos (público con token), Facturación
  mensual, Gastos, Cierre de mes, Reportes.
- **Operativo**: Inventario (código `CHL-0001`, QR público `/tambor/{codigo}`, movimientos),
  Plan de compras, Órdenes de compra.
- **Comunicación**: recordatorios WhatsApp/mail, resumen semanal (scheduler), chatbot de recordatorios.
- **Administración**: Accesos (matriz de módulos por usuario, alta de usuarios), Apariencia, Diseño (canvas).

---

## 2. Modelo de datos actual

### Entidades de identidad (el núcleo del problema)

```
users ──1:1── profesores (profesores.user_id, nullable)
  │
  └──1:1── alumnos (alumnos.user_id, nullable)
```

- **No existe una entidad Persona.** Los datos personales están repartidos:
  - `alumnos`: `nombre_apellido`, `dni`, `fecha_nacimiento`, `telefono`.
  - `profesores`: `nombre`, `telefono`, `email`.
  - `users`: `name`, `email`, `telefono`.
- Una misma persona que es alumna y profesora existe **dos o tres veces** (fila en `alumnos`,
  fila en `profesores`, fila en `users`). Solo quedan unidas si ambas fichas apuntan al mismo
  `user_id`; si no tiene cuenta, no hay forma de saber que son la misma persona.
- `users.role` es un string único (`admin`, `direccion`, `profesor`, `alumno`) que convive
  con los roles Spatie en `model_has_roles`.

### Relaciones académicas

| Relación | Tabla | Observaciones |
|----------|-------|---------------|
| Alumno ↔ Bloque (N:N) | `alumno_bloque` (`es_principal`) | además `alumnos.bloque_id` legacy |
| Alumno → Sede | `alumnos.sede_id` | sede "principal"; **FK CASCADE** |
| Profesor ↔ Bloque (N:N) | `bloque_profesor` (`rol`: titular/ayudante/suplente/coordinador_clase) | además `bloques.profesor_id` legacy (titular) |
| Profesor ↔ Sede con rol | `profesor_sede` (`rol`: profesor/encargado/coordinador) | además `sedes.coordinador_id` legacy |
| Coordinador de área | `coordinador_area` (`profesor_id`, `area`) | género / costa / tambores |
| Bloque → Sede | `bloques.sede_id` | **FK CASCADE** |

### Financiero

| Tabla | Descripción |
|-------|-------------|
| `cuotas` | nombre, año, mes, vencimiento, monto, `alcance` (bloque/sede/general); `cuota_alumno` restringe a alumnos puntuales |
| `pagos` | fecha, monto_total, comprobante, `registrado_por` (user) |
| `pago_detalles` | pago × alumno × cuota, monto, abono al profesor (liquidación) |
| `comprobantes_cuota_alumnos` (+ items) | comprobante subido por el alumno sin login; se aprueba → crea pago |
| `facturacion_mensual` | cierre manual por sede/mes |
| `gastos` | por sede/bloque, tipo/subtipo |

No hay modelo de **becas**, descuentos ni saldo calculado; no hay anulación de pagos.

### Operativo

`inventario_items` (sede, tipo, código, propietario escuela/alumno, estado, atributos del
tambor), `inventario_movimientos`, `ordenes_compra` + items.

### Diagrama de FKs peligrosas (ON DELETE CASCADE)

```
sedes ──CASCADE──> bloques ──CASCADE──> asistencias, alumno_bloque, bloque_profesor, comprobante items
  │
  └──CASCADE──> alumnos ──CASCADE──> pago_detalles, asistencias, comprobantes, observaciones, villa_gesell_inscriptos
                                    
cuotas ──CASCADE──> pago_detalles
sedes ──CASCADE──> inventario_items, facturacion_mensual, ordenes_compra
```

**Borrar una sede desde el CRUD elimina sus bloques, sus alumnos y el detalle de todos los
pagos de esos alumnos.** Borrar una cuota elimina el detalle de los pagos asociados. No hay
confirmación de dependencias ni soft delete.

---

## 3. Autenticación

- Sesión web (`auth`), login por `username` (o email si la columna no existe).
- **Sin rate limiting en `/login`** (fuerza bruta posible).
- Registro público deshabilitado por defecto (`ALLOW_PUBLIC_REGISTER`).
- En cada login, `asegurarRolSpatie()` asigna el rol `profesor` a cualquier usuario sin rol
  Spatie cuyo `users.role` no sea reconocido (**escalada implícita**).
- No hay tokens de API, no hay control de usuarios desactivados, no se registra último acceso.

## 4. Autorización

### Roles actuales

| Rol Spatie | Significado | Cómo se asigna |
|------------|-------------|----------------|
| `admin`, `direccion` | acceso total | manual (Accesos) |
| `coordinador_sede` | gestión acotada a sedes coordinadas | derivado de `profesor_sede.rol = coordinador` o `sedes.coordinador_id` |
| `coordinador_area` | alumnos y asistencias de sus sedes | derivado de `coordinador_area` |
| `profesor` | "Mi espacio" docente | alta de profesor |
| `alumno` | espacio del alumno | manual |

### Permisos actuales

- **La tabla `permissions` está vacía.** Todo el control es por rol, con `if` en código:
  140 llamadas a `isAdmin()`, `isProfesor()`, `hasRole()`, `->role ===` en controladores,
  servicios y vistas.
- Middleware `role:admin,coordinador_sede,...` por grupo de rutas.
- Matriz `users.modulos_access` (JSON) que **solo puede quitar** acceso a módulos del menú.
- Alcance de datos por sede resuelto en `User::sedeIdsOperativas()` + `AmbitoSedeService`.

### Problemas de autorización detectados

1. **IDOR en bloques, sedes, eventos y shows**: el grupo de rutas `role:admin,coordinador_sede,coordinador_area`
   habilita `show/edit/update/destroy` sin verificar que el registro pertenezca a las sedes del
   coordinador. Un coordinador de área de Quilmes puede editar o **borrar** la sede Palomar
   modificando el ID en la URL (y por la cascada, sus alumnos y pagos).
2. Alumnos sí validan alcance (`autorizarFichaAlumno`, `asegurarSedeYBloquesPermitidos`).
3. Asistencias validan bloque (`puedeAccederBloque`) — correcto.
4. Imposible dar "solo finanzas" a alguien: cuotas, pagos, gastos, facturación e inventario
   están en el grupo `role:admin` → para que un profesor sea contador hay que hacerlo admin total.
5. El alcance mezcla funciones: un profesor que es coordinador en Quilmes ve como coordinador
   **todas las sedes donde da clase** (`sedeIdsOperativas` une sedes coordinadas + sedes de sus bloques).
6. `Programa` / editor de partituras: edición y subida de archivos (hasta 100 MB) **sin login**,
   solo con un nombre declarado y throttle. Es una decisión funcional (edición colaborativa
   abierta), pero es superficie de abuso.

---

## 5. Flujos actuales

### Alumnos
Alta desde `/alumnos` (admin/coordinador) con sede principal + bloques (pivot) o importación
Excel (`chilinga:import-excel`, `/alumnos/import`). Ficha con asistencias, pagos, observaciones.
DNI opcional y único → alta rápida sin DNI genera homónimos sin control.

### Profesores
`/profesores` (solo admin). Crea ficha `profesores` con nombre/teléfono/email **independiente**
del alumno; opcionalmente crea un `User` nuevo (`cuenta_modo=nueva`) o vincula uno existente.
Si el profesor ya era alumno sin cuenta, queda duplicado. `sincronizarRolesUsuario()` además
fuerza `users.role = 'profesor'`.

### Sedes / Bloques
CRUD simple. Bloque tiene año (1–6), titular (`profesor_id`) + equipo (`bloque_profesor`),
horarios semanales, tambores.

### Cuotas y pagos
Cuota se resuelve por período: bloque → sede → general (`Cuota::resolveForBloque`). Pago
manual (admin) con N detalles alumno×cuota; o comprobante subido por el alumno (link público
con token cifrado de 20 min) que un profesor/admin aprueba. Liquidación docente por sede
(retención escuela + % docente). No existe estado de cuenta ni saldo del alumno.

### Asistencias
Matriz por bloque y mes (P/T/J/I/F/S). Único `(alumno, bloque, fecha)` → idempotente.
No registra quién tomó la asistencia.

### Eventos / calendario
Eventos (show, taller, muestra, gira, reunión…), Shows con bloques convocados, calendario
FullCalendar que une eventos, shows y horarios de bloques.

### Inventario
Ítems por sede con código y QR público (ficha sin datos personales), movimientos
(ingreso, cambio de sede, reparación, evento, retorno). Solo admin.

### Flujo financiero
Pagos → facturación mensual (carga manual) → gastos → cierre de mes / reportes.
Todo restringido a `admin`.

### Notificaciones
WhatsApp vía Twilio (recordatorio de cuota, resumen semanal a admins, estado de entrega
con webhook firmado y deduplicación por SID). Mail de resumen diario. No hay notificaciones
internas ni push. No hay deduplicación por "evento de negocio" (se puede mandar dos veces
el mismo recordatorio).

---

## 6. Deuda técnica

| # | Problema | Impacto |
|---|----------|---------|
| 1 | **Migraciones no corren en una base vacía** (FK con nombre > 64 caracteres en MySQL; `ALTER ... MODIFY` y `enum` en SQLite) | Instalaciones nuevas quedan con tablas sin FK; los tests no pueden usar las migraciones reales (usan un esquema a mano, `tests/Support/BusinessSchema.php`) |
| 2 | 181 llamadas a `Schema::hasTable/hasColumn` en runtime | Consultas a `information_schema` por request; código defensivo que oculta migraciones pendientes |
| 3 | Columnas legacy duplicadas (`alumnos.bloque_id` vs pivot, `bloques.profesor_id` vs pivot, `sedes.coordinador_id` vs `profesor_sede`) | Dos fuentes de verdad, sincronización manual |
| 4 | `User` define `$casts` y `casts()` a la vez | Confuso, uno pisa al otro |
| 5 | `users.role` + Spatie + `modulos_access` | Tres sistemas de acceso superpuestos |
| 6 | Tests de negocio se saltean si falta `pdo_sqlite` (localmente se saltaban 26 de 61) | Falsa sensación de cobertura |
| 7 | Sin auditoría de cambios sensibles (pagos, gastos, usuarios) | No hay trazabilidad |
| 8 | `start.sh` continúa aunque fallen las migraciones | Deploy "verde" con esquema roto |
| 9 | Lógica de negocio en controladores grandes (`PagoController` 647 líneas, `AlumnoController` 746, `DashboardController` 707) | No reutilizable desde una API |

## 7. Seguridad (resumen)

| Severidad | Hallazgo |
|-----------|----------|
| Crítica | IDOR + borrado en cascada de sedes/bloques por coordinadores |
| Alta | Borrado de sede/cuota/alumno destruye historial financiero |
| Alta | Login sin throttle |
| Media | Login asigna rol `profesor` por defecto a usuarios sin rol |
| Media | Edición pública de partituras con subida de archivos |
| Baja | Sin registro de IP/usuario en operaciones financieras |

Correctos: CSRF activo (excepto webhook Twilio con firma validada), token cifrado con
vencimiento para comprobantes públicos, throttle en endpoints públicos, ficha QR pública
sin datos personales, mass assignment acotado con `$fillable`.

## 8. UX

- El menú depende del rol, no de lo que la persona realmente puede hacer.
- Una persona con varias funciones ve un dashboard elegido por prioridad fija
  (alumno < profesor < gestión) y pierde acceso visual a sus otras funciones.
- No hay ficha única de persona: los datos de María alumna y María profesora están en pantallas distintas.
- "Accesos" muestra una matriz de módulos on/off difícil de interpretar y no explica
  qué puede hacer cada usuario.

## 9. Qué se conserva, modifica o elimina

### Conservar (correcto y reutilizable)
- Todos los módulos funcionales y sus vistas.
- `Cuota::resolveForBloque`, `aplicaAAlumno`, liquidación docente (`Sede`, `LiquidacionDocente`).
- `PagoDesdeComprobanteService`, `ComprobanteCuotaRegistroService`, `PagoCuotaToken`.
- `WhatsAppService` + tracking de estados, `MailResumenAdminService`.
- Inventario con código/QR y ficha pública; movimientos.
- Partituras (modelo v4, editor, audio), Programa, Biblioteca, Diseño, Villa Gesell.
- Pivots `alumno_bloque`, `bloque_profesor`, `profesor_sede` (son las relaciones correctas).
- Spatie Permission como catálogo de roles y permisos.

### Modificar
- Identidad: introducir **Persona** y vincular alumnos, profesores y usuarios.
- Autorización: permisos granulares con alcance (global / sede / bloque) en lugar de `if` por rol.
- Rutas: middleware por permiso; policies con verificación de alcance por registro.
- Alta de profesores y usuarios: partir de una persona existente o nueva.
- Borrados: bloquear cuando hay historial; preferir desactivar.
- Dashboard y menú: construidos a partir de permisos efectivos.
- Migraciones: portables (MySQL/SQLite) para que los tests usen el esquema real.

### Eliminar (solo lo duplicado o incompatible)
- Nada se elimina en esta etapa. Las columnas legacy (`users.role`, `alumnos.bloque_id`,
  `bloques.profesor_id`, `sedes.coordinador_id`, `users.modulos_access`) se mantienen y se
  marcan como deprecadas hasta confirmar en producción que no se usan (ver `docs/MIGRACIONES.md`).

## 10. Funcionalidades faltantes

Persona única · roles múltiples con contexto · permisos granulares · becas · estado de cuenta
y saldo · anulación de pagos · auditoría · API REST · app móvil · notificaciones internas/push ·
toma de asistencia offline · selector de contexto · diagnóstico de datos · registro de
quién tomó asistencia · último acceso y desactivación de usuarios.

## 11. Reutilizable para la API

| Pieza | Reutilización |
|-------|---------------|
| `Cuota::resolveForBloque`, `aplicaAAlumno` | estado de cuenta |
| `AmbitoSedeService` (filtros por sede) | base del filtro por alcance |
| `EspacioAlumnoService` (armado del espacio del alumno) | home del alumno |
| `PagoDesdeComprobanteService` | registrar pago desde comprobante |
| `Asistencia` (tipos, letra, `esPresente`) | toma de asistencia |
| `InventarioItem::fichaPublica`, `asegurarCodigo` | inventario por QR |
| `PartituraScore` + `ProgramaRitmo::mediosNormalizados` | visor de partituras móvil |
| `WhatsAppService` | canal WhatsApp de notificaciones |

## 12. A rediseñar para móvil

Toma de asistencia (hoy es una matriz mensual pensada para desktop) · home por capacidades ·
consulta de cuotas/pagos del alumno · inventario por escaneo QR · calendario en lista ·
visor de partituras a pantalla completa y horizontal.
