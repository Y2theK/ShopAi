#!/bin/sh
set -e

cd /var/www

# Storage/cache dirs are excluded from the image or mounted as fresh volumes,
# so they may not exist yet on first start.
mkdir -p storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

chown -R www-data:www-data storage bootstrap/cache

if [ "$1" = "php-fpm" ]; then
    # Compose only starts this container once MySQL reports healthy, but the
    # server can still refuse connections for a moment — retry instead of dying.
    tries=0
    until su-exec www-data php artisan migrate --force; do
        tries=$((tries + 1))
        if [ "$tries" -ge 10 ]; then
            echo "Database never became reachable; giving up." >&2
            exit 1
        fi
        echo "Waiting for the database... (attempt $tries)"
        sleep 3
    done
    su-exec www-data php artisan config:cache
    # route:cache is skipped: routes/web.php contains a closure route, which
    # cannot be serialized into the route cache.
    touch /tmp/app-ready
    # php-fpm's master process must run as root; its workers drop to www-data.
    exec "$@"
fi

# Any other command (queue worker, one-off artisan): wait until the app
# container has finished running migrations, then run as www-data.
until su-exec www-data php artisan migrate:status >/dev/null 2>&1; do
    echo "Waiting for the app container to finish database setup..."
    sleep 2
done

exec su-exec www-data "$@"
