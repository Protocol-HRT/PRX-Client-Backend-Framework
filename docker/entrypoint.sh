#!/usr/bin/env bash
set -e

cd /var/www/html

mkdir -p storage/app/public \
         storage/framework/cache/data storage/framework/sessions storage/framework/views \
         storage/logs bootstrap/cache
chmod -R 775 storage bootstrap/cache 2>/dev/null || true
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

# Filament Shield (via AdminUserSeeder / db:seed) may write generated policy files into app/
chown -R www-data:www-data app 2>/dev/null || true

# Ensure storage link exists
php artisan storage:link >/dev/null 2>&1 || true

exec "$@"
