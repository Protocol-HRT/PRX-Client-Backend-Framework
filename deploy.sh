#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")"

COMPOSE="docker compose --env-file .env.prod -f docker-compose.prod.yml"

if ! command -v docker >/dev/null 2>&1; then
  echo "ERROR: docker not found. Install Docker first." >&2
  exit 1
fi

if [ ! -f .env.prod ]; then
  echo "ERROR: missing .env.prod"
  echo "  cp .env.prod.example .env.prod && vi .env.prod"
  echo "  (set APP_KEY with: openssl rand -base64 32, plus APP_URL, DB_*, REDIS_PASSWORD, MAIL_*, ADMIN_*)"
  exit 1
fi

# docker compose --env-file .env.prod reads .env.prod for interpolation.
if [ -z "$(sed -n 's/^APP_KEY=//p' .env.prod | tail -1)" ]; then
  echo "ERROR: APP_KEY is empty in .env.prod"
  echo "  openssl rand -base64 32   # paste the result into APP_KEY="
  exit 1
fi

echo "==> Building images (includes composer + npm build)"
$COMPOSE build --no-cache app

echo "==> Starting app container (needed for asset publishing)"
$COMPOSE up -d app

echo "==> Waiting for app to be ready"
until $COMPOSE exec -T app php -r "exit(0);" 2>/dev/null; do
  sleep 2
done

echo "==> Copying built frontend assets to host"
mkdir -p ./public/build
$COMPOSE cp app:/var/www/html/public/build ./public/build

echo "==> Publishing vendor assets (Livewire, Filament, etc.)"
$COMPOSE exec -T --user www-data app php artisan livewire:publish --assets 2>/dev/null || true
$COMPOSE exec -T --user www-data app php artisan filament:assets 2>/dev/null || true
$COMPOSE exec -T --user www-data app php artisan vendor:publish --all 2>/dev/null || true

echo "==> Restarting all services"
$COMPOSE up -d --force-recreate app nginx horizon scheduler

echo "==> Waiting for MySQL to become healthy"
until $COMPOSE exec -T mysql mysqladmin ping -h localhost --silent 2>/dev/null; do
  sleep 2
done
echo "    MySQL is up"

echo "==> Migrations"
$COMPOSE exec -T --user www-data app php artisan migrate --force

echo "==> Seed base data only on a fresh database (no users yet)"
$COMPOSE exec -T --user www-data app php artisan tinker --execute="
if (\Illuminate\Support\Facades\DB::table('users')->count() === 0) {
    \Illuminate\Support\Facades\Artisan::call('db:seed', ['--force' => true]);
}
" || true

echo "==> Clearing stale caches"
rm -f bootstrap/cache/*.php 2>/dev/null || true
$COMPOSE exec -T --user www-data app php artisan optimize:clear >/dev/null 2>&1 || true

echo "==> Rebuilding caches"
$COMPOSE exec -T --user www-data app php artisan optimize
$COMPOSE restart 2>/dev/null || true

$COMPOSE ps

PORT="${APP_PORT:-8080}"
echo
echo "Backend is up. Browse:   http://localhost:${PORT}"
echo "Admin panel:  http://localhost:${PORT}/admin"
echo "API docs:     http://localhost:${PORT}/api/docs"
echo "Logs:         $COMPOSE logs -f app"
echo "Scale/reboot: $COMPOSE restart"
echo "Teardown:     $COMPOSE down"
