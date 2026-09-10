# syntax=docker/dockerfile:1

########################################
# 1. Build frontend assets (Vite/Tailwind)
########################################
FROM node:20-alpine AS frontend

WORKDIR /app

COPY package.json ./
RUN npm install --no-audit --no-fund

COPY resources ./resources
COPY vite.config.js ./
COPY public ./public

RUN npm run build

########################################
# 2. Install PHP dependencies
########################################
FROM dunglas/frankenphp:php8.4 AS vendor

WORKDIR /app

RUN install-php-extensions pdo_mysql bcmath pcntl intl opcache zip >/dev/null

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY . .
COPY --from=frontend /app/public/build ./public/build

RUN composer dump-autoload --optimize --no-dev

########################################
# 3. Runtime image
########################################
FROM dunglas/frankenphp:php8.4 AS runtime

RUN install-php-extensions pdo_mysql bcmath pcntl intl opcache >/dev/null \
    && rm -rf /var/lib/apt/lists/* /tmp/*

WORKDIR /app

COPY --from=vendor /app /app
COPY docker/Caddyfile /app/docker/Caddyfile
COPY docker/entrypoint.sh /entrypoint.sh

RUN chmod +x /entrypoint.sh \
    && mkdir -p storage/framework/{cache,sessions,testing,views} storage/logs bootstrap/cache \
    && chown -R www-data:www-data /app storage bootstrap/cache

ENV SERVER_NAME=":80"
ENV APP_RUNTIME_ENV=production
ENV OCTANE_SERVER=frankenphp

EXPOSE 80 443 443/udp

ENTRYPOINT ["/entrypoint.sh"]
CMD ["app"]
