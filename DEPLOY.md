# Despliegue (Railway y general)

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

## Checklist antes de producción

- [ ] `APP_DEBUG=false`
- [ ] `APP_ENV=production`
- [ ] `APP_URL` con la URL real (HTTPS)
- [ ] `APP_KEY` generada y fija
- [ ] `DB_*` o variables MySQL del plugin correctas
- [ ] `SESSION_SECURE_COOKIE=true` (o dejar que lo ponga la app en producción)
- [ ] Migraciones ejecutadas
- [ ] `storage:link` ejecutado si se usan comprobantes en disco local
