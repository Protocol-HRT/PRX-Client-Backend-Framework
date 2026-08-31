#!/usr/bin/env bash
# init-letsencrypt.sh
#
# First-time Let's Encrypt setup for the prx-backend prod stack.
#
# Usage (from the project root, after .env.prod has APP_DOMAIN + CERTBOT_EMAIL):
#   docker compose --env-file .env.prod -f docker-compose.prod.yml \
#       run --rm certbot /init-letsencrypt.sh
#
# What it does:
#   1. Generates a temporary self-signed cert so nginx can start.
#   2. Requests a real certificate from Let's Encrypt via the webroot challenge.
#   3. Symlinks the cert directory to /etc/letsencrypt/live/default/ (which nginx references).
#   4. Reloads nginx to pick up the new cert.
#
set -euo pipefail

# ---- Read from environment (injected by docker-compose) ----
DOMAIN="${APP_DOMAIN:?Set APP_DOMAIN in .env.prod}"
EMAIL="${CERTBOT_EMAIL:?Set CERTBOT_EMAIL in .env.prod}"
STAGING="${CERTBOT_STAGING:-0}"   # Set to 1 for Let's Encrypt staging (testing)

LIVE_DIR="/etc/letsencrypt/live"
CERT_DIR="${LIVE_DIR}/${DOMAIN}"
DEFAULT_LINK="${LIVE_DIR}/default"
WEBROOT="/var/www/certbot"

echo "==> Domain:  ${DOMAIN}"
echo "==> Email:   ${EMAIL}"
echo "==> Staging: ${STAGING}"

# ---- Step 1: Generate temporary self-signed cert if none exists ----
if [ ! -f "${CERT_DIR}/fullchain.pem" ]; then
    echo "==> No existing certificate found — generating temporary self-signed cert…"
    mkdir -p "${CERT_DIR}"
    openssl req -x509 -nodes -newkey rsa:2048 \
        -days 1 \
        -keyout "${CERT_DIR}/privkey.pem" \
        -out    "${CERT_DIR}/fullchain.pem" \
        -subj   "/CN=${DOMAIN}/O=Dev/C=US"
    echo "==> Temporary cert generated."
else
    echo "==> Existing certificate found — skipping self-signed generation."
fi

# ---- Step 2: Symlink /etc/letsencrypt/live/default → domain cert dir ----
echo "==> Creating symlink: ${DEFAULT_LINK} → ${CERT_DIR}"
rm -f "${DEFAULT_LINK}"
ln -s "${CERT_DIR}" "${DEFAULT_LINK}"

# ---- Step 3: Request real certificate from Let's Encrypt ----
STAGING_FLAG=""
if [ "${STAGING}" = "1" ]; then
    STAGING_FLAG="--staging"
    echo "==> Using Let's Encrypt STAGING environment (test certs)."
fi

echo "==> Requesting certificate from Let's Encrypt…"

certbot certonly --webroot \
    -w "${WEBROOT}" \
    -d "${DOMAIN}" \
    --email "${EMAIL}" \
    --agree-tos \
    --no-eff-email \
    --force-renewal \
    ${STAGING_FLAG}

# ---- Step 4: Reload nginx ----
echo "==> Reloading nginx to use the new certificate…"
nginx -s reload

echo ""
echo "================================================"
echo "  Certificate issued for ${DOMAIN}"
echo "  Cert path: ${CERT_DIR}/fullchain.pem"
echo "  Key path:  ${CERT_DIR}/privkey.pem"
echo "  Symlink:   ${DEFAULT_LINK} → ${CERT_DIR}"
echo ""
echo "  Auto-renewal is handled by the certbot service"
echo "  (runs every 12 hours in docker-compose)."
echo "================================================"
