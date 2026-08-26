#!/bin/sh
set -e

echo "=========================================="
echo "=== Iniciando La Chilinga - Gestión   ==="
echo "=========================================="

echo "=== Variables de entorno ==="
echo "APP_ENV: ${APP_ENV:-no configurado}"
# En Railway el disco es efímero: SESSION_DRIVER=file pierde la sesión en cada
# deploy y los formularios devuelven 419 PAGE EXPIRED.
if [ "${APP_ENV:-}" = "production" ] && [ "${SESSION_DRIVER:-file}" = "file" ]; then
    echo "=== SESSION_DRIVER=file en producción: pasando a database ==="
    export SESSION_DRIVER=database
fi
echo "SESSION_DRIVER: ${SESSION_DRIVER:-no configurado}"
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

# Tablas/columnas de cuaderno pedagógico, comprobantes e índices.
# Laravel las saltea si ya están en `migrations`; se listan para que Railway
# no dependa de un migrate olvidado a mano.
echo "=== Migraciones 2026-08-19 (cuaderno / comprobantes / índices) ==="
for MIG in \
    database/migrations/2026_08_19_120000_create_observaciones_pedagogicas_table.php \
    database/migrations/2026_08_19_160000_add_cargado_por_to_comprobantes_cuota_alumnos.php \
    database/migrations/2026_08_19_180000_cuaderno_pedagogico_e_indices.php \
    database/migrations/2026_08_19_200000_inventario_codigo_y_movimientos.php \
    database/migrations/2026_08_19_210000_create_villa_gesell_gira_tables.php \
    database/migrations/2026_08_20_100000_alumnos_campos_opcionales_alta_rapida.php
do
    if [ -f "$MIG" ]; then
        php artisan migrate --force --no-interaction --path="$MIG" || echo "⚠️  Falló $MIG"
    fi
done

echo "=== Estado de migraciones ==="
php artisan migrate:status --no-interaction || true

# Seed opcional (solo si RUN_SEED=1)
if [ "${RUN_SEED:-0}" = "1" ]; then
    echo "=== Ejecutando seeders ==="
    php artisan db:seed --force --no-interaction || true
fi

# Partituras v4: regenerar JSON desde los .py y cargar seeders si faltan
PARTITURAS_PY="database/data/partituras-v4/generar.py"
if [ -f "$PARTITURAS_PY" ]; then
    echo "=== Partituras v4 (Python + seeders) ==="
    if command -v python3 >/dev/null 2>&1; then
        python3 "$PARTITURAS_PY" || echo "⚠️  generar.py reportó problemas; se continúa con los JSON disponibles."
    else
        echo "⚠️  python3 no está instalado; se usan los JSON ya generados."
    fi
    PARTITURAS_BOOTSTRAP_ARGS="--no-interaction"
    if [ "${PARTITURAS_BOOTSTRAP_FORCE:-0}" = "1" ]; then
        PARTITURAS_BOOTSTRAP_ARGS="$PARTITURAS_BOOTSTRAP_ARGS --force"
        echo "(PARTITURAS_BOOTSTRAP_FORCE=1: recarga todas las partituras v4)"
    fi
    php artisan partituras:bootstrap $PARTITURAS_BOOTSTRAP_ARGS || echo "⚠️  Seeders de partituras fallaron. El servidor arranca igual."
fi

# Autoload
composer dump-autoload --no-interaction --optimize 2>/dev/null || true

# Storage link y permisos mínimos
echo "=== Verificando storage ==="
php artisan storage:link 2>/dev/null || true
mkdir -p storage/app/public storage/framework/cache storage/framework/sessions storage/framework/views
chmod -R 775 storage bootstrap/cache 2>/dev/null || true

# artisan serve lanza un php -S hijo: hereda PHP_INI_SCAN_DIR (no los -d del padre)
if [ -d /usr/local/etc/php/conf.d ]; then
    export PHP_INI_SCAN_DIR="/usr/local/etc/php/conf.d"
fi
if [ -f /var/www/html/docker/php/uploads.ini ]; then
    export PHP_INI_SCAN_DIR="${PHP_INI_SCAN_DIR:+$PHP_INI_SCAN_DIR:}/var/www/html/docker/php"
fi

echo ""
echo "=== Límites de subida PHP ==="
php -d upload_max_filesize=100M -d post_max_size=110M -r 'echo "upload_max_filesize=".ini_get("upload_max_filesize")." post_max_size=".ini_get("post_max_size").PHP_EOL;'
echo "=========================================="
echo "=== Servidor iniciado ==="
echo "Host: 0.0.0.0"
echo "Port: ${PORT:-8000}"
echo "=========================================="
echo ""

exec php artisan serve --host=0.0.0.0 --port=${PORT:-8000}
