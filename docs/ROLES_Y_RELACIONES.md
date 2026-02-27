# Roles y relaciones – Chilinga Admin

## 1. Una persona puede ser profesor y alumno

- **User** puede tener vinculado un perfil **Profesor** (`user_id` en `profesores`) y/o un perfil **Alumno** (`user_id` en `alumnos`).
- Así, un mismo usuario puede dar clase en unos bloques (como profesor) y estar inscripto en otros (como alumno).
- Relaciones: `User::profesor()`, `User::alumno()`, `Profesor::user()`, `Alumno::user()`.

## 2. Un profesor puede tener varios bloques

- **Bloque** pertenece a un **Profesor** (`profesor_id`).
- Un profesor puede ser responsable de varios bloques: `Profesor::bloques()`.

## 3. Un alumno puede pertenecer a varios bloques

- Tabla pivot **`alumno_bloque`**: `alumno_id`, `bloque_id`, `es_principal`.
- **Alumno** tiene `bloques()` (BelongsToMany) y opcionalmente `bloque_id` como “bloque principal”.
- **Bloque** tiene `alumnos()` (BelongsToMany) a través de la misma pivot.
- Al crear/editar alumno se sigue usando “bloque principal”; la pivot se sincroniza con ese bloque.

## 4. Coordinador de sede

- **Sede** tiene `coordinador_id` (FK a `profesores`).
- Un profesor puede ser coordinador de una sede (ej. Banfield).
- Relaciones: `Sede::coordinador()`, `Profesor::sedeCoordinada()`.

## 5. Coordinadores de área

- Tabla **`coordinador_area`**: `profesor_id`, `area` (género, costa, tambores).
- Un profesor puede ser coordinador de una o más áreas.
- Modelo: `CoordinadorArea`; constantes: `CoordinadorArea::AREAS`.
- Relación: `Profesor::coordinadorAreas()`.

## 6. Roles (Spatie Permission)

- **admin** – Dirección.
- **direccion** – Mismo nivel que admin.
- **coordinador_sede** – Asignado al usuario que es coordinador de una sede (según `sedes.coordinador_id`).
- **coordinador_area** – Asignado al usuario que tiene al menos un registro en `coordinador_area`.
- **profesor** – Da clase en uno o más bloques.
- **alumno** – Inscripto en uno o más bloques.

Helpers en **User**: `isDireccion()`, `isCoordinadorSede()`, `isCoordinadorArea()`, `isAlumno()`.

## Migraciones a ejecutar

```bash
php artisan migrate
```

- `2026_02_26_000004_add_user_id_to_profesores_and_alumnos`
- `2026_02_26_000005_create_alumno_bloque_pivot_for_many_blocks`
- `2026_02_26_000006_add_coordinador_to_sedes`
- `2026_02_26_000007_create_coordinador_area_table`

Después de migrar, ejecutar seeders para crear los nuevos roles:

```bash
php artisan db:seed
```
