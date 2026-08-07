FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --optimize-autoloader

FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json ./
COPY vite.config.js ./
COPY resources ./resources
RUN npm install --no-audit --ignore-scripts=false && npm run build

FROM php:8.3-cli
WORKDIR /app
COPY --from=vendor /app/vendor ./vendor
COPY --from=assets /app/public/build ./public/build
COPY . .
RUN mkdir -p storage/framework/sessions storage/framework/views storage/framework/cache storage/logs database bootstrap/cache
EXPOSE 8080
CMD ["sh", "-c", "php artisan key:generate --force && php artisan migrate --force && php artisan serve --host=0.0.0.0 --port=$PORT"]
