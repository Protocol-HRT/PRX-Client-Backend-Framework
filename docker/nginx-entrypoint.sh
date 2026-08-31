#!/bin/sh
set -eu

LIVE_DIR="/etc/letsencrypt/live"
DEFAULT_LINK="${LIVE_DIR}/default"
CERT_DIR="${LIVE_DIR}/selfsigned"

if [ ! -f "${DEFAULT_LINK}/fullchain.pem" ]; then
    echo "==> No SSL certificate found — generating temporary self-signed cert…"
    mkdir -p "${CERT_DIR}"
    openssl req -x509 -nodes -newkey rsa:2048 \
        -days 365 \
        -keyout "${CERT_DIR}/privkey.pem" \
        -out    "${CERT_DIR}/fullchain.pem" \
        -subj   "/CN=localhost/O=Dev/C=US" 2>/dev/null
    rm -f "${DEFAULT_LINK}"
    ln -s "${CERT_DIR}" "${DEFAULT_LINK}"
    echo "==> Self-signed cert ready at ${DEFAULT_LINK}"
fi

exec "$@"
