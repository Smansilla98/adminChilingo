# Orden de migraciones y claves foráneas

Laravel ejecuta las migraciones en **orden alfabético por nombre de archivo**. El orden queda definido por el timestamp en el nombre.

## Orden de ejecución (dependencias respetadas)

1. **0001_01_01_000000** – users, password_reset_tokens, sessions (FK sessions → users)
2. **0001_01_01_000001** – cache, cache_locks
3. **0001_01_01_000002** – jobs, job_batches, failed_jobs
4. **2026_02_20_032342** – add_role_to_users, create_permission_tables, **profesores**, **sedes**
5. **2026_02_20_032343** – **bloques** (depende: profesores, sedes)
6. **2026_02_20_032344** – **alumnos** (depende: sedes, bloques)
7. **2026_02_20_032345** – **asistencias** (depende: alumnos, bloques)
8. **2026_02_20_032346** – **eventos** (depende: sedes, profesores, bloques, users)
9. **2026_02_26_*** – alter asistencias, bloques, eventos; user_id en profesores/alumnos; alumno_bloque; coordinador en sedes; coordinador_area; cuotas; pagos; pago_detalles; facturacion_mensual; shows; bloque_horarios; expand tipo_evento; username en users; programa_ritmos
10. **2026_02_27_*** – inventario_items; ordenes_compra; add propiedad a sedes + gastos

## Cambios realizados (reconstrucción)

- **Orden:** Se renombraron las migraciones de alumnos, asistencias y eventos a timestamps **032344, 032345, 032346** para que **bloques** (032343) se ejecute antes que **alumnos**.
- **FK restauradas:** En `create_alumnos_table` y `create_asistencias_table` se volvieron a definir las FK a `bloques` (antes se habían quitado por el orden incorrecto).
- **Sintaxis actual:** Todas las FK usan `->constrained('tabla')->nullOnDelete()` o `->cascadeOnDelete()` en lugar de `->onDelete('set null')` / `->onDelete('cascade')`.
- **FK añadidas:** `eventos.created_by` → users, `pagos.registrado_por` → users, `ordenes_compra.created_by` → users, `gastos.created_by` → users, `sessions.user_id` → users.

## Cómo probar

Con MySQL en marcha y `.env` configurado:

```bash
php artisan migrate:fresh --force
```

En Docker/Railway, las migraciones se ejecutan en el arranque vía `start.sh`; con este orden deberían completarse sin errores de FK.
