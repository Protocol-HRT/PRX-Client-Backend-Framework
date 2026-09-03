#!/bin/sh
# init-letsencrypt.sh
#
# First-time Let's Encrypt setup for the prx-backend prod stack.
#
# Usage (from the project root, after .env.prod has APP_DOMAIN + CERTBOT_EMAIL):
#   docker compose --env-file .env.prod -f docker-compose.prod.yml \
#       run --rm certbot /init-letsencrypt.sh
#
# Prerequisites:
#   - APP_DOMAIN and CERTBOT_EMAIL must be set in .env.prod
#   - DNS A record for APP_DOMAIN must point to this server's IP
#   - nginx must be running (it serves the ACME challenge)
#
# What it does:
#   1. Requests a real certificate from Let's Encrypt via the webroot challenge.
#   2. Updates the /etc/letsencrypt/live/default symlink to point to the real cert.
#   3. Reloads nginx via Docker socket to pick up the new cert.
#
set -eu

DOMAIN="${APP_DOMAIN:?Set APP_DOMAIN in .env.prod}"
EMAIL="${CERTBOT_EMAIL:?Set CERTBOT_EMAIL in .env.prod}"
STAGING="${CERTBOT_STAGING:-0}"

LIVE_DIR="/etc/letsencrypt/live"
CERT_DIR="${LIVE_DIR}/${DOMAIN}"
DEFAULT_LINK="${LIVE_DIR}/default"
WEBROOT="/var/www/certbot"

echo "==> Domain:  ${DOMAIN}"
echo "==> Email:   ${EMAIL}"
echo "==> Staging: ${STAGING}"

# ---- Step 1: Request real certificate from Let's Encrypt ----
STAGING_FLAG=""
if [ "${STAGING}" = "1" ]; then
    STAGING_FLAG="--staging"
    echo "==> Using Let's Encrypt STAGING environment (test certs)."
fi

echo "==> Requesting certificate from Let. Encrypt..."

certbot certonly --webroot \
    -w "${WEBROOT}" \
    -d "${DOMAIN}" \
    --email "${EMAIL}" \
    --agree-tos \
    --no-eff-email \
    --force-renewal \
    ${STAGING_FLAG}

# ---- Step 2: Update the symlink so nginx uses the real cert ----
if [ -d "${CERT_DIR}" ]; then
    echo "==> Updating symlink: ${DEFAULT_LINK} -> ${CERT_DIR}"
    rm -f "${DEFAULT_LINK}"
    ln -s "${CERT_DIR}" "${DEFAULT_LINK}"
else
    echo "==> WARNING: ${CERT_DIR} not found after certbot run."
    echo "    The symlink still points to the self-signed cert."
fi

# ---- Step 3: Reload nginx via Docker socket ----
if [ -S /var/run/docker.sock ]; then
    apk add --no-cache docker-cli >/dev/null 2>&1 || true
    NGINX_ID=$(docker ps -q \
        --filter "label=com.docker.compose.project=prx-backend-prod" \
        --filter "label=com.docker.compose.service=nginx" | head -1)
    if [ -n "${NGINX_ID}" ]; then
        echo "==> Reloading nginx (container ${NGINX_ID})..."
        docker kill --signal=HUP "${NGINX_ID}" 2>/dev/null || true
    else
        echo "==> WARNING: nginx container not found. Reload manually:"
        echo "    docker compose --env-file .env.prod -f docker-compose.prod.yml exec nginx nginx -s reload"
    fi
else
    echo "==> WARNING: Docker socket not mounted. Reload nginx manually:"
    echo "    docker compose --env-file .env.prod -f docker-compose.prod.yml exec nginx nginx -s reload"
fi

echo ""
echo "================================================"
echo "  Certificate issued for ${DOMAIN}"
echo "  Cert path: ${CERT_DIR}/fullchain.pem"
echo "  Key path:  ${CERT_DIR}/privkey.pem"
echo ""
echo "  Auto-renewal is handled by the certbot service"
echo "  (runs every 12 hours in docker-compose)."
echo "================================================"
