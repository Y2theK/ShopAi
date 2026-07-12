FROM php:8.4-fpm-alpine AS base

RUN apk add --no-cache su-exec \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && docker-php-ext-install opcache pcntl bcmath pdo_mysql \
    && apk del .build-deps

# ---- composer dependencies (built on the same PHP as production) ----
FROM base AS vendor

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www

# Scripts are skipped: artisan-based composer hooks run in the entrypoint
# instead, where the full application and environment are available.
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY . .
RUN composer install --no-dev --no-scripts --optimize-autoloader --no-interaction

# ---- production image ----
FROM base

WORKDIR /var/www

COPY --from=vendor /var/www /var/www
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/entrypoint.sh /usr/local/bin/app-entrypoint
RUN chmod +x /usr/local/bin/app-entrypoint

EXPOSE 9000

ENTRYPOINT ["app-entrypoint"]
CMD ["php-fpm"]
