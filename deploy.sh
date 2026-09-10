#!/usr/bin/env bash
#
# Quick deploy for a clean Debian 13 server.
#
# Usage (run from inside the cloned repo, as root):
#   git clone https://github.com/ahilespelid/messenger.git
#   cd messenger
#   sudo ./deploy.sh chat.example.com admin@example.com
#
# The domain must already have a DNS A record pointing at this server's IP —
# Caddy (bundled with FrankenPHP) needs that to issue a Let's Encrypt
# certificate automatically. Re-running this script on an existing install
# just pulls the latest code, rebuilds, and restarts (your .env is kept).

set -euo pipefail

DOMAIN="${1:-}"
OPERATOR_EMAIL="${2:-admin@${DOMAIN}}"

if [[ -z "$DOMAIN" ]]; then
    echo "Usage: sudo $0 <domain> [operator-email]" >&2
    echo "Example: sudo $0 chat.example.com admin@example.com" >&2
    exit 1
fi

if [[ $EUID -ne 0 ]]; then
    echo "Please run as root: sudo $0 $*" >&2
    exit 1
fi

if [[ ! -f docker-compose.yml || ! -f .env.example ]]; then
    echo "Run this script from inside the cloned repository (docker-compose.yml not found here)." >&2
    exit 1
fi

echo "==> Installing prerequisites"
apt-get update -y
apt-get install -y ca-certificates curl git openssl

echo "==> Installing Docker"
if ! command -v docker >/dev/null 2>&1; then
    curl -fsSL https://get.docker.com | sh
fi
systemctl enable --now docker

echo "==> Firewall"
if command -v ufw >/dev/null 2>&1 && ufw status | grep -q "Status: active"; then
    ufw allow 80/tcp || true
    ufw allow 443/tcp || true
    ufw allow 443/udp || true
    echo "    ufw is active — opened 80/tcp, 443/tcp, 443/udp"
else
    echo "    ufw is not active on this host — make sure 80/tcp, 443/tcp and"
    echo "    443/udp are open in your cloud provider's firewall/security group."
fi

echo "==> Preparing .env"
if [[ ! -f .env ]]; then
    cp .env.example .env

    APP_KEY="base64:$(openssl rand -base64 32)"
    DB_PASSWORD="$(openssl rand -hex 16)"
    DB_ROOT_PASSWORD="$(openssl rand -hex 16)"
    REVERB_APP_KEY="$(openssl rand -hex 16)"
    REVERB_APP_SECRET="$(openssl rand -hex 24)"
    OPERATOR_PASSWORD="$(openssl rand -hex 8)"

    sed -i "s|^APP_ENV=.*|APP_ENV=production|" .env
    sed -i "s|^APP_DEBUG=.*|APP_DEBUG=false|" .env
    sed -i "s|^APP_KEY=.*|APP_KEY=${APP_KEY}|" .env
    sed -i "s|^APP_URL=.*|APP_URL=https://${DOMAIN}|" .env
    sed -i "s|^APP_DOMAIN=.*|APP_DOMAIN=${DOMAIN}|" .env
    sed -i "s|^DB_PASSWORD=.*|DB_PASSWORD=${DB_PASSWORD}|" .env
    sed -i "s|^DB_ROOT_PASSWORD=.*|DB_ROOT_PASSWORD=${DB_ROOT_PASSWORD}|" .env
    sed -i "s|^REVERB_APP_KEY=.*|REVERB_APP_KEY=${REVERB_APP_KEY}|" .env
    sed -i "s|^REVERB_APP_SECRET=.*|REVERB_APP_SECRET=${REVERB_APP_SECRET}|" .env
    sed -i "s|^VAPID_SUBJECT=.*|VAPID_SUBJECT=mailto:${OPERATOR_EMAIL}|" .env
    sed -i "s|^OPERATOR_EMAIL=.*|OPERATOR_EMAIL=${OPERATOR_EMAIL}|" .env
    sed -i "s|^OPERATOR_PASSWORD=.*|OPERATOR_PASSWORD=${OPERATOR_PASSWORD}|" .env

    NEW_ENV=1
else
    echo "    .env already exists — leaving it as-is (delete it first for a fresh setup)"
    NEW_ENV=0
fi

echo "==> Building images (this can take a few minutes the first time)"
docker compose build

echo "==> Starting services"
docker compose up -d

echo "==> Waiting for the app container to be ready"
for i in $(seq 1 30); do
    if docker compose exec -T app true >/dev/null 2>&1; then
        break
    fi
    sleep 2
done

if ! grep -q '^VAPID_PUBLIC_KEY=.\+' .env; then
    echo "==> Generating Web Push (VAPID) keys"
    VAPID_OUTPUT="$(docker compose exec -T app php artisan webpush:vapid)"
    VAPID_PUBLIC="$(echo "$VAPID_OUTPUT" | grep VAPID_PUBLIC_KEY | cut -d= -f2 | tr -d '\r')"
    VAPID_PRIVATE="$(echo "$VAPID_OUTPUT" | grep VAPID_PRIVATE_KEY | cut -d= -f2 | tr -d '\r')"
    sed -i "s|^VAPID_PUBLIC_KEY=.*|VAPID_PUBLIC_KEY=${VAPID_PUBLIC}|" .env
    sed -i "s|^VAPID_PRIVATE_KEY=.*|VAPID_PRIVATE_KEY=${VAPID_PRIVATE}|" .env
    echo "==> Restarting app/queue to pick up the new VAPID keys"
    docker compose up -d --force-recreate app queue
fi

echo ""
echo "================================================================"
echo " Deployed."
echo ""
echo "   Client widget:   https://${DOMAIN}"
echo "   Operator login:  https://${DOMAIN}/login"
if [[ "${NEW_ENV:-0}" -eq 1 ]]; then
    echo "   Operator email:    ${OPERATOR_EMAIL}"
    echo "   Operator password: ${OPERATOR_PASSWORD}"
    echo ""
    echo "   (These credentials are saved in .env — OPERATOR_EMAIL / OPERATOR_PASSWORD)"
fi
echo ""
echo " If the site doesn't load over HTTPS yet, give Caddy a minute to obtain"
echo " a Let's Encrypt certificate, then check: docker compose logs -f app"
echo "================================================================"
