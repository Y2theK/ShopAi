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
    bootstrap/cache \
    /data

chown -R www-data:www-data storage bootstrap/cache /data

if [ "$1" = "php-fpm" ]; then
    [ -f /data/database.sqlite ] || su-exec www-data touch /data/database.sqlite
    su-exec www-data php artisan migrate --force
    su-exec www-data php artisan config:cache
    # route:cache is skipped: routes/web.php contains a closure route, which
    # cannot be serialized into the route cache.
    touch /tmp/app-ready
    # php-fpm's master process must run as root; its workers drop to www-data.
    exec "$@"
fi

# Any other command (queue worker, one-off artisan): wait until the app
# container has finished running migrations, then run as www-data.
until [ -f /data/database.sqlite ] && su-exec www-data php artisan migrate:status >/dev/null 2>&1; do
    echo "Waiting for the app container to finish database setup..."
    sleep 2
done

exec su-exec www-data "$@"
