#!/bin/sh
# DevLab container entrypoint — production.
#
# The development entrypoint installs dependencies, seeds players and builds
# assets on every boot, because a fresh clone that does not boot into something
# playable is a bug. None of that belongs here. Dependencies and assets are in
# the image; a production boot must be fast, repeatable, and must never invent
# data.
#
# The one thing it does add is rebuilding the framework caches, because the image
# ships with opcache.validate_timestamps=0 and a cached config would otherwise
# outlive the deployment that produced it.

set -eu

cd /var/www/html

# Is this a long-running service, or a one-off command someone ran with
# `docker compose run`?
#
# The distinction matters more than it looks. An earlier version required
# APP_KEY for EVERY invocation, including `php artisan key:generate --show` —
# the command its own error message told you to run to obtain a key. Utility
# commands get none of the service setup below and are simply executed.
IS_SERVICE=no
case "$*" in
    frankenphp*|*queue:work*|*schedule:work*) IS_SERVICE=yes ;;
esac

if [ "$IS_SERVICE" = "no" ]; then
    exec "$@"
fi

if [ -z "${APP_KEY:-}" ]; then
    echo "FATAL: APP_KEY is not set. Generate one with:" >&2
    echo "  docker run --rm devlab php artisan key:generate --show" >&2
    exit 1
fi

# Only the web container waits and migrates. A worker and a scheduler starting
# at the same moment would race on the migration table, which is a real failure
# mode rather than a theoretical one.
case "${1:-}" in
    frankenphp)
        echo "→ Waiting for the database"
        until php -r "new PDO('pgsql:host='.getenv('DB_HOST').';port='.(getenv('DB_PORT') ?: 5432).';dbname='.getenv('DB_DATABASE'), getenv('DB_USERNAME'), getenv('DB_PASSWORD'));" 2>/dev/null; do
            sleep 1
        done

        # On by default because this image targets a single-instance host, where
        # nothing else can be migrating at the same time. Set
        # DEVLAB_SKIP_MIGRATIONS=true before scaling the web service past one
        # replica, and run migrations as their own deploy step instead.
        if [ "${DEVLAB_SKIP_MIGRATIONS:-false}" != "true" ]; then
            echo "→ Running migrations"
            php artisan migrate --force --no-interaction
        fi

        # Content, not players. ContentSeeder is keyed updateOrCreate on slugs,
        # so this publishes newly authored challenges on deploy and repeats
        # harmlessly. It is deliberately NOT DatabaseSeeder, which would create
        # test@example.com with the password `password` on a public host.
        if [ "${DEVLAB_SEED_CONTENT:-true}" = "true" ]; then
            echo "→ Publishing catalogue content"
            php artisan db:seed --force --no-interaction --class='Database\Seeders\ContentSeeder'
        fi
        ;;
esac

# Rebuilt every boot rather than baked into the image: a cached config captures
# the environment it was built in, and the image is built before any deployment
# environment exists.
echo "→ Caching configuration, routes, views and events"
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

exec "$@"
