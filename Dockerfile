# All-in-one image for single-service hosts (e.g. Render free tier):
# nginx serves the built SPA and proxies /api, /sanctum, /up to php-fpm
# in the same container. Local development keeps using docker-compose.yml,
# which builds backend/ and frontend/ separately — this file does not
# affect it.

# ---- SPA build ----
FROM node:22-alpine AS spa

WORKDIR /app

COPY frontend/package.json frontend/package-lock.json ./
RUN npm ci

COPY frontend/ .

# Relative URLs: the SPA and the API share one origin in this image.
ARG VITE_API_BASE_URL=/api/v1
ARG VITE_BACKEND_URL=
ENV VITE_API_BASE_URL=${VITE_API_BASE_URL} \
    VITE_BACKEND_URL=${VITE_BACKEND_URL}

RUN npm run build

# ---- PHP base (mirrors backend/Dockerfile) ----
FROM php:8.4-fpm-alpine AS base

RUN apk add --no-cache su-exec libpq \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS postgresql-dev \
    && docker-php-ext-install opcache pcntl bcmath pdo_pgsql \
    && apk del .build-deps

# ---- composer dependencies ----
FROM base AS vendor

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www

COPY backend/composer.json backend/composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY backend/ .
RUN composer install --no-dev --no-scripts --optimize-autoloader --no-interaction

# ---- production image ----
FROM base

WORKDIR /var/www

# Hugging Face Spaces run the container as UID 1000 without root. Owning the
# app and the nginx runtime dirs by a uid-1000 user keeps that host working;
# root hosts (Render, compose) re-chown storage to www-data in the entrypoint.
RUN adduser -D -u 1000 app

COPY --from=vendor --chown=app:app /var/www /var/www
COPY backend/docker/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY backend/docker/entrypoint.sh /usr/local/bin/app-entrypoint
COPY backend/docker/app-web.sh /usr/local/bin/app-web
RUN chmod +x /usr/local/bin/app-entrypoint /usr/local/bin/app-web \
    && apk add --no-cache nginx redis \
    && mkdir -p /run/nginx \
    && chown -R app:app /run/nginx /var/lib/nginx /var/log/nginx

COPY --from=spa --chown=app:app /app/dist /var/www/spa
COPY backend/docker/nginx-fullstack.conf /etc/nginx/http.d/default.conf

EXPOSE 8080

ENTRYPOINT ["app-entrypoint"]
CMD ["app-web"]
