FROM php:8.3-fpm-bookworm

WORKDIR /var/www

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libzip-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libicu-dev \
        libonig-dev \
        libxml2-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        gd \
        intl \
        mbstring \
        opcache \
        pdo_mysql \
        zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

COPY www/service.antifraud.local.com /var/www/service.antifraud.local.com
COPY www/service.storage.company.com /var/www/service.storage.company.com

RUN cd /var/www/service.antifraud.local.com \
    && composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction \
    && cd /var/www/service.storage.company.com \
    && composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction \
    && mkdir -p \
        /var/www/service.antifraud.local.com/bootstrap/cache \
        /var/www/service.antifraud.local.com/storage/app \
        /var/www/service.antifraud.local.com/storage/framework/cache \
        /var/www/service.antifraud.local.com/storage/framework/sessions \
        /var/www/service.antifraud.local.com/storage/framework/views \
        /var/www/service.antifraud.local.com/storage/logs \
        /var/www/service.storage.company.com/bootstrap/cache \
        /var/www/service.storage.company.com/storage/app \
        /var/www/service.storage.company.com/storage/framework/cache \
        /var/www/service.storage.company.com/storage/framework/sessions \
        /var/www/service.storage.company.com/storage/framework/views \
        /var/www/service.storage.company.com/storage/logs \
    && chown -R www-data:www-data \
        /var/www/service.antifraud.local.com/bootstrap/cache \
        /var/www/service.antifraud.local.com/storage \
        /var/www/service.storage.company.com/bootstrap/cache \
        /var/www/service.storage.company.com/storage

CMD ["php-fpm"]
