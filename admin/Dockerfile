# Production: composer + frontend build in image.
# MySQL/Redis: set DB_HOST, REDIS_HOST via environment.

# Stage 1: build frontend (Vite)
FROM node:alpine AS frontend
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
RUN npm run build

# Stage 2: PHP app
FROM php:8.5-cli-alpine
RUN apk add --no-cache git unzip libzip-dev libpng-dev libxml2-dev oniguruma-dev
RUN docker-php-ext-install pdo_mysql zip gd xml mbstring
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

WORKDIR /app

COPY composer.json composer.lock* ./
RUN composer install --no-dev --optimize-autoloader

COPY . .

# Built Vite output (overwrites any placeholder)
COPY --from=frontend /app/public/assets/admin/css ./public/assets/admin/css

# Static assets into public
RUN mkdir -p public/assets public/assets/admin/js public/assets/admin/img public/uploads bootstrap/cache storage/framework/cache storage/framework/sessions storage/framework/views storage/logs \
    && cp -r assets/fonts assets/js assets/bootstrap public/assets/ 2>/dev/null || true \
    && cp -r assets/admin/js assets/admin/img public/assets/admin/ 2>/dev/null || true \
    && chmod -R 775 bootstrap/cache storage public/uploads

COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 80

ENTRYPOINT ["docker-entrypoint.sh"]
