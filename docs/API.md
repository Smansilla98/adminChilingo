# API v1

Base: `https://<dominio>/api/v1` · JSON · errores en español.

## Autenticación

Tokens personales de Laravel Sanctum, **uno por dispositivo**, con vencimiento
(`SANCTUM_TOKEN_MINUTOS`, 30 días por defecto).

```http
POST /auth/login
{ "username": "maria", "password": "…", "dispositivo": "android app" }

200 { "token": "12|abc…", "tipo": "Bearer", "expira": "2026-10-23T12:00:00-03:00" }
422 { "message": "Usuario o contraseña incorrectos.", "errors": { "username": [...] } }
429 demasiados intentos (5 por minuto por usuario+IP, 20 por IP)
```

Luego: `Authorization: Bearer <token>`.

| Endpoint | |
|----------|-|
| `POST /auth/refresh` | Emite un token nuevo y revoca el usado. La app lo llama cuando faltan < 7 días |
| `POST /auth/logout` | Revoca el token (y opcionalmente `push_token` del dispositivo) |

- Cuenta desactivada → login 422 y cualquier token existente 403 (y se revoca).
- Token vencido o revocado → 401 (la app vuelve al login).
- Límite general: 120 requests/min por usuario.

## Contexto de trabajo

Header opcional `X-Contexto: profesor:3` (valor = `contextos[].clave` de `/me`). Solo ordena
el contenido de `/inicio`; **nunca** cambia permisos.

## Autorización

Cada endpoint verifica el permiso y el **alcance del registro**: pedir `/alumnos/123` de otra
sede devuelve 403 aunque el ID exista. Los listados vienen filtrados por alcance.

## Endpoints

### Identidad

| Método | Ruta | Descripción |
|--------|------|-------------|
| GET | `/me` | Usuario, persona, `funciones` (rol + dónde + origen), `contextos`, `permisos`, `alcances`, `modulos` (para armar el menú), `superadmin` |
| GET | `/inicio` | Home: `tarjetas` según funciones (`mi_espacio`, `clases_hoy`, `finanzas`, `inventario`, `eventos`), `avisos_no_leidos`, `modulos` |
| GET | `/sedes` | Sedes donde la persona tiene alguna función (todas con alcance global) |
| GET | `/personas?q=` · `/personas/{id}` | `personas.view` (con alcance) o uno mismo |

### Académico

| Método | Ruta | Descripción |
|--------|------|-------------|
| GET | `/bloques?para=asistencia&sede_id=` | Bloques visibles (o donde puede tomar asistencia) con horarios |
| GET | `/bloques/{id}` · `/bloques/{id}/alumnos` | |
| GET | `/bloques/{id}/asistencia?fecha=YYYY-MM-DD` | Planilla: alumnos del bloque con su estado, `tomada`, `puede_editar`, `tipos` |
| POST | `/bloques/{id}/asistencia` | Guarda la planilla (total o parcial) — ver abajo |
| GET | `/alumnos?q=&bloque_id=&sede_id=&activo=1` · `/alumnos/{id}` | DNI y nacimiento solo si puede editar al alumno |
| GET | `/calendario?desde=&hasta=&sede_id=&bloque_id=` | Clases (horarios), eventos y shows del alcance. Máx. 62 días |
| GET | `/eventos?desde=` | Próximos eventos del alcance |
| GET | `/partituras` · `/partituras/{slug}?con_score=1` | Toques publicados; `visor_url`, `pdf_url`, `partes`, `videos` |

#### Asistencia (idempotente y apta para offline)

```http
POST /bloques/12/asistencia
{
  "fecha": "2026-09-23",
  "client_uuid": "0b6c…",               // opcional: reintentos con el mismo UUID no duplican
  "capturado_en": "2026-09-23T18:05:00Z", // opcional: cuándo se tomó en el teléfono
  "registros": [
    { "alumno_id": 51, "tipo": "presente" },
    { "alumno_id": 52, "tipo": "ausencia_injustificada" }
  ]
}

200 { "guardadas": 2, "fecha": "2026-09-23", "bloque_id": 12, "conflictos": [], "duplicado": false }
```

- `tipo`: `presente`, `tarde`, `ausencia_justificada`, `ausencia_injustificada`, `feriado`, `sin_clases`.
- Un alumno que no pertenece al bloque → 422.
- Con `capturado_en`: si otra persona corrigió ese registro después, se conserva el dato del
  servidor y el alumno se informa en `conflictos`.
- Queda registrado quién la tomó (`registrado_por`).

### Finanzas

| Método | Ruta | Descripción |
|--------|------|-------------|
| GET | `/mi/estado-cuenta?anio=` | Estado de cuenta de la persona logueada (todas sus fichas de alumno) |
| GET | `/alumnos/{id}/estado-cuenta?anio=` | Requiere `cuotas.view` o `pagos.view` sobre el alumno (o ser uno mismo) |
| GET | `/cuotas?anio=` | Cuotas del alcance con cantidad de pagos |
| GET | `/pagos?desde=&hasta=` · `/pagos/{id}` | Pagos del alcance (incluye anulados, marcados) |
| POST | `/pagos/{id}/anular` | `{ "motivo": "…" }` — `pagos.reverse` sobre todos los alumnos del pago |

Estado de cuenta:

```json
{
  "alumno_id": 51, "anio": 2026,
  "items": [{ "cuota_id": 3, "nombre": "Marzo", "periodo": "03/2026", "vencimiento": "2026-03-10",
              "bruto": 24000, "beca": { "id": 1, "etiqueta": "Beca 50%" }, "descuento": 12000,
              "neto": 12000, "pagado": 12000, "saldo": 0, "estado": "pagada" }],
  "totales": { "bruto": 0, "descuento": 0, "neto": 0, "pagado": 0, "saldo": 0, "vencido": 0 },
  "becas": [ … ]
}
```

El **registro** de pagos (con liquidación docente) sigue en el panel web.

### Inventario

| Método | Ruta | Descripción |
|--------|------|-------------|
| GET | `/inventario?q=&sede_id=&tipo=&estado=` | Ítems del alcance |
| GET | `/inventario/catalogos` | Tipos, estados, propietarios, tipos de movimiento |
| GET | `/inventario/codigo/{codigo}` | Buscar por código o por la URL del QR (`…/tambor/CHL-0042`). 404 si no existe **o** está fuera del alcance |
| GET | `/inventario/{id}` | Ficha + últimos 20 movimientos + `puede_editar` |
| POST | `/inventario` | Alta (201). La sede debe estar en el alcance de `inventario.create`. Genera código si falta |
| PUT | `/inventario/{id}` | Edición |
| POST | `/inventario/{id}/movimientos` | `{ tipo, estado?, sede_id?, nota? }` — cambia estado/sede y deja historial |

### Avisos y dispositivos

| Método | Ruta | |
|--------|------|-|
| GET | `/notificaciones` | `no_leidas` + últimos 50 |
| POST | `/notificaciones/{id}/leer` · `/notificaciones/leer-todas` | |
| POST | `/dispositivos` | `{ token: "ExponentPushToken[…]", plataforma, nombre }` |
| DELETE | `/dispositivos` | `{ token }` |

### Administración de accesos

| Método | Ruta | Permiso |
|--------|------|---------|
| GET | `/accesos/catalogo` | `usuarios.view` — roles (con permisos expandidos y ámbitos) y permisos |
| GET | `/usuarios?q=` · `/usuarios/{id}` | `usuarios.view` global |
| POST | `/usuarios/{id}/asignaciones` | `usuarios.permissions` — `{ tipo: rol\|permiso, nombre, ambito, sede_id?, bloque_id?, desde?, hasta?, notas? }` |
| DELETE | `/usuarios/{id}/asignaciones/{asignacion}` | idem |

## Errores

| Código | Significado |
|--------|-------------|
| 401 | Sin token / token vencido o revocado |
| 403 | Sin permiso, fuera de alcance o cuenta desactivada |
| 404 | No existe |
| 422 | Validación: `{ message, errors: { campo: [..] } }` |
| 429 | Límite de intentos |

## Pruebas

`tests/Feature/Api/ApiV1Test.php` cubre login/refresh/logout, cuentas desactivadas, `/me`,
`/inicio`, IDOR por ID, asistencia idempotente y offline, estado de cuenta con beca,
inventario por QR, finanzas y asignaciones.
