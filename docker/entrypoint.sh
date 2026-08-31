#!/usr/bin/env bash
set -e

cd /var/www/html

mkdir -p storage/app/public \
         storage/framework/cache/data storage/framework/sessions storage/framework/views \
         storage/logs bootstrap/cache
chmod -R g+w storage bootstrap/cache 2>/dev/null || true

# Filament Shield (via the AdminUserSeeder / db:seed) writes generated policy
# files into app/ at first boot. The build copies this tree as root, so hand
# it (and the framework dirs) to the php-fpm pool user.
chown -R www-data:www-data app storage bootstrap/cache 2>/dev/null || true

# Media is served via the storage:link contract; start.sh/dev images ensure this too
php artisan storage:link >/dev/null 2>&1 || true

# Compile config/routes/events/view cache. Tolerate missing keys on first boot.
php artisan optimize >/dev/null 2>&1 || true

# Keep runtime dirs writable for the php-fpm pool user (www-data)
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

# php-fpm master stays root (it must open /proc/self/fd/* logs); its pool
# workers already run as www-data (see zz-docker.conf). Horizon/scheduler
# get the www-data drop via their compose command (su-exec).
exec "$@"