#!/bin/sh
set -e

echo "=========================================="
echo "=== Iniciando La Chilinga - Gestión   ==="
echo "=========================================="

echo "=== Variables de entorno ==="
echo "APP_ENV: ${APP_ENV:-no configurado}"
echo "DB_CONNECTION: ${DB_CONNECTION:-no configurado}"
echo "DB_HOST: ${DB_HOST:-no configurado}"
echo "DB_DATABASE: ${DB_DATABASE:-no configurado}"
echo "DB_USERNAME: ${DB_USERNAME:-no configurado}"
echo ""

# Esperar a que MySQL esté disponible (máx. 30 intentos, 2s entre cada uno)
echo "=== Esperando base de datos ==="
for i in $(seq 1 30); do
    if php -r "
        try {
            new PDO(
                'mysql:host='.(getenv('DB_HOST') ?: '127.0.0.1').
                ';port='.(getenv('DB_PORT') ?: '3306').
                ';dbname='.(getenv('DB_DATABASE') ?: ''),
                getenv('DB_USERNAME') ?: 'root',
                getenv('DB_PASSWORD') ?: '',
                [PDO::ATTR_TIMEOUT => 2]
            );
            exit(0);
        } catch (Exception \$e) {
            exit(1);
        }
    " 2>/dev/null; then
        echo "✓ Base de datos disponible"
        break
    fi
    echo "Intento $i/30..."
    sleep 2
done

# Limpiar cachés (solo los que no usan DB)
echo "=== Limpiando cachés ==="
php artisan config:clear || true
php artisan route:clear || true
php artisan view:clear || true

# Migraciones (todas son idempotentes; --force para producción/Docker)
echo "=== Ejecutando migraciones ==="
php artisan migrate --force --no-interaction || {
    echo "⚠️  ADVERTENCIA: Las migraciones fallaron. Revisá los logs."
    echo "   El servidor arranca igual; la app puede tener funcionalidad limitada."
}

# Seed opcional (solo si RUN_SEED=1)
if [ "${RUN_SEED:-0}" = "1" ]; then
    echo "=== Ejecutando seeders ==="
    php artisan db:seed --force --no-interaction || true
fi

# Autoload
composer dump-autoload --no-interaction --optimize 2>/dev/null || true

# Storage link y permisos mínimos
echo "=== Verificando storage ==="
php artisan storage:link 2>/dev/null || true
mkdir -p storage/app/public storage/framework/cache storage/framework/sessions storage/framework/views
chmod -R 775 storage bootstrap/cache 2>/dev/null || true

echo ""
echo "=========================================="
echo "=== Servidor iniciado ==="
echo "Host: 0.0.0.0"
echo "Port: ${PORT:-8000}"
echo "=========================================="
echo ""

exec php artisan serve --host=0.0.0.0 --port=${PORT:-8000}
