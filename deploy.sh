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

echo "==> Building images"
$COMPOSE build

echo "==> Restarting services with fresh code (bind-mounted from host)"
$COMPOSE up -d --force-recreate app nginx horizon scheduler

echo "==> Waiting for MySQL to become healthy"
until $COMPOSE exec -T mysql mysqladmin ping -h localhost --silent 2>/dev/null; do
  sleep 2
done
echo "    MySQL is up"

echo "==> Installing composer dependencies (host code is bind-mounted, vendor is not)"
$COMPOSE exec -T app composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

echo "==> Building frontend assets (on host, bind-mounted into container)"
npm ci --ignore-scripts && npm run build

echo "==> Migrations"
$COMPOSE exec -T --user www-data app php artisan migrate --force

echo "==> Seed base data only on a fresh database (no users yet)"
$COMPOSE exec -T --user www-data app php artisan tinker --execute="
if (\Illuminate\Support\Facades\DB::table('users')->count() === 0) {
    \Illuminate\Support\Facades\Artisan::call('db:seed', ['--force' => true]);
}
" || true

echo "==> Refresh caches with the real APP_KEY, then restart workers"
$COMPOSE exec -T --user www-data app php artisan optimize:clear >/dev/null 2>&1 || true                            
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