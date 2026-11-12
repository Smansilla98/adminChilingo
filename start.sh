#!/bin/sh
# Arranque del contenedor de La Chilinga (Railway / Docker).
#
# Un mismo Dockerfile, tres procesos. Se elige con el primer argumento o con PROCESO:
#   web        (default) espera la base, migra, prepara cachés y sirve con FrankenPHP.
#              Si las migraciones fallan, el contenedor NO arranca: Railway deja el deploy anterior.
#   scheduler  php artisan schedule:work en primer plano (avisos, conciliación, backups).
#   worker     php artisan queue:work en primer plano (cola "database").
# Compatibilidad: sin servicio scheduler aparte, el web lo lanza en segundo plano
# (SCHEDULER_EN_WEB=1, default). Ver docs/DEPLOY_RAILWAY.md.
set -eu

SCRIPT="$(cd "$(dirname "$0")" && pwd)/$(basename "$0")"
cd "$(dirname "$SCRIPT")"

PROCESO="${1:-${PROCESO:-web}}"
PERSISTENTE="${PERSISTENT_STORAGE_PATH:-}"

case "$PROCESO" in
    web|scheduler|worker) ;;
    *) echo "✗ PROCESO desconocido: '$PROCESO' (usá web, scheduler o worker)" >&2; exit 64 ;;
esac

# ── Usuario ─────────────────────────────────────────────────────────────────────────────────
# La imagen corre como www-data. Si Railway la arranca como root (RAILWAY_RUN_UID=0, hace falta
# para que el volumen sea escribible), se corrigen los permisos del volumen y se baja a www-data.
if [ "$(id -u)" = "0" ] && [ "${CORRER_COMO_ROOT:-0}" != "1" ] && command -v gosu >/dev/null 2>&1; then
    if [ -n "$PERSISTENTE" ]; then
        mkdir -p "$PERSISTENTE"
        # Solo lo que no es de www-data (rápido aunque haya miles de comprobantes).
        find "$PERSISTENTE" \! -user www-data -exec chown www-data:www-data {} + 2>/dev/null || true
    fi
    chown -R www-data:www-data storage bootstrap/cache public 2>/dev/null || true
    exec gosu www-data "$SCRIPT" "$PROCESO"
fi

echo "=========================================="
echo "=== La Chilinga · proceso: $PROCESO ==="
echo "=========================================="
echo "APP_ENV: ${APP_ENV:-no configurado} · DB_CONNECTION: ${DB_CONNECTION:-no configurado} · DB_HOST: ${DB_HOST:-${MYSQLHOST:-no configurado}}"

# En Railway el disco es efímero: SESSION_DRIVER=file pierde la sesión en cada
# deploy y los formularios devuelven 419 PAGE EXPIRED.
if [ "${APP_ENV:-}" = "production" ] && [ "${SESSION_DRIVER:-file}" = "file" ]; then
    echo "SESSION_DRIVER=file en producción: pasando a database"
    export SESSION_DRIVER=database
fi

# El volumen tiene que ser escribible: sin esto se pierden comprobantes y backups en silencio.
if [ -n "$PERSISTENTE" ]; then
    mkdir -p "$PERSISTENTE" 2>/dev/null || true
    if ! ( touch "$PERSISTENTE/.escritura" && rm -f "$PERSISTENTE/.escritura" ) 2>/dev/null; then
        echo "✗ $PERSISTENTE no se puede escribir como $(id -un)." >&2
        echo "  En Railway: variable RAILWAY_RUN_UID=0 (start.sh corrige permisos y baja a www-data)." >&2
        exit 1
    fi
fi

# ── Base de datos ───────────────────────────────────────────────────────────────────────────
if [ "${DB_CONNECTION:-mysql}" = "mysql" ]; then
    echo "=== Esperando base de datos ==="
    i=0
    # shellcheck disable=SC2016 # es código PHP: las $ no son del shell
    until php -r '
        try {
            new PDO(
                "mysql:host=".(getenv("DB_HOST") ?: getenv("MYSQLHOST") ?: "127.0.0.1").
                ";port=".(getenv("DB_PORT") ?: getenv("MYSQLPORT") ?: "3306").
                ";dbname=".(getenv("DB_DATABASE") ?: getenv("MYSQLDATABASE") ?: ""),
                getenv("DB_USERNAME") ?: getenv("MYSQLUSER") ?: "root",
                getenv("DB_PASSWORD") ?: getenv("MYSQLPASSWORD") ?: "",
                [PDO::ATTR_TIMEOUT => 2]
            );
        } catch (Throwable $e) { exit(1); }
    ' 2>/dev/null; do
        i=$((i + 1))
        if [ "$i" -ge 30 ]; then
            echo "✗ La base no respondió en 60 s." >&2
            exit 1
        fi
        echo "Intento $i/30..."
        sleep 2
    done
    echo "✓ Base de datos disponible"
fi

# ── Cachés de Laravel (config, rutas, vistas, eventos) ──────────────────────────────────────
# Se regeneran en cada arranque con las variables reales del servicio. Si fallan, no se arranca.
echo "=== Cachés de Laravel ==="
php artisan optimize --no-interaction

if [ "$PROCESO" = "scheduler" ]; then
    echo "=== Scheduler (php artisan schedule:work) ==="
    exec php artisan schedule:work --no-interaction
fi

if [ "$PROCESO" = "worker" ]; then
    echo "=== Worker de colas (php artisan queue:work) ==="
    # --max-time: se recicla cada hora (Railway lo vuelve a levantar: restartPolicyType=ALWAYS).
    exec php artisan queue:work --tries=3 --max-time=3600 --sleep=3 --no-interaction
fi

# ── Solo web ────────────────────────────────────────────────────────────────────────────────
echo "=== Migraciones ==="
if ! php artisan migrate --force --no-interaction; then
    echo "✗ Las migraciones fallaron: el servidor NO arranca (Railway mantiene el deploy anterior)." >&2
    exit 1
fi

# Catálogo de permisos y roles (config/permisos.php → tablas de Spatie). Idempotente.
echo "=== Permisos y roles ==="
php artisan chilinga:permisos:sync --no-interaction || echo "⚠️  No se pudo sincronizar permisos."

# Diagnóstico de datos (solo lectura): queda en los logs del deploy; nunca frena el arranque.
if [ "${CHILINGA_DIAGNOSE:-1}" != "0" ]; then
    echo "=== Diagnóstico de datos ==="
    php artisan chilinga:diagnose --no-interaction || echo "⚠️  El diagnóstico no pudo completarse."
fi

# Seed opcional (solo si RUN_SEED=1)
if [ "${RUN_SEED:-0}" = "1" ]; then
    echo "=== Ejecutando seeders ==="
    php artisan db:seed --force --no-interaction || true
fi

# Partituras v4: regenerar JSON desde los .py y cargar/actualizar en la DB.
# Incremental por defecto: carga lo que falta o cambió en el repo (hash). Los toques
# editados por la escuela (con versiones en el editor) nunca se pisan.
# PARTITURAS_BOOTSTRAP_FORCE=1 recarga todo lo no editado aunque el hash coincida.
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
        echo "(partituras:bootstrap --force: recarga desde JSON v4)"
    else
        echo "(PARTITURAS_BOOTSTRAP_FORCE=0: solo actualiza si falta o cambió el hash)"
    fi
    # shellcheck disable=SC2086 # los argumentos van separados a propósito
    php artisan partituras:bootstrap $PARTITURAS_BOOTSTRAP_ARGS || echo "⚠️  Seeders de partituras fallaron. El servidor arranca igual."
fi

echo "=== Storage ==="
php artisan storage:link --no-interaction 2>/dev/null || true
mkdir -p storage/app/public storage/framework/cache storage/framework/sessions storage/framework/views

# Kit de percusión: regenerar WAV desde fuentes CC0 (mismo criterio que generar.py).
# Hace falta python3 + ffmpeg. PERC_KIT_REBUILD=0 usa los WAV ya commiteados.
echo "=== Kit de percusión ==="
PERC_DIR="public/sounds/perc"
KIT_PY="scripts/build-chilinga-kit.py"
KIT_SRC="${CHILINGA_KIT_SRC:-scripts/chilinga-kit-src}"
mkdir -p "$PERC_DIR"
if [ -f "$PERC_DIR/PROPIO" ]; then
    echo "(public/sounds/perc/PROPIO: kit grabado de la escuela, no se regenera)"
elif [ "${PERC_KIT_REBUILD:-1}" != "0" ] && [ -f "$KIT_PY" ]; then
    if ! command -v python3 >/dev/null 2>&1; then
        echo "⚠️  python3 no está; se usan los WAV del repo."
    elif ! command -v ffmpeg >/dev/null 2>&1; then
        echo "⚠️  ffmpeg no está; se usan los WAV del repo."
    elif [ ! -d "$KIT_SRC/vcsl" ]; then
        echo "⚠️  No está $KIT_SRC; se usan los WAV del repo."
    else
        echo "(regenerando public/sounds/perc desde $KIT_SRC)"
        python3 "$KIT_PY" "$KIT_SRC" || echo "⚠️  build-chilinga-kit.py falló; se siguen los WAV que haya."
    fi
else
    echo "(PERC_KIT_REBUILD=0: no se regeneran los WAV)"
fi
chmod -R a+rX "$PERC_DIR" 2>/dev/null || true
WAV_N=$(find "$PERC_DIR" -maxdepth 1 -type f -name '*.wav' 2>/dev/null | wc -l | tr -d ' ')
if [ "$WAV_N" -lt 27 ]; then
    echo "⚠️  $PERC_DIR tiene $WAV_N WAV (se esperan 27). El reproductor va a fallar."
else
    echo "✓ $WAV_N WAV listos en $PERC_DIR"
fi

# Scheduler dentro del web (compatibilidad): solo si no hay servicio scheduler aparte.
# Cuando crees el servicio "scheduler" en Railway, poné SCHEDULER_EN_WEB=0 en el web.
# SCHEDULER_ENABLED=0 (variable vieja) también lo apaga.
if [ "${SCHEDULER_EN_WEB:-1}" = "1" ] && [ "${SCHEDULER_ENABLED:-1}" != "0" ]; then
    echo "=== Scheduler en segundo plano (SCHEDULER_EN_WEB=1) ==="
    php artisan schedule:work --no-interaction 2>&1 &
fi

echo "=== Servidor FrankenPHP en 0.0.0.0:${PORT:-8080} ==="
exec frankenphp run --config /etc/frankenphp/Caddyfile --adapter caddyfile
