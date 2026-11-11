# syntax=docker/dockerfile:1
# Imagen única de La Chilinga para Railway. El mismo build sirve para 3 servicios según
# PROCESO (o el argumento de start.sh): web (FrankenPHP), scheduler y worker. Ver docs/DEPLOY_RAILWAY.md.
# Versiones fijadas: subirlas a propósito, no por accidente.

# ── 1) Assets del frontend (Vite + editor Diseño) ────────────────────────────────────────────
# Node 20 → npm 10: el lock de resources/opendesign exige esa versión (ver docs/DEPLOY.md).
FROM node:20.20-bookworm-slim AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY . .
RUN npm run build

# ── 2) Imagen final: FrankenPHP (Caddy + PHP 8.3) ────────────────────────────────────────────
FROM dunglas/frankenphp:1.12-php8.3-bookworm

ENV DEBIAN_FRONTEND=noninteractive \
    PORT=8080 \
    COMPOSER_ALLOW_SUPERUSER=1

# Extensiones PHP (GD con JPEG/WebP/FreeType para miniaturas del archivo histórico).
RUN install-php-extensions pdo_mysql pdo_sqlite gd exif pcntl bcmath zip intl opcache

# python3: genera los JSON de partituras; ffmpeg: kit de percusión; gosu: baja de root a www-data
# cuando Railway arranca como root para poder escribir el volumen (RAILWAY_RUN_UID=0).
RUN apt-get update && apt-get install -y --no-install-recommends \
        python3 ffmpeg gosu unzip ca-certificates \
    && rm -rf /var/lib/apt/lists/*

RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY docker/php/uploads.ini docker/php/opcache.ini $PHP_INI_DIR/conf.d/
COPY docker/frankenphp/Caddyfile /etc/frankenphp/Caddyfile

COPY --from=composer:2.9 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Dependencias PHP primero (capa cacheable mientras no cambie composer.lock).
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --no-progress

COPY . .
COPY --from=assets /app/public/build ./public/build
COPY --from=assets /app/public/opendesign ./public/opendesign

# Autoload optimizado + package:discover (no toca la base).
RUN composer dump-autoload --optimize --no-dev --no-interaction \
    && rm -f /usr/bin/composer \
    && mkdir -p storage/app/public storage/app/persistent storage/framework/cache storage/framework/sessions \
        storage/framework/views storage/logs bootstrap/cache \
    && chmod +x start.sh \
    && chown -R www-data:www-data /var/www/html /data /config

USER www-data

EXPOSE 8080

# start.sh: espera la base, migra (si falla, el deploy falla), cachea config/rutas/vistas y arranca el proceso.
CMD ["/var/www/html/start.sh"]
