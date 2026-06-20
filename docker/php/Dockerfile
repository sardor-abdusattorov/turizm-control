FROM php:8.4-fpm

RUN apt-get update && apt-get install -y --no-install-recommends \
        postgresql-client \
        libpq-dev \
        libzip-dev \
        libpng-dev \
        libjpeg-dev \
        libwebp-dev \
        libfreetype6-dev \
        libicu-dev \
        libonig-dev \
        libsqlite3-dev \
        git \
        unzip \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-configure gd --with-jpeg --with-webp --with-freetype \
    && docker-php-ext-install -j$(nproc) \
        pdo_pgsql \
        pgsql \
        pdo_sqlite \
        mbstring \
        bcmath \
        gd \
        intl \
        zip \
        opcache \
        pcntl \
        sockets \
        exif

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini

WORKDIR /var/www

CMD ["php-fpm"]
