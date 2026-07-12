FROM php:8.4-fpm-alpine AS base

RUN apk add --no-cache su-exec libpq \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS postgresql-dev \
    && docker-php-ext-install opcache pcntl bcmath pdo_pgsql \
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
COPY docker/app-web.sh /usr/local/bin/app-web
RUN chmod +x /usr/local/bin/app-entrypoint /usr/local/bin/app-web

# nginx makes the image self-serving over HTTP for single-container hosts
# (e.g. Render); compose ignores it and keeps talking FastCGI to php-fpm.
RUN apk add --no-cache nginx
COPY docker/nginx-web.conf /etc/nginx/http.d/default.conf

EXPOSE 9000

ENTRYPOINT ["app-entrypoint"]
CMD ["php-fpm"]
