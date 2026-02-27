# Dockerfile - Laravel La Chilinga (MySQL)
# PHP 8.3 requerido por composer.lock (spatie/laravel-permission, maennchen/zipstream-php, etc.)
FROM php:8.3-cli

# Variables de entorno
ENV DEBIAN_FRONTEND=noninteractive
ENV PORT=8000

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

# Instalar Node.js 18.x desde NodeSource
RUN curl -fsSL https://deb.nodesource.com/setup_18.x | bash - \
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
RUN if [ -f "package.json" ]; then npm install; fi
RUN if [ -f "package.json" ]; then npm run build; fi

# Permisos
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 775 /var/www/html/storage \
    && chmod -R 775 /var/www/html/bootstrap/cache

# Script de inicio
RUN chmod +x /var/www/html/start.sh || true

# Limpieza de cachés en build
RUN php artisan optimize:clear || true
RUN php artisan config:clear || true
RUN composer dump-autoload --optimize --no-interaction || true

EXPOSE ${PORT:-8000}

CMD ["/var/www/html/start.sh"]
