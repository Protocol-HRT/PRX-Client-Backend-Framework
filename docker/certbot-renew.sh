#!/bin/sh
# certbot-renew.sh — Renew certs and reload nginx via Docker socket.
set -e

certbot renew --webroot -w /var/www/certbot --quiet

# If certbot exited 0 (renewal happened or not yet due), signal nginx to reload.
# Uses the Docker socket to send SIGHUP — nginx gracefully reloads certs.
if [ -f /var/run/docker.sock ]; then
    apk add --no-cache docker-cli >/dev/null 2>&1 || true
    # Find the nginx container by project label and reload it.
    NGINX_ID=$(docker ps -q --filter "label=com.docker.compose.project=prx-backend-prod" --filter "label=com.docker.compose.service=nginx" | head -1)
    if [ -n "$NGINX_ID" ]; then
        echo "==> Reloading nginx (container $NGINX_ID)…"
        docker kill --signal=HUP "$NGINX_ID" 2>/dev/null || true
    fi
fi
