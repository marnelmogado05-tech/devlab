#!/usr/bin/env bash
# DevLab container entrypoint — development.
#
# Makes a fresh `docker compose up` produce a working, seeded, playable
# instance. A clone that does not boot into something you can actually press
# "I'm Bored" on is a bug (plan §78).

set -euo pipefail

cd /var/www/html

if [ ! -f .env ]; then
    echo "→ No .env found, copying .env.example"
    cp .env.example .env
fi

if [ ! -d vendor ] || [ ! -f vendor/autoload.php ]; then
    echo "→ Installing PHP dependencies"
    composer install --no-interaction --prefer-dist
fi

if ! grep -q '^APP_KEY=base64:' .env; then
    echo "→ Generating application key"
    php artisan key:generate --force
fi

if [ ! -d node_modules ]; then
    echo "→ Installing Node dependencies"
    npm install
fi

# The compose healthcheck already gates on this, but the queue worker can start
# fractionally ahead of Postgres accepting connections.
echo "→ Waiting for the database"
until php -r "new PDO('pgsql:host='.getenv('DB_HOST').';port='.(getenv('DB_PORT') ?: 5432).';dbname='.getenv('DB_DATABASE'), getenv('DB_USERNAME'), getenv('DB_PASSWORD'));" 2>/dev/null; do
    sleep 1
done

# Only the web container migrates. Two containers racing on the migration table
# is a real failure mode, not a theoretical one.
if [ "${1:-}" = "php" ] && [ "${3:-}" = "serve" ]; then
    echo "→ Running migrations"
    php artisan migrate --force
fi

exec "$@"
