FROM php:8.5-fpm

COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/

RUN apt-get update \
    && apt-get install -y --no-install-recommends gdal-bin \
    && rm -rf /var/lib/apt/lists/* \
    && install-php-extensions pdo_pgsql zip \
    && mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && printf '[www]\npm.max_children = 16\n' > /usr/local/etc/php-fpm.d/zz-replot.conf

ENV APP_ENV=prod
WORKDIR /srv/backend

COPY .env /srv/.env
COPY backend/ ./
RUN composer install --no-dev --optimize-autoloader --no-interaction \
    && chown -R www-data:www-data var
