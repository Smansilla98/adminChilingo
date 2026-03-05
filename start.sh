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

# Esperar a que MySQL esté disponible
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

# Limpiar cachés (evita que config antigua afecte migrate)
echo "=== Limpiando cachés ==="
php artisan optimize:clear || true
php artisan config:clear || true
php artisan route:clear || true

# --- Migraciones forzadas (obligatorio en Docker/producción) ---
# Siempre se usa --force --no-interaction para no requerir confirmación ni TTY.
echo "=== Ejecutando migraciones (--force) ==="
MIGRATE_CMD="php artisan migrate --force --no-interaction"
MIGRATE_ATTEMPTS=5
MIGRATE_OK=0

# Primera ejecución inmediata
if $MIGRATE_CMD; then
    echo "✓ Migraciones aplicadas (primera pasada)"
    MIGRATE_OK=1
fi

# Reintentos si falló (p. ej. DB aún no lista)
if [ "$MIGRATE_OK" -eq 0 ]; then
    for i in $(seq 1 $MIGRATE_ATTEMPTS); do
        echo "Intento $i/$MIGRATE_ATTEMPTS..."
        if $MIGRATE_CMD; then
            echo "✓ Migraciones aplicadas (intento $i)"
            MIGRATE_OK=1
            break
        fi
        if [ "$i" -lt "$MIGRATE_ATTEMPTS" ]; then
            echo "Reintentando en 5s..."
            sleep 5
        fi
    done
fi

if [ "$MIGRATE_OK" -eq 0 ]; then
    echo "⚠️  ADVERTENCIA: Las migraciones fallaron tras varios intentos. Revisá los logs."
fi

# Segunda pasada por si quedó alguna migración pendiente
$MIGRATE_CMD && echo "✓ Revisión final de migraciones OK" || true

# Autoload
composer dump-autoload --no-interaction --optimize || true

# Storage link (comprobantes PDF, etc.)
echo "=== Verificando storage ==="
php artisan storage:link || true

echo ""
echo "=========================================="
echo "=== Servidor iniciado ==="
echo "Host: 0.0.0.0"
echo "Port: ${PORT:-8000}"
echo "=========================================="
echo ""

exec php artisan serve --host=0.0.0.0 --port=${PORT:-8000}
