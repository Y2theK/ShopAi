#!/bin/sh
# Single-container HTTP mode: php-fpm in the background, nginx in the
# foreground. The entrypoint has already run migrations and config caching.
set -e

# Hosts like Render inject PORT and route traffic to it.
if [ -n "$PORT" ] && [ "$PORT" != "8080" ]; then
    sed -i "s/listen 8080;/listen $PORT;/" /etc/nginx/http.d/default.conf
fi

mkdir -p /run/nginx
php-fpm -D
exec nginx -g 'daemon off;'
