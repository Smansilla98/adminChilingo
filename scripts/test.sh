#!/usr/bin/env bash
# Corre la suite. Prefiere PHP local con pdo_sqlite; si falta, usa Docker (Dockerfile.test).
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

run_artisan_test() {
  php artisan config:clear --ansi >/dev/null 2>&1 || true
  exec php artisan test "$@"
}

if php -m 2>/dev/null | grep -qi pdo_sqlite; then
  run_artisan_test "$@"
fi

if command -v docker >/dev/null 2>&1; then
  echo "pdo_sqlite no está en el PHP local. Corriendo tests en Docker…" >&2
  docker build -f Dockerfile.test -t chilinga-admin-test "$ROOT" >/dev/null
  docker run --rm \
    -v "$ROOT":/app \
    -w /app \
    -e DB_CONNECTION=sqlite \
    -e DB_DATABASE=:memory: \
    chilinga-admin-test \
    php artisan test "$@"
  exit $?
fi

echo "Falta la extensión pdo_sqlite." >&2
echo "Instalá: sudo apt-get install php8.3-sqlite3" >&2
echo "O Docker, y volvé a correr: composer test / bash scripts/test.sh" >&2
exit 1
