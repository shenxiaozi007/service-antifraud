FROM composer:2 AS vendor

WORKDIR /app

COPY www/service.antifraud.local.com/composer.json www/service.antifraud.local.com/composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --prefer-dist \
    --optimize-autoloader \
    --no-scripts

COPY www/service.antifraud.local.com/ .
RUN composer dump-autoload --optimize --classmap-authoritative

FROM php:8.2-fpm-alpine

WORKDIR /var/www/html

RUN apk add --no-cache \
        freetype \
        icu-libs \
        libjpeg-turbo \
        libpng \
        libzip \
        oniguruma \
        unzip \
    && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        freetype-dev \
        icu-dev \
        libjpeg-turbo-dev \
        libpng-dev \
        libzip-dev \
        oniguruma-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        gd \
        intl \
        mbstring \
        opcache \
        pcntl \
        pdo_mysql \
        zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

COPY docs/docker/backend/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini
COPY docs/docker/backend/php/zz-production.ini /usr/local/etc/php/conf.d/zz-production.ini
COPY --from=vendor /app /var/www/html

RUN mkdir -p storage/app storage/framework/cache storage/framework/sessions storage/framework/views storage/logs \
    && chown -R www-data:www-data storage bootstrap/cache

USER www-data

EXPOSE 9000

CMD ["php-fpm"]
