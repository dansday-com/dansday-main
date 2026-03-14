FROM node:alpine AS frontend
WORKDIR /app
COPY package.json package-lock.json* ./
RUN if [ -f package-lock.json ]; then npm ci; else npm install; fi
COPY . .
RUN npm run build

FROM php:8.5-cli-alpine
RUN apk add --no-cache git unzip libzip-dev libpng-dev libxml2-dev oniguruma-dev
RUN docker-php-ext-install pdo_mysql zip gd xml mbstring
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install redis opentelemetry \
    && docker-php-ext-enable redis opentelemetry \
    && apk del .build-deps
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

WORKDIR /app

COPY composer.json ./

RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

COPY . .
RUN cp .env.example .env && php artisan package:discover --ansi

RUN mkdir -p public/assets public/uploads \
    && cp -r assets/* public/assets/

COPY --from=frontend /app/public/assets/admin/css/admin.css ./public/assets/admin/css/

EXPOSE 80

CMD sh -c "php artisan optimize:clear && php artisan migrate --force && exec php artisan serve --host=0.0.0.0 --port=${PORT:-80}"
