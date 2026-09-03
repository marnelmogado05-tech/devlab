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
elif [ composer.lock -nt vendor/composer/installed.json ]; then
    # A dependency added since this container last installed. See the note on
    # the Node check below: "already installed" is not "installed what the
    # lockfile now says".
    echo "→ PHP dependencies are out of date"
    composer install --no-interaction --prefer-dist
fi

if ! grep -q '^APP_KEY=base64:' .env; then
    echo "→ Generating application key"
    php artisan key:generate --force
fi

# Testing for the directory is not enough: `node_modules` is an anonymous volume,
# so Docker always creates the mount point and an unpopulated one still passes
# `-d`. Check that it has contents, the way the vendor check above does.
if [ -z "$(ls -A node_modules 2>/dev/null)" ]; then
    echo "→ Installing Node dependencies"
    npm install
elif [ package-lock.json -nt node_modules/.package-lock.json ]; then
    #
    # Populated is not the same as current. node_modules lives in an anonymous
    # volume that outlives `docker compose up`, so a dependency added to the
    # lockfile after a container first booted was never installed into it: the
    # emptiness check above passes and nothing else looked.
    #
    # That is not hypothetical. `@testing-library/react` was added months after
    # these volumes were created, so the container had `@testing-library/dom`
    # and `user-event` but not `react` — the frontend suite could not run in the
    # container at all, and the dev server logged a resolve error every time it
    # crawled a test file.
    #
    # npm writes node_modules/.package-lock.json on every install, which makes
    # it an honest record of what is actually on disk. If it is missing, `-nt`
    # is true and we install, which is the safe direction.
    #
    echo "→ Node dependencies are out of date"
    npm install
fi

# Only the PHP containers wait for PostgreSQL. The vite service must come up
# whether or not the database does — a frontend dev server blocked on a database
# is a confusing failure with no upside.
# The compose healthcheck already gates on this, but the queue worker can start
# fractionally ahead of Postgres accepting connections.
if [ "${1:-}" = "php" ]; then
    echo "→ Waiting for the database"
    until php -r "new PDO('pgsql:host='.getenv('DB_HOST').';port='.(getenv('DB_PORT') ?: 5432).';dbname='.getenv('DB_DATABASE'), getenv('DB_USERNAME'), getenv('DB_PASSWORD'));" 2>/dev/null; do
        sleep 1
    done
fi

# Only the web container migrates and builds. Two containers racing on the
# migration table — or writing public/build at the same time — is a real failure
# mode, not a theoretical one.
if [ "${1:-}" = "php" ] && [ "${3:-}" = "serve" ]; then
    echo "→ Running migrations"
    php artisan migrate --force

    # Content, every boot. The header above promises a playable instance, and
    # without this the catalogue is empty: "I'm Bored" has nothing to hand out
    # and quietly redirects to a list of nothing, which is the one first-run
    # experience §78 says counts as a bug.
    #
    # Safe to repeat: every seeder is keyed updateOrCreate on a slug or key, so
    # this refreshes the catalogue rather than duplicating it, and it picks up
    # new challenges on a later `docker compose up` without a reset. It does not
    # touch players — users, attempts and XP are written by the app, never here.
    echo "→ Seeding experiences, achievements and challenges"
    php artisan db:seed --force --class=Database\\Seeders\\ContentSeeder

    # public/build is gitignored, so a fresh clone has no compiled assets and
    # every page would 500 with "Vite manifest not found". The vite service
    # supersedes these with hot module reloading as soon as it is up; this build
    # is the floor that keeps the app working when it is not.
    if [ ! -f public/build/manifest.json ]; then
        echo "→ Building frontend assets (first run, this takes a minute)"
        npm run build
    fi
fi

# The Vite dev server writes public/hot and removes it on a clean exit — but not
# when Docker stops the container: the signal never reaches its cleanup hook, not
# even with vp as PID 1, because the server runs in a forked child. A stale hot
# file is a silent failure: Laravel keeps serving pages, but every asset points at
# a dev server that is gone, so the app renders unstyled and inert. Remove it on
# the way out instead of leaving it for the next person to debug.
case "${1:-}" in
    *"/vp")
        trap 'rm -f public/hot' EXIT
        trap 'kill -TERM "$child" 2>/dev/null' INT TERM
        "$@" &
        child=$!
        wait "$child"
        exit $?
        ;;
esac

exec "$@"
