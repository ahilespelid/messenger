#!/usr/bin/env bash
#
# Quick deploy for a clean Debian 13 server.
#
# Usage (run from inside the cloned repo, as root):
#   git clone https://github.com/ahilespelid/messenger.git
#   cd messenger
#   sudo ./deploy.sh <domain-or-ip> [operator-email]
#
# Examples:
#   sudo ./deploy.sh 203.0.113.10                     # no domain yet — plain HTTP
#   sudo ./deploy.sh chat.example.com admin@example.com  # real domain — auto HTTPS
#
# Pass a bare IP address when you don't have a domain pointed at this server
# yet: the site comes up over plain HTTP on that IP (browsers can't get a
# trusted certificate for a raw IP, so HTTPS is skipped). Once you have a
# domain/subdomain, point its A record at this server and re-run this script
# with the domain instead — see "Switching from IP to a domain later" in
# README.md; it flips a couple of .env values and Caddy takes care of the
# certificate automatically.
#
# Re-running this script on an existing install just pulls the latest code,
# rebuilds, and restarts — it never overwrites an existing .env.

set -euo pipefail

TARGET="${1:-}"
OPERATOR_EMAIL="${2:-admin@example.com}"

if [[ -z "$TARGET" ]]; then
    echo "Usage: sudo $0 <domain-or-ip> [operator-email]" >&2
    echo "Examples:" >&2
    echo "  sudo $0 203.0.113.10" >&2
    echo "  sudo $0 chat.example.com admin@example.com" >&2
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

# A bare IPv4 address means "no domain yet" -> plain HTTP, no Let's Encrypt.
if [[ "$TARGET" =~ ^([0-9]{1,3}\.){3}[0-9]{1,3}$ ]]; then
    MODE="ip"
    APP_HTTPS_VALUE="false"
    SCHEME="http"
    REVERB_PORT_VALUE="80"
else
    MODE="domain"
    APP_HTTPS_VALUE="true"
    SCHEME="https"
    REVERB_PORT_VALUE="443"
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
    echo "    ufw is not active on this host — make sure 80/tcp is open"
    echo "    (plus 443/tcp and 443/udp once you switch to a domain) in your"
    echo "    cloud provider's firewall/security group."
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

    sed -i "s|^APP_KEY=.*|APP_KEY=${APP_KEY}|" .env
    sed -i "s|^DB_PASSWORD=.*|DB_PASSWORD=${DB_PASSWORD}|" .env
    sed -i "s|^DB_ROOT_PASSWORD=.*|DB_ROOT_PASSWORD=${DB_ROOT_PASSWORD}|" .env
    sed -i "s|^REVERB_APP_KEY=.*|REVERB_APP_KEY=${REVERB_APP_KEY}|" .env
    sed -i "s|^REVERB_APP_SECRET=.*|REVERB_APP_SECRET=${REVERB_APP_SECRET}|" .env
    sed -i "s|^OPERATOR_PASSWORD=.*|OPERATOR_PASSWORD=${OPERATOR_PASSWORD}|" .env

    NEW_ENV=1
else
    echo "    .env already exists — keeping existing secrets, syncing domain/scheme"
    NEW_ENV=0
fi

# These are safe to re-sync on every run (e.g. switching from a bare IP to a
# domain later never touches secrets, only where/how the site is reachable).
sed -i "s|^APP_ENV=.*|APP_ENV=production|" .env
sed -i "s|^APP_DEBUG=.*|APP_DEBUG=false|" .env
sed -i "s|^APP_URL=.*|APP_URL=${SCHEME}://${TARGET}|" .env
sed -i "s|^APP_DOMAIN=.*|APP_DOMAIN=${TARGET}|" .env
sed -i "s|^APP_HTTPS=.*|APP_HTTPS=${APP_HTTPS_VALUE}|" .env
sed -i "s|^REVERB_CLIENT_PORT=.*|REVERB_CLIENT_PORT=${REVERB_PORT_VALUE}|" .env
sed -i "s|^REVERB_CLIENT_SCHEME=.*|REVERB_CLIENT_SCHEME=${SCHEME}|" .env
sed -i "s|^VAPID_SUBJECT=.*|VAPID_SUBJECT=mailto:${OPERATOR_EMAIL}|" .env
sed -i "s|^OPERATOR_EMAIL=.*|OPERATOR_EMAIL=${OPERATOR_EMAIL}|" .env

echo "==> Building images (this can take a few minutes the first time)"
docker compose build

echo "==> Starting services"
docker compose up -d --force-recreate

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

# Always write the current credentials to a plain file — copy/pasting from
# here avoids browser autofill silently substituting an old saved password,
# which is exactly what happened testing this.
CURRENT_OPERATOR_EMAIL="$(grep '^OPERATOR_EMAIL=' .env | cut -d= -f2-)"
CURRENT_OPERATOR_PASSWORD="$(grep '^OPERATOR_PASSWORD=' .env | cut -d= -f2-)"

cat > credentials.txt <<EOF
Messenger — operator login
Generated: $(date -u +"%Y-%m-%d %H:%M:%S UTC")

URL:      ${SCHEME}://${TARGET}/login
Email:    ${CURRENT_OPERATOR_EMAIL}
Password: ${CURRENT_OPERATOR_PASSWORD}

This file is regenerated on every deploy.sh run and is not tracked by git.
EOF
chmod 600 credentials.txt

echo ""
echo "================================================================"
echo " Deployed."
echo ""
echo "   Client widget:   ${SCHEME}://${TARGET}"
echo "   Operator login:  ${SCHEME}://${TARGET}/login"
echo "   Operator email:    ${CURRENT_OPERATOR_EMAIL}"
echo "   Operator password: ${CURRENT_OPERATOR_PASSWORD}"
echo ""
echo "   Also saved to: $(pwd)/credentials.txt"
if [[ "$MODE" == "ip" ]]; then
    echo ""
    echo " Running over plain HTTP on the IP — no domain yet, so no HTTPS."
    echo " Once you point a domain/subdomain at this server, re-run:"
    echo "     sudo $0 messenger.your-domain.ru ${OPERATOR_EMAIL}"
    echo " after editing .env as described in \"Switching from IP to a domain"
    echo " later\" in README.md — Caddy will fetch a Let's Encrypt certificate"
    echo " automatically."
else
    echo ""
    echo " If the site doesn't load over HTTPS yet, give Caddy a minute to obtain"
    echo " a Let's Encrypt certificate, then check: docker compose logs -f app"
fi
echo "================================================================"
