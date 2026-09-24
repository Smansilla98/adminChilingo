# Roles y relaciones (documento anterior)

Este documento describía el esquema previo (roles globales `admin`, `coordinador_sede`,
`profesor`, `alumno` y perfiles unidos por `user_id`). Fue reemplazado por:

- [MODELO_PERSONAS.md](MODELO_PERSONAS.md) — Persona única, perfiles y cuentas.
- [ROLES_Y_PERMISOS.md](ROLES_Y_PERMISOS.md) — permisos granulares con alcance (global / sede / bloque).

Las relaciones académicas que describía (`alumno_bloque`, `bloque_profesor`, `profesor_sede`,
`coordinador_area`, `sedes.coordinador_id`) se conservan y ahora **derivan roles con alcance**.
