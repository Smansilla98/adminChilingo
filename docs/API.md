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
| GET | `/partituras` · `/partituras/{slug}` · `/partituras/{slug}/archivo` · `/partituras/muestras` · `/partituras/muestras/{archivo}.wav` | Toques. El detalle trae `lectura`, el `score` v4 (para escribir y escuchar) y `tiene_pdf`. El PDF y los WAV salen por la API. |
| POST | `/partituras` | Alta de toque (queda oculto). `partituras.admin` |
| PUT | `/partituras/{slug}` · `/partituras/{slug}/score` | Publicar/metadatos, y la notación. `partituras.admin` |
| POST | `/partituras/{slug}/archivo` | PDF o imagen de referencia. `partituras.admin` |
| DELETE | `/partituras/{slug}` | Baja del toque. `partituras.admin` |

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
| GET | `/roles` | cualquier sesión — qué gestiona cada perfil, agrupado por caso |
| GET | `/accesos/catalogo` | `usuarios.view` — roles (con permisos expandidos y ámbitos) y permisos |
| GET | `/usuarios?q=` · `/usuarios/{id}` | `usuarios.view` global |
| POST | `/usuarios/{id}/asignaciones` | `usuarios.permissions` — `{ tipo: rol\|permiso, nombre, ambito, sede_id?, bloque_id?, desde?, hasta?, notas? }` |
| DELETE | `/usuarios/{id}/asignaciones/{asignacion}` | idem |

### Archivo histórico

Ver `docs/ARCHIVO_HISTORICO.md`. Lo publicado se lee **sin token**; lo demás exige sesión.

| Método | Ruta | Acceso |
|--------|------|--------|
| GET | `/archivo` · `/archivo/timeline` | público — portada, línea de tiempo por décadas, capítulos |
| GET | `/archivo/capitulos` · `/archivo/capitulos/{slug}` | público |
| GET | `/archivo/eventos?anio=&capitulo=&q=` · `/archivo/eventos/{slug}` | público — acontecimientos (con fotos y relacionados) |
| GET | `/archivo/fotos?q=&decada=&anio=&desde=&hasta=&sede=&tipo=&tags[]=&persona=` · `/archivo/fotos/{id\|slug}` | público (lo no publicado, solo con permiso) |
| GET | `/archivo/personas?q=` | público — personas de fotos publicadas (solo nombre) |
| GET | `/archivo/catalogo` | sesión — tipos, fuentes, sedes, acontecimientos y `permisos` |
| GET | `/archivo/imagen/{id}/{400\|800\|1200\|2048}` | sesión — derivado de lo no publicado (aportante o equipo) |
| GET/POST | `/archivo/aportes` | sesión — mis aportes / subir (multipart `archivo` + datos; `enviar=1`; `409` si ya existe salvo `confirmar_duplicado=1`) |
| GET/PUT/DELETE | `/archivo/aportes/{id}` · POST `/archivo/aportes/{id}/enviar` | quien aportó, mientras el estado lo permita |
| GET | `/archivo/gestion/resumen` · `/archivo/gestion/moderacion?estado=` | `archivo.view\|manage\|moderate` |
| GET/POST | `/archivo/gestion/fotos` | listado con filtros (`estado`, `sin=fecha\|credito\|descripcion\|personas`, …) / carga del equipo |
| GET/PUT/DELETE | `/archivo/gestion/fotos/{id}` · POST `…/{id}/imagen` (reemplazar) | `archivo.manage` / `archivo.delete` con alcance |
| POST | `/archivo/gestion/fotos/{id}/estado` | `{ accion: aprobar\|rechazar\|cambios\|publicar\|ocultar, notas? }` — `moderate` / `publish` |
| POST | `/archivo/gestion/fotos/lote` | `{ ids[], accion: aplicar\|publicar\|ocultar\|eliminar, …datos }` → `{ hechas, omitidas }` |
| POST | `/archivo/gestion/fotos/orden` | `{ ids[] }` en el orden deseado |
| GET/POST/PUT/DELETE | `/archivo/gestion/capitulos[/{id}]` · `/archivo/gestion/eventos[/{id}]` | capítulos: alcance global; acontecimientos: sede propia |
| GET | `/archivo/gestion/personas?q=` | personas del sistema + nombres ya usados |

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

## Gestión completa (paridad con el panel web)

Todos los endpoints usan las mismas reglas y Policies que la web (servicios de
`app/Domain`). Las fichas incluyen `acciones` (qué puede hacer quien consulta, con
alcance). Listados paginados: `{ data, meta: { current_page, last_page, total } }`.
Detalle: `{ data: {...} }`. En los campos de año se usa `anio`.

| Recurso | Endpoints |
|---|---|
| Personas | `GET/POST /personas`, `GET/PUT /personas/{id}`, `POST /personas/{id}/fusionar` (filtros `funcion`, `estado`) |
| Becas | `GET /becas`, `GET/PUT /becas/{id}`, `POST /personas/{id}/becas` |
| Profesores | `GET/POST /profesores`, `GET/PUT/DELETE /profesores/{id}`, `GET /profesores/catalogo`, `GET /profesores/usuarios-disponibles` |
| Alumnos | `POST /alumnos`, `PUT/DELETE /alumnos/{id}`, `GET /alumnos/catalogo`, `GET /alumnos/exportar` (xlsx), `GET /alumnos/{id}/seguimiento` |
| Seguimiento | `POST /seguimiento`, `DELETE /seguimiento/{id}` |
| Bloques | `POST /bloques`, `PUT/DELETE /bloques/{id}`, `POST /bloques/{id}/horarios`, `DELETE /bloque-horarios/{id}`, `GET /bloques/catalogo` |
| Sedes | `GET /sedes?gestion=1`, `POST /sedes`, `GET/PUT/DELETE /sedes/{id}`, `GET /sedes/catalogo` |
| Eventos / Shows | `GET/POST /eventos`, `GET/PUT/DELETE /eventos/{id}`, `GET /eventos/catalogo`; `GET/POST /shows`, `GET/PUT/DELETE /shows/{id}` |
| Cuotas | `GET/POST /cuotas`, `GET/PUT/DELETE /cuotas/{id}`, `GET /cuotas/catalogo` |
| Pagos | `POST /pagos` (JSON o multipart con `comprobante`; `client_uuid` idempotente), `PUT /pagos/{id}`, `POST /pagos/{id}/anular`, `GET /pagos/{id}/comprobante`, `GET /pagos/cuotas-para-cobrar`, `GET /pagos/cuotas/{id}/alumnos`, `GET /mi/pagos-docente` |
| Comprobantes | `GET/POST /comprobantes`, `GET /comprobantes/{id}`, `GET /comprobantes/{id}/archivo`, `POST /comprobantes/{id}/visto`, `POST /comprobantes/{id}/aprobar`, `GET /comprobantes/opciones`, `GET/POST /mi/comprobantes` |
| Gastos | `GET/POST /gastos`, `GET/PUT/DELETE /gastos/{id}`, `POST /gastos/{id}/decision`, `GET /gastos/catalogo` |
| Facturación | `GET/POST /facturacion`, `GET/PUT /facturacion/{id}`, `GET /facturacion/catalogo`, `GET /facturacion/cierre-mes` |
| Compras | `GET/POST /compras`, `GET/PUT/DELETE /compras/{id}`, `POST /compras/{id}/estado`, `GET /compras/plan`, `GET /compras/catalogo` |
| Reportes | `GET /reportes`, `GET /reportes/excel`, `GET /reportes/imprimible`, `GET /reportes/profesores` |
| Usuarios | `POST /usuarios`, `PUT /usuarios/{id}`, `POST /usuarios/{id}/estado`, `POST /usuarios/{id}/resetear-acceso`, `GET /usuarios/catalogo` |
| Auditoría | `GET /auditoria`, `GET /auditoria/{id}`, `GET /auditoria/catalogo` |
| Villa Gesell | `GET /villa-gesell`, `PUT /villa-gesell/config`, inscriptos (`GET/POST`, `GET/PUT/DELETE {id}`, `GET nueva`, `GET alumnos-disponibles`), `POST alumnos-rapidos\|profesores-rapidos\|bloques-rapidos`, calendario (`GET calendario`, `POST dias/generar`, `PUT dias/{id}`, `POST dias/{id}/slots\|tocadas`, `PUT/DELETE tocadas/{id}`), gastos e insumos (`GET/POST`, `PUT/DELETE {id}`) |
| Diseño | `GET/POST /disenos`, `GET/PUT/DELETE /disenos/{id}`, `POST /disenos/{id}/paginas`, `PUT/DELETE /disenos/paginas/{id}`, `POST /disenos/paginas/{id}/duplicar`, `GET /disenos/plantillas[/{id}]`, `POST /disenos/imagenes`, `GET /disenos/marca`, `POST/DELETE /disenos/marca/kit[/{id}]` |
| Partituras | `GET /partituras/{slug}/versiones`, `GET /partituras/{slug}/versiones/{n}` (cada `PUT /partituras/{slug}/score` publica una versión nueva si la música cambió) |
| Biblioteca | `GET/POST /biblioteca`, `GET /biblioteca/{id}`, `GET /biblioteca/{id}/archivo`, `POST /biblioteca/{id}/visibilidad`, `DELETE /biblioteca/{id}`, `GET /biblioteca/catalogo` |
| Inventario | `DELETE /inventario/{id}` (además de lo ya documentado) |

Errores de negocio: `409` al aprobar un comprobante ya pagado; `422` con errores por
campo (p. ej. `lineas.0.alumno_id` en pagos).
