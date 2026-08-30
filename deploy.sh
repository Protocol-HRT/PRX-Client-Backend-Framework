#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")"

COMPOSE="docker compose -f docker-compose.prod.yml"

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

# Expose .env.prod values to docker compose interpolation (DB_*, REDIS_PASSWORD, APP_PORT, …)
set -a
# shellcheck disable=SC1091
source .env.prod
set +a

if [ -z "${APP_KEY:-}" ]; then
  echo "ERROR: APP_KEY is empty in .env.prod"
  echo "  openssl rand -base64 32   # paste the result into APP_KEY="
  exit 1
fi

echo "==> Building images"
$COMPOSE build

echo "==> Starting mysql, redis, app, nginx, horizon, scheduler"
$COMPOSE up -d

echo "==> Waiting for MySQL to become healthy"
until $COMPOSE exec -T mysql mysqladmin ping -h localhost --silent 2>/dev/null; do
  sleep 2
done
echo "    MySQL is up"

echo "==> Migrations"
$COMPOSE exec -T app php artisan migrate --force

echo "==> Seed base data only on a fresh database (no users yet)"
$COMPOSE exec -T app php artisan tinker --execute="
if (\Illuminate\Support\Facades\DB::table('users')->count() === 0) {
    \Illuminate\Support\Facades\Artisan::call('db:seed', ['--force' => true]);
}
" || true

echo "==> Refresh caches with the real APP_KEY, then restart workers"
$COMPOSE exec -T app php artisan optimize:clear >/dev/null 2>&1 || true
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