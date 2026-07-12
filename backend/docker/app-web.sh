#!/bin/sh
# Single-container HTTP mode: php-fpm in the background, nginx in the
# foreground. The entrypoint has already run migrations and config caching.
set -e

# Hosts like Render inject PORT and route traffic to it.
if [ -n "$PORT" ] && [ "$PORT" != "8080" ]; then
    sed -i "s/listen 8080;/listen $PORT;/" /etc/nginx/http.d/default.conf
fi

# Single-container hosts (e.g. Hugging Face Spaces) point REDIS_HOST at
# localhost and get an in-container Redis; compose uses its own redis service.
# Persistence is off: the cache can start cold, and Spaces disks are ephemeral.
case "$REDIS_HOST" in
    127.0.0.1|localhost)
        redis-server --daemonize yes --port "${REDIS_PORT:-6379}" \
            --dir /tmp --save '' --appendonly no
        ;;
esac

mkdir -p /run/nginx
php-fpm -D
exec nginx -g 'daemon off;'
