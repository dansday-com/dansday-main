FROM php:8.5-cli-alpine
RUN apk add --no-cache git unzip libzip-dev libpng-dev libxml2-dev oniguruma-dev nodejs npm
RUN docker-php-ext-install pdo_mysql zip gd xml mbstring
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

WORKDIR /app

# Code and vendor come from host/volume at runtime; COPY only needed so image builds.
COPY . .

EXPOSE 8080

# Runtime: assume deps already installed by `make install`. Sync Docker env into .env so Laravel uses mysql host.
CMD ["sh", "-c", "mkdir -p public/assets/admin/css public/assets/admin/js public/assets/admin/img bootstrap/cache storage/framework/cache storage/framework/sessions storage/framework/views storage/logs && chmod -R 775 bootstrap/cache storage && cp -r assets/fonts assets/js assets/bootstrap public/assets/ 2>/dev/null || true && cp assets/admin/css/*.css public/assets/admin/css/ 2>/dev/null || true && cp -r assets/admin/js public/assets/admin/ 2>/dev/null || true && cp -r assets/admin/img public/assets/admin/ 2>/dev/null || true && (test -f .env && sed -i \"s|^DB_HOST=.*|DB_HOST=${DB_HOST:-mysql}|\" .env && sed -i \"s|^DB_USERNAME=.*|DB_USERNAME=${DB_USERNAME:-dansday_main}|\" .env && sed -i \"s|^DB_PASSWORD=.*|DB_PASSWORD=${DB_PASSWORD:-password}|\" .env && sed -i \"s|^APP_URL=.*|APP_URL=${APP_URL:-http://localhost:8080}|\" .env && sed -i \"s|^REDIS_HOST=.*|REDIS_HOST=${REDIS_HOST:-redis}|\" .env; true) && php artisan config:clear && exec php artisan serve --host=0.0.0.0 --port=8080"]
