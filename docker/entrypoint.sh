#!/bin/sh
set -e

echo "[entrypoint] Starting pdfhub..."

if [ ! -f .env ]; then
    echo "[entrypoint] Creating .env from .env.example"
    cp .env.example .env
fi

if ! grep -qE '^APP_KEY=.+' .env; then
    echo "[entrypoint] Generating APP_KEY"
    php artisan key:generate --force
fi

echo "[entrypoint] Running migrations (with retry)"
DB_OK=0
for i in 1 2 3 4 5 6; do
    if timeout 30 php artisan migrate --force; then
        DB_OK=1
        break
    fi
    echo "[entrypoint] Migrate attempt $i failed, retrying in 5s..."
    sleep 5
done

if [ "$DB_OK" -ne 1 ]; then
    echo "[entrypoint] WARNING: migrations did not complete. Starting server anyway."
fi

export PHP_CLI_SERVER_WORKERS="${PHP_CLI_SERVER_WORKERS:-4}"
export PORT="${PORT:-10000}"

echo "[entrypoint] Serving on 0.0.0.0:${PORT} with ${PHP_CLI_SERVER_WORKERS} workers"
exec php -d memory_limit=256M artisan serve --host=0.0.0.0 --port="${PORT}"
