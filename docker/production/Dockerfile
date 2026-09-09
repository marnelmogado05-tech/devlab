# DevLab application image — production.
#
# The counterpart to docker/app/Dockerfile, whose own header says a production
# image is a separate concern. This is that concern: multi-stage, no dev
# dependencies, assets compiled in, opcache with timestamp validation off.
#
# The runtime is FrankenPHP, which is Caddy and PHP in one process. That choice
# is the reason this deploys on a free single-instance VM: the conventional
# split needs nginx or Caddy in one container and PHP-FPM in another, and then
# the web container must somehow hold a copy of `public/` at a path identical to
# the one the FastCGI SCRIPT_FILENAME points at. FrankenPHP removes the split,
# the shared volume, and the automatic-HTTPS setup all at once. It is a base
# image rather than a Composer dependency, and nothing in the application knows
# it is there — classic mode serves `public/index.php` exactly as php-fpm does,
# so this is not Octane and there is no worker-mode state to reason about.
#
#   docker build -f docker/production/Dockerfile -t devlab .

# ── Stage 1: PHP dependencies ────────────────────────────────────────────────
#
# Pinned to the BUILD platform, never the target. Composer output is PHP — it is
# identical whatever the image will eventually run on — so emulating this stage
# for an arm64 target buys nothing and costs a great deal.
FROM --platform=$BUILDPLATFORM composer:2 AS vendor

WORKDIR /app
COPY . .

# --no-dev is what keeps Pint, PHPStan, Pest and Faker out of the image. The
# authoritative classmap is safe precisely because nothing generates classes at
# runtime.
RUN composer install \
        --no-dev \
        --optimize-autoloader \
        --classmap-authoritative \
        --no-interaction \
        --prefer-dist

# ── Stage 2: frontend assets ─────────────────────────────────────────────────
#
# Needs BOTH toolchains. `resources/js/{routes,actions,wayfinder}` are generated,
# not committed, so a fresh checkout cannot resolve a single `@/routes` import
# until artisan has written them — and that is PHP work that has to happen before
# Vite runs. `--with-form` is required because vite.config.ts sets formVariants;
# without it the `.form` helpers are missing and the build fails.
# BUILD platform again, and here it is not an optimisation but a correctness
# fix. Rolldown ships native binaries, and under QEMU arm64 emulation they die
# on an instruction it cannot emulate:
#
#   process "/bin/sh -c npm ci && npm run build" did not complete successfully:
#   exit code: 132        (132 = 128 + SIGILL)
#
# The output is JavaScript and CSS, which no architecture cares about, so the
# stage runs natively and the compiled assets are copied into whichever runtime
# is being produced.
FROM --platform=$BUILDPLATFORM php:8.4-cli-alpine AS assets

RUN apk add --no-cache nodejs npm

WORKDIR /app
COPY --from=vendor /app/vendor ./vendor
COPY . .

# A throwaway key for a throwaway .env: artisan refuses to boot without one, and
# this file never leaves the build stage. The real key comes from the runtime
# environment.
RUN cp .env.example .env \
    && php artisan key:generate --force \
    && php artisan wayfinder:generate --with-form

RUN npm ci && npm run build

# ── Stage 3: runtime ─────────────────────────────────────────────────────────
#
# The ONLY stage that is target-platform specific, because it is the only one
# holding native code: the PHP extensions are compiled here, and they must match
# the architecture that will run them.
FROM dunglas/frankenphp:php8.4-alpine AS production

# The same extension set the development image installs, for the same reasons:
# pdo_pgsql for the source of truth, redis for cache/session/queue/leaderboards,
# intl and bcmath for formatting and scoring, opcache because this is production.
RUN install-php-extensions \
        pdo_pgsql \
        pgsql \
        intl \
        zip \
        bcmath \
        opcache \
        redis

# Timestamp validation OFF is the single largest difference between this image
# and the development one. It is safe because the code cannot change under a
# running container — a deploy replaces the image — and it is why the entrypoint
# rebuilds the framework caches on every boot rather than trusting stale ones.
RUN { \
        echo 'opcache.enable=1'; \
        echo 'opcache.validate_timestamps=0'; \
        echo 'opcache.memory_consumption=192'; \
        echo 'opcache.max_accelerated_files=20000'; \
        echo 'opcache.interned_strings_buffer=16'; \
        echo 'memory_limit=256M'; \
        echo 'upload_max_filesize=16M'; \
        echo 'post_max_size=16M'; \
        echo 'expose_php=Off'; \
        echo 'display_errors=Off'; \
        echo 'log_errors=On'; \
    } > /usr/local/etc/php/conf.d/devlab.ini

WORKDIR /var/www/html

COPY --from=vendor  /app/vendor        ./vendor
COPY --from=assets  /app/public/build  ./public/build
COPY . .

# `.env` is never baked in. Configuration arrives from the environment, so an
# image cannot carry one deployment's secrets into another.
RUN rm -f .env .env.example \
    && mkdir -p storage/framework/{cache/data,sessions,views} storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

COPY docker/production/Caddyfile   /etc/caddy/Caddyfile
COPY docker/production/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

EXPOSE 80 443

ENTRYPOINT ["entrypoint"]
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]
