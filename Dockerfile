FROM composer:2 AS vendor
WORKDIR /app
COPY . .
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --optimize-autoloader

FROM node:22-alpine AS assets
WORKDIR /app
COPY . .
RUN npm install --no-audit --ignore-scripts=false && npm run build

FROM php:8.4-cli
RUN apt-get update && apt-get install -y --no-install-recommends libpq-dev \
    && docker-php-ext-install pdo_pgsql \
    && rm -rf /var/lib/apt/lists/*
WORKDIR /app
COPY --from=vendor /app/vendor ./vendor
COPY --from=assets /app/public/build ./public/build
COPY . .
RUN mkdir -p storage/framework/sessions storage/framework/views storage/framework/cache storage/logs database bootstrap/cache
EXPOSE 8080
CMD ["sh", "-c", "[ -f .env ] || cp .env.example .env; php artisan key:generate --force && php artisan migrate --force && php artisan serve --host=0.0.0.0 --port=$PORT"]
