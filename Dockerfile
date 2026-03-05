# Dockerfile - Laravel La Chilinga (MySQL)
# PHP 8.3 requerido por composer.lock (spatie/laravel-permission, maennchen/zipstream-php, etc.)
FROM php:8.3-cli

# Variables de entorno
ENV DEBIAN_FRONTEND=noninteractive
ENV PORT=8000
# Migraciones se ejecutan siempre con --force en start.sh (sin confirmación ni TTY)
ENV MIGRATE_FORCE=1

# Instalar dependencias del sistema
RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    ca-certificates \
    && rm -rf /var/lib/apt/lists/*

# Instalar extensiones PHP para MySQL
RUN docker-php-ext-install -j$(nproc) \
    pdo \
    pdo_mysql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    zip

# Instalar Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Instalar Node.js 20.x (Vite 7 requiere Node 20.19+ o 22.12+)
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Directorio de trabajo
WORKDIR /var/www/html

# Copiar archivos del proyecto
COPY . .

# Instalar dependencias PHP
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Regenerar autoloader
RUN composer dump-autoload --optimize --no-interaction || true

# Instalar dependencias Node y compilar assets
RUN if [ -f "package.json" ]; then npm install && npm run build; fi

# Permisos
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 775 /var/www/html/storage \
    && chmod -R 775 /var/www/html/bootstrap/cache

# Script de inicio: al arrancar ejecuta migraciones (--force) y luego php artisan serve
RUN chmod +x /var/www/html/start.sh || true

# No ejecutar artisan en build: no hay base de datos y Laravel intenta conectar (Connection refused).
# La limpieza de caché (optimize:clear, config:clear) se hace en start.sh al iniciar el contenedor.

EXPOSE ${PORT:-8000}

# Al iniciar, start.sh espera la DB, ejecuta php artisan migrate --force (y reintentos) y arranca el servidor
CMD ["/var/www/html/start.sh"]
