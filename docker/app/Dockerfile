# DevLab application image — development.
#
# A production image is a separate concern (multi-stage, no dev dependencies,
# built assets, opcache preloading) and belongs with the deployment work.

FROM php:8.4-cli-alpine

# System packages: postgres client libs, zip and git for Composer, plus the
# toolchain the PHP extensions need at build time.
RUN apk add --no-cache \
        bash \
        git \
        curl \
        icu-dev \
        libzip-dev \
        postgresql-dev \
        oniguruma-dev \
        nodejs \
        npm \
        $PHPIZE_DEPS \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_pgsql \
        pgsql \
        intl \
        zip \
        bcmath \
        opcache \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del $PHPIZE_DEPS

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Development settings: show errors, no opcache staleness surprises.
RUN { \
        echo 'opcache.enable=1'; \
        echo 'opcache.validate_timestamps=1'; \
        echo 'opcache.revalidate_freq=0'; \
        echo 'memory_limit=512M'; \
        echo 'upload_max_filesize=16M'; \
        echo 'post_max_size=16M'; \
    } > /usr/local/etc/php/conf.d/devlab.ini

WORKDIR /var/www/html

# Vite must bind to 0.0.0.0 to be reachable from the host.
ENV VITE_HOST=0.0.0.0

EXPOSE 8000 5173

COPY docker/app/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

ENTRYPOINT ["entrypoint"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
