# Local environment for the API. It has the same PHP version and extensions as the host.
FROM php:8.5-cli

RUN apt-get update \
    && apt-get install -y --no-install-recommends libfreetype6-dev libjpeg62-turbo-dev libpng-dev unzip \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" gd pdo_mysql

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY uploads.ini /usr/local/etc/php/conf.d/uploads.ini

WORKDIR /app/api
