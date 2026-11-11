# Deploy en Railway (noviembre 2026)

Una sola imagen (`Dockerfile`) y tres servicios que se diferencian solo por el proceso que arrancan.
Cómo restaurar un backup o volver atrás un deploy: [ROLLBACK.md](ROLLBACK.md).

| Servicio | Config-as-code | Proceso | HTTP / healthcheck |
|----------|----------------|---------|--------------------|
| **web** (el que ya existe) | `/railway.toml` | `start.sh web`: espera la base, **migra (si falla, el deploy falla)**, cachés, FrankenPHP | sí, `GET /salud` |
| **scheduler** (nuevo) | `/railway.scheduler.toml` | `start.sh scheduler` → `php artisan schedule:work` | no |
| **worker** (nuevo, opcional) | `/railway.worker.toml` | `start.sh worker` → `php artisan queue:work --tries=3 --max-time=3600` | no |

El proceso también se puede elegir con la variable `PROCESO=web|scheduler|worker` (el argumento de
`start.sh` tiene prioridad).

## Qué cambió respecto del deploy anterior

- **Servidor**: antes `php artisan serve` (atiende un request a la vez). Ahora **FrankenPHP**
  (Caddy + PHP 8.3), con `PHP_THREADS` requests en paralelo (default 8), compresión y caché larga
  para `/build/*`. Config en `docker/frankenphp/Caddyfile`. Puerto: `PORT` (Railway lo inyecta).
- **Migraciones**: si `php artisan migrate` falla, el contenedor termina con error. Railway marca el
  deploy como fallido y **sigue sirviendo el deploy anterior**. Antes arrancaba igual con el esquema roto.
- **Cachés**: `php artisan optimize` (config, rutas, vistas, eventos) en cada arranque, con las
  variables reales. Consecuencia: `env()` solo funciona dentro de `config/*.php`.
- **Usuario**: la app corre como `www-data`, no como root.
- **Logs**: van a stderr (Railway → Deployments → Logs) con nivel `info`. Antes iban a un archivo
  dentro del contenedor que se perdía en cada deploy.
- **Procfile**: eliminado (era de Heroku y no se usaba).

> Los comandos `railway ssh` corren dentro del contenedor. Con `RAILWAY_RUN_UID=0` la sesión es root:
> anteponé `gosu www-data` (ej. `railway ssh --service web -- gosu www-data php artisan chilinga:respaldo`)
> para que los archivos que se creen queden con el dueño correcto.

## Checklist del dueño en Railway

Hacelo en este orden. Hasta el paso 2 inclusive el sistema queda igual que hoy (el web sigue corriendo
el scheduler adentro).

### 1. Servicio web (el actual)

1. Variables → agregar:
   - `RAILWAY_RUN_UID=0` — **obligatoria si el servicio tiene volumen**. Railway monta los volúmenes como
     root; con esto `start.sh` arranca como root solo para corregir permisos del volumen y enseguida baja
     a `www-data`. Sin ella, si el volumen no se puede escribir, el deploy falla con un mensaje claro
     (no se pierden comprobantes en silencio).
   - `PERSISTENT_STORAGE_PATH=/data/storage` — tiene que coincidir con el **mount path del volumen**
     (Settings → Volumes). Si hoy el volumen está montado en otra ruta, usá esa ruta.
   - `LOG_STACK=stderr` y `LOG_LEVEL=info` (si ya existían con `single`/`debug`, cambialas).
   - `SCHEDULER_EN_WEB=1` (default; se apaga en el paso 3).
2. Settings → Deploy → Healthcheck Path: lo toma de `railway.toml` (`/salud`, timeout 300 s). Si el
   dashboard tiene un valor puesto a mano, que sea `/salud`.
3. Settings → Networking: el puerto lo pone Railway en `PORT`; no hace falta tocar nada. Si el dominio
   tenía un "target port" fijo en 8000, cambialo a 8080 o dejalo vacío.
4. Deploy. En los logs tiene que aparecer `=== Migraciones ===`, `=== Servidor FrankenPHP en 0.0.0.0:<puerto> ===`
   y `GET /salud` devolviendo `{"ok":true,...}`.

### 2. Volumen

- Debe estar montado en el servicio **web** (Settings → Volumes) en la ruta de `PERSISTENT_STORAGE_PATH`.
- Ahí viven comprobantes, backups (`respaldos/`) y el disco `archivos` (si `ARCHIVOS_DISCO=local`).
- Un volumen de Railway se monta en **un solo servicio**: scheduler y worker no lo ven. Por eso la copia
  de backups y los archivos compartidos entre servicios van a S3/R2 (pasos 4 y 7).

### 3. Servicio scheduler

1. New → GitHub Repo → el mismo repo y rama que el web. Nombre: `scheduler`.
2. Settings → Config-as-code → **Railway config file**: `/railway.scheduler.toml`.
3. Variables: las mismas que el web (lo más simple: *Shared Variables* del proyecto, o "Copy variables"
   desde el web). No necesita dominio ni volumen. `numReplicas = 1` ya viene en el toml.
4. Deploy y verificá en logs `=== Scheduler (php artisan schedule:work) ===`.
5. Recién entonces, en el **web**: `SCHEDULER_EN_WEB=0` y redeploy. (Si quedaran los dos un rato no
   pasa nada grave: las tareas usan `onOneServer()` con lock en la base y los avisos son idempotentes.)

Ojo: el backup nocturno corre en el scheduler. Como el scheduler no tiene el volumen, sus backups locales
quedan en el disco efímero del scheduler: **configurá la copia externa (paso 4) antes de apagar el
scheduler del web**, o dejá `SCHEDULER_EN_WEB=1` hasta tenerla.

### 4. Copia de backups fuera de Railway (Cloudflare R2)

1. Cloudflare → R2 → Create bucket (ej. `chilinga-respaldos`), sin acceso público.
2. R2 → Manage API tokens → Create token con permiso **Object Read & Write** solo para ese bucket.
   Anotá Access Key ID, Secret Access Key y el endpoint `https://<account_id>.r2.cloudflarestorage.com`.
3. (Recomendado) En el bucket, una regla de ciclo de vida que borre objetos de más de 45 días, como red de
   seguridad además de la retención de la app.
4. Variables (en el servicio que corre el scheduler; si usás Shared Variables, en todos):
   ```
   AWS_ACCESS_KEY_ID=...
   AWS_SECRET_ACCESS_KEY=...
   AWS_DEFAULT_REGION=auto
   AWS_BUCKET=chilinga-respaldos
   AWS_ENDPOINT=https://<account_id>.r2.cloudflarestorage.com
   AWS_USE_PATH_STYLE_ENDPOINT=true
   RESPALDO_DISCO_EXTERNO=s3
   RESPALDO_CLAVE=<32+ caracteres al azar>     # openssl rand -base64 36
   RESPALDO_EXTERNO_CONSERVAR=30
   ```
5. **Guardá `RESPALDO_CLAVE` fuera de Railway** (gestor de contraseñas del dueño). Sin esa clave las
   copias no se pueden abrir. No es la `APP_KEY` a propósito: rotar una no rompe la otra.
6. Probar: `railway ssh --service scheduler -- php artisan chilinga:respaldo` y después
   `railway ssh --service scheduler -- php artisan chilinga:respaldo:probar --externo`
   (restaura la copia en una base temporal y la borra). Estado del sistema muestra
   "Con copia cifrada fuera del servidor".

### 5. Sentry (errores)

1. sentry.io → proyecto nuevo tipo **Laravel**. Copiá el DSN.
2. Variable `SENTRY_LARAVEL_DSN=<dsn>` en web, scheduler y worker. Sin DSN, Sentry queda apagado.
3. No se envían datos personales: `send_default_pii` y los valores de las consultas SQL están forzados a
   `false` en `config/sentry.php`. `/salud` y `/up` no generan trazas.
4. Prueba: `railway ssh --service web -- php artisan sentry:test`.

### 6. Heartbeats (aviso si algo deja de correr)

En Healthchecks.io (gratis) o Better Stack, creá dos checks:

| Variable | Período | Gracia |
|----------|---------|--------|
| `HEARTBEAT_URL_SCHEDULER` | 10 min (`HEARTBEAT_SCHEDULER_MINUTOS`) | 10 min |
| `HEARTBEAT_URL_RESPALDO` | 1 día | 2 h |

Ponelas en el servicio scheduler (o en el web mientras `SCHEDULER_EN_WEB=1`). El respaldo solo hace ping
si salió bien, incluida la copia externa cuando está configurada.

### 7. Worker (opcional, cuando haya trabajos en cola)

Hoy las notificaciones son síncronas y nadie encola trabajos, así que el worker no es necesario todavía.
Cuando se agregue algo encolado (por ejemplo la transcripción de audio):

1. New → mismo repo → nombre `worker`; Config-as-code: `/railway.worker.toml`
   (`restartPolicyType = "ALWAYS"`: `queue:work` se recicla solo cada hora).
2. Mismas variables que el web. `QUEUE_CONNECTION=database`.
3. Si el trabajo necesita archivos subidos desde el web, usá el disco `archivos` con `ARCHIVOS_DISCO=s3`
   (el volumen no se comparte entre servicios).

## Disco `archivos` (para el Equipo E y futuros procesos)

`Storage::disk('archivos')` es el lugar para archivos de usuarios que tienen que leer varios servicios:

- `ARCHIVOS_DISCO=local` (default): `PERSISTENT_STORAGE_PATH/archivos` (solo el servicio con el volumen).
- `ARCHIVOS_DISCO=s3`: prefijo `archivos/` (cambiable con `ARCHIVOS_PREFIJO`) en el bucket de `AWS_*`.

Los archivos existentes (comprobantes, biblioteca, archivo histórico) **no** se movieron.

## Variables nuevas

| Variable | Default | Para qué |
|----------|---------|----------|
| `PROCESO` | `web` | `web`, `scheduler` o `worker` |
| `SCHEDULER_EN_WEB` | `1` | el web corre el scheduler en segundo plano (poné `0` al crear el servicio scheduler). `SCHEDULER_ENABLED=0` (vieja) también lo apaga |
| `RAILWAY_RUN_UID` | — | `0` en el servicio con volumen (ver arriba) |
| `PERSISTENT_STORAGE_PATH` | `storage/app/persistent` | ruta del volumen |
| `PHP_THREADS` | `8` | requests simultáneos de FrankenPHP |
| `LOG_STACK` / `LOG_LEVEL` | `stderr` / `info` en producción | |
| `SENTRY_LARAVEL_DSN` | — | Sentry apagado si está vacío |
| `AWS_*` | — | S3 / R2 |
| `RESPALDO_DISCO_EXTERNO` / `RESPALDO_CLAVE` / `RESPALDO_EXTERNO_CONSERVAR` | — / — / `30` | copia cifrada del backup |
| `ARCHIVOS_DISCO` | `local` | disco `archivos` |
| `HEARTBEAT_URL_SCHEDULER` / `HEARTBEAT_SCHEDULER_MINUTOS` / `HEARTBEAT_URL_RESPALDO` | — / `10` / — | monitoreo |

## Probar la imagen en local

```bash
docker build -t chilinga-admin .
docker run --rm --network host --user root \
  -e PORT=18093 -e APP_ENV=production -e APP_KEY=$(php artisan key:generate --show) \
  -e DB_HOST=127.0.0.1 -e DB_DATABASE=chilinga_local -e DB_USERNAME=root -e DB_PASSWORD=... \
  -e PERSISTENT_STORAGE_PATH=/data/storage -v chilinga-vol:/data/storage chilinga-admin
curl http://127.0.0.1:18093/salud
```

Scheduler o worker: agregá `/var/www/html/start.sh scheduler` (o `worker`) al final del `docker run`.
