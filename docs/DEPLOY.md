# Despliegue (Railway y general)

**Guía completa de ejecución (local, Docker, Railway):** ver **[EJECUCION.md](EJECUCION.md)**.

## Plataforma multirrol (septiembre 2026) — checklist de deploy

La infraestructura en Railway **no cambia** (mismo `Dockerfile`, mismo `start.sh`, misma base MySQL).

1. **Backup de la base** antes del deploy (Railway → MySQL → Backups, o `mysqldump --single-transaction`).
2. Deploy normal. `start.sh`:
   - `php artisan migrate --force` → crea personas, asignaciones, auditoría, becas, tokens, etc.
     y ejecuta el backfill (ver [MIGRACIONES.md](MIGRACIONES.md));
   - `php artisan chilinga:permisos:sync` → catálogo de permisos y roles.
3. Verificar: `railway run php artisan chilinga:diagnose`.
4. Los usuarios que eran **admin/dirección** quedan como **superadministradores** (mismos privilegios).
   Si no hubiera ninguno: `railway run php artisan chilinga:superadmin <usuario> --force`.
5. Revisar en **Configuración › Usuarios y permisos** que cada persona tenga sus funciones
   (contadores, tesorería, encargados: se asignan ahí con su alcance).

### Variables nuevas

| Variable | Default | |
|----------|---------|-|
| `SANCTUM_TOKEN_MINUTOS` | `43200` (30 días) | vencimiento de los tokens de la app |
| `PROGRAMA_EDICION_PUBLICA` | `true` | edición de partituras sin login (comportamiento histórico); `false` exige permiso |
| `EXPO_PUSH_ENABLED` | `false` | envío de push a la app vía Expo |
| `EXPO_ACCESS_TOKEN` | — | opcional, si el proyecto de Expo exige token para push |

### Scheduler (cron)

Además de los resúmenes existentes, el scheduler corre:

| Comando | Cuándo |
|---------|--------|
| `chilinga:avisos cuotas-vencidas` | todos los días 11:00 |
| `chilinga:avisos eventos` | todos los días 18:00 (eventos del día siguiente) |

Railway no ejecuta cron dentro del contenedor web: crear un **servicio Cron** en Railway con
el mismo repositorio y comando `php artisan schedule:run` cada minuto (o un Cron Job de
Railway con `*/5 * * * *`). Los avisos son idempotentes: correrlos de más no duplica.

### Colas

`QUEUE_CONNECTION=database`. Las notificaciones actuales son síncronas (no requieren worker).
Si en el futuro se encolan, agregar un servicio con `php artisan queue:work --tries=3`.

### API y app

- La API vive en el mismo servicio: `https://<dominio>/api/v1` ([API.md](API.md)).
- La app móvil **no** se despliega en Railway: se compila con EAS ([APP_MOVIL.md](APP_MOVIL.md)).
  `mobile/` está excluido de la imagen Docker (`.dockerignore`).
- Configurar en `mobile/eas.json` la `EXPO_PUBLIC_API_URL` de producción (dominio de Railway).

### Storage

Sin cambios: comprobantes y archivos en `storage/app` (usar volumen persistente o S3 como hasta ahora).

## Salud

- Laravel: `GET /up`
- App + DB: `GET /salud` (JSON; 503 si no hay conexión a la base)

## Migraciones recientes (automáticas en Railway)

`start.sh` ejecuta `php artisan migrate --force` y, además, estas tres (idempotentes):

- `2026_08_19_120000_create_observaciones_pedagogicas_table.php`
- `2026_08_19_160000_add_cargado_por_to_comprobantes_cuota_alumnos.php`
- `2026_08_19_180000_cuaderno_pedagogico_e_indices.php`

No hace falta correrlas a mano si el contenedor arranca con `start.sh`.

## Configuración general ya aplicada

- **TrustProxies**: la app confía en proxies (Railway/HTTPS).
- **Sesión segura**: en `APP_ENV=production` la cookie de sesión solo se envía por HTTPS.
- **Timezone**: `America/Argentina/Buenos_Aires` (configurable con `APP_TIMEZONE`).
- **MySQL**: soporte para variables `DB_*` o `MYSQLHOST`, `MYSQLDATABASE`, etc. del plugin MySQL.

## Variables de entorno en Railway

Usar las de `env.railway.example`: `APP_ENV`, `APP_DEBUG=false`, `APP_URL`, `APP_KEY`, `DB_*`, `SESSION_SECURE_COOKIE=true`, etc.

## Comandos de despliegue

En el build o en el primer deploy (Railway puede ejecutar comandos de release):

```bash
php artisan migrate --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
```

(Opcional) Usuarios de ejemplo:

```bash
php artisan db:seed --class=UsersSeeder
```

## Archivos subidos (comprobantes PDF)

Los PDF de pagos se guardan en `storage/app/public/pagos`. Es necesario:

1. Ejecutar `php artisan storage:link` para que `public/storage` apunte a `storage/app/public`.
2. En Railway el disco es efímero: los archivos se pierden al redeploy. Para conservarlos hace falta un disco persistente o un servicio como S3 (configurar disco `s3` en `config/filesystems.php` y variable `FILESYSTEM_DISK=s3`).

## Docker

Build y ejecución local (con MySQL en otro contenedor o en host):

```bash
docker build -t chilinga-admin .
docker run --rm -p 8000:8000 -e APP_KEY=base64:xxx -e DB_HOST=host.docker.internal -e DB_DATABASE=chilinga_admin -e DB_USERNAME=root -e DB_PASSWORD=xxx chilinga-admin
```

El `start.sh` espera a que MySQL esté disponible, ejecuta migraciones y `storage:link`, y arranca `php artisan serve` en el puerto `PORT` (por defecto 8000). Para producción con imagen Docker, pasar todas las variables de `env.railway.example`.

## Procfile

Incluido para entornos que usan buildpack Heroku PHP. Si Railway usa Nixpacks, puede ignorar el Procfile y detectar Laravel solo.

## Error "Table 'railway.sessions' doesn't exist"

Si la app devuelve 500 y en los logs aparece que la tabla `sessions` no existe, es porque **las migraciones no se ejecutaron** en la base de Railway (la tabla `sessions` se crea en la primera migración).

**Solución 1 (recomendada):** Ejecutar las migraciones en Railway:
```bash
railway run php artisan migrate --force
```
Con eso se crean `users`, `sessions`, y el resto de tablas.

**Solución 2 (mientras tanto):** La app usa por defecto el driver de sesión **file** (no base de datos). Así la app puede arrancar aunque la tabla `sessions` no exista. Si en tu `.env` tenés `SESSION_DRIVER=database`, quitá esa variable o cambiá a `SESSION_DRIVER=file` hasta haber ejecutado las migraciones. Con sesiones en archivo el login y el resto funcionan; las sesiones se guardan en `storage/framework/sessions` (en Railway ese disco puede ser efímero).

## Checklist antes de producción

- [ ] `APP_DEBUG=false`
- [ ] `APP_ENV=production`
- [ ] `APP_URL` con la URL real (HTTPS)
- [ ] `APP_KEY` generada y fija
- [ ] `DB_*` o variables MySQL del plugin correctas
- [ ] `SESSION_SECURE_COOKIE=true` (o dejar que lo ponga la app en producción)
- [ ] Migraciones ejecutadas
- [ ] `storage:link` ejecutado si se usan comprobantes en disco local
