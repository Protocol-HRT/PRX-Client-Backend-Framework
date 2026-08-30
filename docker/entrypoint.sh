#!/usr/bin/env bash
set -e

cd /var/www/html

mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views \
         storage/logs bootstrap/cache
chmod -R g+w storage bootstrap/cache 2>/dev/null || true

# Media is served via the storage:link contract; start.sh/dev images ensure this too
php artisan storage:link >/dev/null 2>&1 || true

# Compile config/routes/events/view cache. Tolerate missing keys on first boot.
php artisan optimize >/dev/null 2>&1 || true

# Keep runtime dirs writable for the php-fpm pool user (www-data)
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

exec "$@"