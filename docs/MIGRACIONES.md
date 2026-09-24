# Migraciones

## Principios

- **Aditivas**: la plataforma multirrol no borra tablas ni columnas. Las columnas heredadas
  (`users.role`, `users.modulos_access`, `alumnos.bloque_id`, `bloques.profesor_id`,
  `sedes.coordinador_id`) siguen en uso por compatibilidad y se retiran recién cuando se
  compruebe en producción que nada las lee.
- **Idempotentes**: todas chequean `hasTable`/`hasColumn`; el backfill solo toca filas sin `persona_id`.
- **Portables**: corren en MySQL (producción) y SQLite (tests). El CI migra una base MySQL
  vacía, revierte y re-aplica la migración de plataforma.

## Migraciones de la plataforma (2026-09-23)

| Migración | Qué hace | Reversible |
|-----------|----------|-----------|
| `100000_create_personas_table` | tabla `personas` | sí |
| `100100_add_persona_id_and_account_fields` | `persona_id` en alumnos/profesores/users; `users.activo`, `users.ultimo_acceso_at` | sí |
| `100200_create_asignaciones_table` | roles/permisos con alcance | sí |
| `100300_create_auditoria_table` | registro de cambios | sí |
| `100400_create_becas_table` | becas | sí |
| `100500_add_trazabilidad_financiera_y_asistencia` | anulación de pagos, aprobación de gastos (los existentes quedan `aprobado`), `asistencias.registrado_por` | sí |
| `100600_create_notificaciones_y_sync_tables` | `notifications`, `dispositivos`, `notificacion_envios`, `sync_operaciones` | sí |
| `100700_create_personal_access_tokens_table` | tokens de Sanctum | sí |
| `110000_sync_permisos_y_backfill_personas` | **datos**: catálogo de permisos → Spatie; personas para todos los perfiles; dirección heredada → superadministrador | el `down` no deshace datos: al revertir `100100` se descartan los `persona_id` |

### Reglas del backfill (no adivina)

1. Cuenta + ficha docente + fichas de alumno que comparten `user_id` → **una** persona.
2. Alumnos con el **mismo DNI normalizado** → una persona.
3. Todo lo demás → una persona por ficha. Homónimos sin DNI **no** se unen solos:
   `chilinga:diagnose` los lista y se fusionan a mano.

## Correcciones a migraciones existentes

Las migraciones históricas fallaban en una base vacía (instalación nueva o tests):

| Migración | Problema | Corrección |
|-----------|----------|-----------|
| `2026_05_11_120000_create_comprobantes_cuota_alumnos_tables` | nombre de FK de 66 caracteres (> 64 de MySQL): la tabla quedaba sin FK | nombre explícito `cca_items_comprobante_fk` |
| `2026_04_23_135106_make_dni_nullable_in_alumnos_table` | `ALTER … MODIFY` (solo MySQL) | en otros motores usa el schema builder |
| `2026_08_20_100000_alumnos_campos_opcionales_alta_rapida` | idem | idem |
| `2026_02_26_000014_expand_tipo_evento_in_eventos` | el `enum` original quedaba como CHECK en SQLite | `string(50)` fuera de MySQL |

En producción estas migraciones ya figuran como ejecutadas: los cambios no las vuelven a correr.
> Nota: si la base de producción se creó desde cero con la versión anterior, la tabla
> `comprobante_cuota_alumno_items` puede no tener la FK a `comprobantes_cuota_alumnos`. No afecta
> el funcionamiento; `chilinga:diagnose` detecta ítems huérfanos si los hubiera.

## Procedimiento en producción

1. **Backup** de la base (Railway → MySQL → Backups, o `mysqldump --single-transaction`).
2. Diagnóstico previo (opcional, desde una copia): `php artisan chilinga:personas:backfill --simular`.
3. Deploy: `start.sh` corre `migrate --force` y `chilinga:permisos:sync`.
4. Validación:
   ```bash
   php artisan chilinga:diagnose            # duplicados, huérfanos, cuotas y pagos inconsistentes
   php artisan chilinga:diagnose --json > diagnostico.json
   ```
5. Resolver duplicados: `php artisan chilinga:personas:fusionar {queda} {duplicada}` (o desde la ficha web).
6. Si no quedó ningún superadministrador: `php artisan chilinga:superadmin {usuario}`.

## Comandos

| Comando | |
|---------|-|
| `chilinga:diagnose [--json] [--strict]` | Solo lectura. `--strict` devuelve error si hay hallazgos graves (útil en CI) |
| `chilinga:personas:backfill [--simular]` | Vuelve a correr el backfill (idempotente) |
| `chilinga:personas:fusionar {queda} {duplicada} [--force]` | Fusión auditada |
| `chilinga:permisos:sync` | `config/permisos.php` → tablas de Spatie |
| `chilinga:superadmin {usuario} [--force]` | Alta de superadministrador (puesta en marcha / recuperación) |
| `chilinga:avisos cuotas-vencidas\|eventos` | Genera avisos (lo corre el scheduler) |

## Pendiente (cuando se confirme en producción)

- Cambiar las FK `ON DELETE CASCADE` de `sedes → alumnos`, `alumnos → pago_detalles` y
  `cuotas → pago_detalles` por `RESTRICT`. Hoy la protección está en la aplicación
  (`EliminacionSegura`, con tests); cambiar FKs en caliente sobre MySQL con datos requiere
  ventana de mantenimiento.
- Retirar `users.modulos_access` (la matriz "Visibilidad del menú" solo oculta módulos).
