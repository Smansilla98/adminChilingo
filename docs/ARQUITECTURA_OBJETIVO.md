# Arquitectura objetivo — plataforma multi-sede, multi-rol y multi-contexto

Este documento fija las decisiones técnicas. Cada decisión lista las alternativas evaluadas
y el motivo de la elección. El detalle operativo está en `MODELO_PERSONAS.md`,
`ROLES_Y_PERMISOS.md`, `API.md` y `APP_MOVIL.md`.

## 1. Vista general

```
                    ┌──────────────────────────────┐
  Panel web (Blade) │  Laravel 12 (fuente de verdad)│  App móvil (Expo / React Native)
  sesión + CSRF ───▶│                              │◀─── Bearer token (Sanctum)
                    │  Controllers web  API v1     │
                    │        │            │        │
                    │        ▼            ▼        │
                    │   Domain services / Actions  │  ← lógica compartida (una sola vez)
                    │   Acceso (permisos+alcance)  │
                    │   Policies                   │
                    │        │                     │
                    │     Eloquent / MySQL         │
                    └──────────────────────────────┘
```

- **Un solo backend**. Web y API llaman a los mismos servicios (`app/Domain/...`, `app/Services/...`).
- **Autorización centralizada** en `App\Domain\Acceso` (permisos efectivos con alcance) +
  Policies de Laravel. Los controladores no preguntan por roles.
- La app móvil es un cliente nativo que consume `/api/v1`, no un WebView.

## 2. Identidad: Persona ≠ Usuario ≠ Rol

```
Persona (identidad única, datos personales)
 ├── 0..1 User            (cuenta de acceso: username, email, password, activo)
 ├── 0..n Alumno          (perfil alumno: instrumento, sede principal, bloques)
 ├── 0..1 Profesor        (perfil docente: bloques con rol, sedes con rol)
 ├── 0..n Beca            (a través de su perfil alumno)
 └── 0..n Asignación      (rol o permiso explícito + alcance: global / sede / bloque)
```

**Decisión: conservar `alumnos` y `profesores` como perfiles de la persona** (no fusionarlos
en una sola tabla).

| Alternativa | Por qué no |
|-------------|------------|
| Reemplazar `alumnos` y `profesores` por `personas` + `inscripciones` | Obliga a reescribir asistencias, pagos, cuotas, comprobantes, Villa Gesell, inventario y 160 vistas que usan `alumno_id`; alto riesgo sobre datos financieros |
| Mantener todo como está y unir por `user_id` | No resuelve personas sin cuenta ni duplicados |
| **Persona + perfiles vinculados por `persona_id`** ✅ | Identidad única, cero cambios en FKs financieras, migración aditiva |

`alumnos.persona_id`, `profesores.persona_id` y `users.persona_id` apuntan a `personas`.
Los datos personales viven en `personas`; las columnas equivalentes en `alumnos`/`profesores`
quedan sincronizadas automáticamente (observers) mientras existan vistas legacy que las lean.

## 3. Roles y permisos con alcance

**Decisión: Spatie Permission como catálogo + tabla propia `asignaciones` para el alcance.**

| Alternativa | Evaluación |
|-------------|------------|
| Spatie "teams" (`team_id`) | Un único eje de alcance, se activa globalmente, no distingue sede de bloque ni permite "global" y "sede" a la vez para el mismo rol |
| Paquete nuevo (Bouncer, etc.) | Cambia tecnología sin necesidad; Spatie ya está instalado |
| **Spatie para `roles`, `permissions`, `role_has_permissions` + `asignaciones(persona, rol\|permiso, ámbito)`** ✅ | Reutiliza tablas existentes; el alcance se modela explícito |

### Fuentes de roles de una persona

1. **Explícitas** — tabla `asignaciones`: rol o permiso suelto, con ámbito `global`, `sede` o `bloque`
   y vigencia opcional (`desde`/`hasta`). Ej.: *Contador — Global*, *Responsable de inventario — Varela*.
2. **Derivadas de datos académicos** (no se duplican en `asignaciones`):
   - Perfil alumno en un bloque → rol `alumno` en ese bloque.
   - Profesor en `bloque_profesor` / titular → rol `profesor` en ese bloque.
   - `profesor_sede.rol = coordinador` o `sedes.coordinador_id` → `coordinador` en la sede.
   - `profesor_sede.rol = encargado` → `encargado` en la sede.
   - `coordinador_area` → `coordinador_area` en las sedes donde enseña.
   - Beca activa → rol `becado`.
3. **Legacy** — `users.role` y roles Spatie globales `admin`/`direccion` → `administrador` global
   (hasta que la migración los convierte en asignaciones explícitas).

Así no hay dos fuentes de verdad: si un profesor deja un bloque, pierde el permiso en ese bloque
sin que nadie tenga que acordarse de quitar una asignación.

### Evaluación de permisos

`Acceso::para($user)` calcula, una vez por request, un mapa:

```
permiso → Alcance { global: bool, sedes: [ids], bloques: [ids] }
```

- `puede('pagos.create')` → tiene el permiso en algún ámbito (para mostrar menú / rutas).
- `puedeEnSede('alumnos.update', 3)`, `puedeEnBloque('asistencias.create', 12)`.
- `alcance('alumnos.view')->aplicarAlumnos($query)` → filtra listados por sede o bloque.
- Las Policies usan esos métodos para cada registro: **cambiar el ID en la URL no da acceso**.
- `superadministrador` pasa todas las verificaciones.

### Selector de contexto ("Estoy trabajando como")

El contexto **no cambia la identidad ni amplía permisos**: es un filtro de interfaz.
Web: se guarda en sesión. API: header `X-Contexto` (opcional). La autorización siempre
usa la unión de permisos; el contexto solo decide qué menú y qué datos por defecto se muestran.

## 4. API

- Prefijo `/api/v1`, JSON, Sanctum con **tokens personales con vencimiento** (30 días por
  defecto, configurable) y endpoint de rotación (`/auth/refresh`). No se usan cookies en la API.
- Rate limiting: login 5/min por usuario+IP; API general 120/min por usuario.
- Recursos (`JsonResource`) con payloads acotados, paginación por cursor/página.
- `GET /me` devuelve persona, contextos y **capacidades** (lista de permisos + módulos de menú).
  La app construye la navegación solo con eso.
- Idempotencia de escrituras offline: `client_uuid` en asistencias (tabla `sync_operaciones`).

## 5. App móvil

**Decisión: React Native + Expo (TypeScript, Expo Router).**

| Alternativa | Evaluación |
|-------------|------------|
| WebView / PWA | Explícitamente descartado; sin cámara nativa fiable ni offline robusto |
| Flutter | Stack nuevo para el equipo (el repo es PHP + JS) |
| **Expo** ✅ | JS/TS como el frontend actual, builds Android/iOS con EAS, cámara (QR), SecureStore, push |

Principios: home por capacidades, pocas pantallas, botones grandes, React Query con caché
persistente, cola offline solo para asistencia.

## 6. Notificaciones

`NotificacionService` único con canales: **interna** (tabla `notifications` de Laravel),
**push** (Expo Push, desactivado por defecto), **email** (Resend existente), **WhatsApp**
(Twilio existente). Deduplicación por clave de negocio en `notificacion_envios`
(ej. `cuota_vencida:{alumno}:{cuota}:whatsapp`).

## 7. Auditoría

Tabla `auditoria` + trait `Auditable` en modelos sensibles (pagos, detalle de pagos, cuotas,
becas, gastos, inventario, usuarios, asignaciones, personas, alumnos, profesores). Guarda
usuario, acción, entidad, id, IP, antes/después (sin contraseñas ni tokens).

## 8. Integridad de datos

- Borrado protegido: sedes, bloques, alumnos, cuotas y personas con historial (pagos,
  asistencias) **no se eliminan**; se ofrece desactivar. Implementado en `EliminacionSegura`.
- Pagos no se borran: se **anulan** (`anulado_at`, `motivo_anulacion`).
- Las FKs `CASCADE` existentes no se modifican en esta etapa (cambiarlas en caliente en
  producción es riesgoso); la protección es a nivel aplicación y está cubierta por tests.

## 9. Calidad

- Migraciones portables (MySQL y SQLite) → los tests nuevos corren sobre el esquema real
  (`RefreshDatabase`).
- CI: Pint, tests PHP (SQLite), migraciones contra MySQL, build Vite, tests JS,
  typecheck de la app móvil.
