# Modelo de personas

## Principio

**Una persona es una sola identidad**, aunque sea alumna, docente, coordinadora, contadora y
encargada de inventario al mismo tiempo. Sus funciones no son "tipos de usuario": son
perfiles y asignaciones colgados de la misma persona.

```
personas ─┬─ users            (0..1)  cuenta de acceso: username, email, password, activo, último acceso
          ├─ alumnos          (0..n)  perfil alumno: instrumento, sede principal, bloques (alumno_bloque)
          │    └─ becas       (0..n)  tipo, %, monto fijo, vigencia, motivo, estado
          ├─ profesores       (0..1)  perfil docente: bloques con rol (bloque_profesor), sedes con rol (profesor_sede)
          └─ asignaciones     (0..n)  rol o permiso + alcance (global / sede / bloque) + vigencia
```

## Tabla `personas`

| Campo | Notas |
|-------|-------|
| `nombre`, `apellido` | Los datos migrados traen el nombre completo en `nombre` y `apellido` vacío (no se adivina dónde corta el apellido) |
| `dni` | Se guarda **normalizado** (solo dígitos/letras): `30.123.456` → `30123456`. Sin índice único en la base (los datos viejos pueden traer duplicados); las altas nuevas validan unicidad |
| `fecha_nacimiento`, `telefono`, `email`, `direccion` | |
| `contacto_emergencia_nombre`, `contacto_emergencia_telefono` | |
| `foto_path`, `observaciones` | |
| `estado` | `activo` · `inactivo` · `baja` |
| `fusionada_en_id` | Si fue un duplicado, apunta a la persona que quedó. Nunca se borra físicamente (soft delete) |

`alumnos.persona_id`, `profesores.persona_id` y `users.persona_id` son FK nullable
(`ON DELETE SET NULL`).

## Por qué Alumno y Profesor siguen existiendo

Son **perfiles** (datos propios de cada función) y conservan todas las relaciones del sistema
(asistencias, pagos, comprobantes, Villa Gesell, inventario usan `alumno_id`). Fusionarlos en
una sola tabla habría obligado a reescribir el módulo financiero sin necesidad. Ver
[ARQUITECTURA_OBJETIVO.md](ARQUITECTURA_OBJETIVO.md#2-identidad-persona--usuario--rol).

## Sincronización de datos personales

Mientras existan pantallas que leen `alumnos.nombre_apellido` o `profesores.nombre`:

- Editar la **persona** propaga nombre, DNI, fecha, teléfono y email a todas sus fichas.
- Editar la ficha de **alumno** o **docente** actualiza la persona (y de ahí al resto).
- Crear un alumno, docente o cuenta **siempre** vincula una persona (observers en
  `AppServiceProvider`), venga del CRUD, de la importación Excel, del alta rápida de Villa Gesell o de la API:
  1. la persona indicada (`persona_id`), si no
  2. la persona de la cuenta (`user_id`), si no
  3. la persona con el mismo DNI, si no
  4. una persona nueva.
- Al crear una cuenta para una persona, sus fichas quedan vinculadas a esa cuenta
  (`user_id`), y viceversa.

## Flujos

| Quiero… | Dónde |
|---------|-------|
| Ver todo sobre alguien | **Personas → ficha**: funciones, cuenta, permisos, cuotas/becas/saldo, asistencias, eventos, instrumentos |
| Que un alumno empiece a dar clase | Ficha → *Sumar al plantel docente* (no crea otra persona ni otra cuenta) |
| Que un docente empiece a cursar | Ficha → *Inscribir como alumno* |
| Darle acceso al sistema | Ficha → *Crear cuenta de acceso* → rol y alcance |
| Unir dos fichas duplicadas | Ficha → *¿Esta persona está duplicada?* o `php artisan chilinga:personas:fusionar {queda} {duplicada}` |
| Otorgar una beca | Ficha → perfil alumno → *Otorgar beca* (permiso `becas.manage`) |

### Fusión

`PersonaService::fusionar($queda, $duplicada)`:

- Mueve fichas de alumno, ficha docente, asignaciones y la cuenta (si solo una de las dos tiene cuenta).
- Completa datos vacíos de la que queda con los de la duplicada (DNI, teléfono…).
- Marca la duplicada con `fusionada_en_id`, estado `baja`, soft delete. Queda auditado.
- Si **las dos** tienen cuenta, se niega: primero hay que desactivar y desvincular una.

## Becas

| Campo | |
|-------|-|
| `tipo` | `porcentaje` · `monto_fijo` (descuento por cuota) · `total` |
| `porcentaje` / `monto` | según el tipo |
| `bloque_id` / `sede_id` | opcional: limita la beca a las cuotas de ese bloque o sede |
| `fecha_inicio`, `fecha_fin` | vigencia; se evalúa contra el vencimiento (o el 1° del mes) de cada cuota |
| `estado` | `activa` · `suspendida` · `finalizada` |
| `motivo`, `observaciones`, `otorgada_por` | |

El descuento nunca supera el importe de la cuota. Una persona con beca activa ejerce el rol
derivado `becado`.

## Estado de cuenta

`App\Domain\Finanzas\EstadoCuentaService::paraAlumno($alumno, $anio)` — mismo cálculo en la
ficha web, `GET /api/v1/mi/estado-cuenta` y `GET /api/v1/alumnos/{id}/estado-cuenta`:

1. Cuotas activas del año que **le corresponden** (misma regla que `Cuota::aplicaAAlumno`: bloque, sede, general y lista de alumnos).
2. Beca vigente aplicable → importe neto.
3. Pagos **no anulados** del alumno para esa cuota.
4. Estado: `pagada`, `becada`, `parcial`, `vencida`, `pendiente`; totales y vencido.

## Pagos anulados

Los pagos no se borran: se **anulan** (`anulado_at`, `anulado_por`, `motivo_anulacion`). El
detalle de un pago anulado deja de contar en saldos, reportes, dashboard y en la validación de
"ya pagó" (global scope en `PagoDetalle`), pero sigue visible desde el propio pago. Si el pago
venía de un comprobante del alumno, el comprobante vuelve a *pendiente*.
