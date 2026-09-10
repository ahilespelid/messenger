#!/bin/sh
set -e

wait_for_db() {
    echo "Waiting for the database..."
    tries=0
    until php -r '
        try {
            new PDO("mysql:host=".getenv("DB_HOST").";port=".getenv("DB_PORT"), getenv("DB_USERNAME"), getenv("DB_PASSWORD"));
            exit(0);
        } catch (Throwable $e) {
            exit(1);
        }
    '; do
        tries=$((tries + 1))
        if [ "$tries" -ge 60 ]; then
            echo "Database did not become available in time." >&2
            exit 1
        fi
        sleep 2
    done
    echo "Database is up."
}

case "$1" in
  app)
    wait_for_db
    php artisan migrate --force
    php artisan db:seed --force
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    if [ "${APP_HTTPS:-true}" = "false" ]; then
        exec php artisan octane:start \
            --server=frankenphp \
            --host=0.0.0.0 \
            --port=80 \
            --admin-port=2019 \
            --caddyfile=docker/Caddyfile \
            --workers="${OCTANE_WORKERS:-auto}" \
            --max-requests="${OCTANE_MAX_REQUESTS:-500}"
    else
        exec php artisan octane:start \
            --server=frankenphp \
            --host="${APP_DOMAIN:-localhost}" \
            --port=443 \
            --admin-port=2019 \
            --caddyfile=docker/Caddyfile \
            --workers="${OCTANE_WORKERS:-auto}" \
            --max-requests="${OCTANE_MAX_REQUESTS:-500}" \
            --https \
            --http-redirect
    fi
    ;;
  reverb)
    wait_for_db
    if [ "${REVERB_DEBUG:-false}" = "true" ]; then
        exec php artisan reverb:start --host=0.0.0.0 --port=8080 --debug
    else
        exec php artisan reverb:start --host=0.0.0.0 --port=8080
    fi
    ;;
  queue)
    wait_for_db
    exec php artisan queue:work --tries=3 --backoff=5 --sleep=1 --max-time=3600
    ;;
  scheduler)
    wait_for_db
    exec php artisan schedule:work
    ;;
  *)
    exec "$@"
    ;;
esac
