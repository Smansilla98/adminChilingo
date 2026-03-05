# Ejecución — Todo listo para arrancar

Este documento describe cómo tener **todo armado para la ejecución** del sistema (local, Docker o Railway).

## Requisitos previos

- **PHP 8.3+**, Composer, Node 20+ (para compilar assets)
- **MySQL 5.7+** (o MariaDB compatible)
- Para producción: variables de entorno configuradas (ver abajo)

## 1. Variables de entorno obligatorias

Copiá `.env.example` a `.env` y completá al menos:

| Variable        | Descripción                          | Ejemplo (local)   |
|----------------|--------------------------------------|-------------------|
| `APP_KEY`      | Clave de cifrado (generar con `php artisan key:generate`) | base64:... |
| `DB_HOST`      | Host de MySQL                        | 127.0.0.1         |
| `DB_PORT`      | Puerto MySQL                         | 3306              |
| `DB_DATABASE`  | Nombre de la base                    | chilinga_admin    |
| `DB_USERNAME`  | Usuario MySQL                        | root              |
| `DB_PASSWORD`  | Contraseña MySQL                     | (vacío o tu clave)|

Recomendado para que el arranque no dependa de la DB para caché/sesión:

- `SESSION_DRIVER=file`
- `CACHE_STORE=file`

En `.env.example` ya están por defecto en `file`.

## 2. Primera vez (instalación local)

```bash
cd chilinga-admin
cp .env.example .env
php artisan key:generate
composer install
npm install && npm run build
php artisan migrate --force
php artisan db:seed --force
php artisan storage:link
php artisan serve
```

Abrir `http://localhost:8000`. Usuario: `admin`, contraseña: `admin123`.

## 3. Ejecución con Docker

El **Dockerfile** no ejecuta migraciones (no hay DB en build). Al **iniciar el contenedor**, `start.sh` hace todo:

1. Espera a que MySQL esté disponible (30 intentos × 2 s).
2. Limpia caché (config, route, view) sin tocar DB.
3. Ejecuta `php artisan migrate --force --no-interaction`.
4. Opcional: si `RUN_SEED=1`, ejecuta `php artisan db:seed --force`.
5. Crea `storage`/enlaces y arranca `php artisan serve`.

Build y ejecución:

```bash
docker build -t chilinga-admin .
docker run --rm -p 8000:8000 \
  -e APP_KEY=base64:TU_CLAVE \
  -e DB_HOST=host.docker.internal \
  -e DB_DATABASE=chilinga_admin \
  -e DB_USERNAME=root \
  -e DB_PASSWORD=tu_password \
  chilinga-admin
```

Para ejecutar seed en el primer deploy:

```bash
docker run ... -e RUN_SEED=1 chilinga-admin
```

## 4. Ejecución en Railway

El proyecto incluye `railway.toml`: Railway usa el **Dockerfile** y al desplegar ejecuta **start.sh** (espera DB, migraciones, luego servidor).

1. Conectar el repo; Railway detectará el Dockerfile (o usar `railway.toml`).
2. Añadir el servicio **MySQL** (plugin o imagen) y vincularlo al proyecto.
3. En **Variables** del servicio web, definir (o usar las que inyecta el plugin MySQL):
   - `APP_KEY` (generada)
   - `APP_ENV=production`
   - `APP_DEBUG=false`
   - `APP_URL=https://tu-app.up.railway.app`
   - `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` (o equivalentes del plugin)
   - Opcional: `RUN_SEED=1` en el primer deploy para usuarios y datos iniciales.

El **start.sh** se ejecuta al iniciar el contenedor; no hace falta configurar un comando de inicio aparte si el CMD del Dockerfile es `/var/www/html/start.sh`.

## 5. Migraciones idempotentes

Todas las migraciones están preparadas para poder ejecutarse más de una vez sin error:

- **Tablas:** se crean solo si no existen (`Schema::hasTable`).
- **Columnas:** se agregan solo si no existen (`Schema::hasColumn`).
- **Claves foráneas:** si fallan (p. ej. tabla referenciada no disponible), se agrega la columna sin FK.

Así, `migrate --force` en cada arranque es seguro aunque la base ya esté parcialmente migrada.

## 6. Checklist antes de producción

- [ ] `APP_KEY` generada y fija.
- [ ] `APP_ENV=production`, `APP_DEBUG=false`.
- [ ] `APP_URL` con la URL real (HTTPS).
- [ ] Variables `DB_*` correctas (o las que inyecte Railway/MySQL).
- [ ] `SESSION_DRIVER=file` y `CACHE_STORE=file` (recomendado para arranque sin depender de DB).
- [ ] Migraciones ejecutadas (automático vía `start.sh` en Docker/Railway).
- [ ] Opcional: `RUN_SEED=1` en el primer deploy para datos iniciales.
- [ ] `storage:link` (automático en `start.sh`).

Con esto, el proyecto queda **listo para ejecutarse** en local, Docker o Railway sin pasos manuales extra después del primer despliegue.
