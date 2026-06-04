FROM php:8.4-fpm-alpine AS base

RUN apk add --no-cache \
    git curl bash \
    libpng-dev libjpeg-turbo-dev libwebp-dev freetype-dev \
    libzip-dev zip unzip \
    icu-dev oniguruma-dev \
    linux-headers $PHPIZE_DEPS

RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install \
        pdo_mysql mbstring exif pcntl bcmath gd zip intl opcache

RUN pecl install redis && docker-php-ext-enable redis

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

RUN addgroup -g 1000 app && adduser -u 1000 -G app -s /bin/sh -D app \
    && git config --global --add safe.directory /var/www/html

WORKDIR /var/www/html

# ─── dev ───────────────────────────────────────────────────────────────────────
FROM base AS dev

COPY docker/php/php.ini     /usr/local/etc/php/conf.d/app.ini
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini

USER app

# ─── prod ──────────────────────────────────────────────────────────────────────
FROM base AS prod

COPY docker/php/php.ini    /usr/local/etc/php/conf.d/app.ini
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini

COPY --chown=app:app . .

RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

USER app
