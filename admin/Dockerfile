FROM php:8.5-cli-alpine
RUN apk add --no-cache git unzip libzip-dev libpng-dev libxml2-dev oniguruma-dev nodejs npm
RUN docker-php-ext-install pdo_mysql zip gd xml mbstring
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

WORKDIR /app

# Copy application code (vendor will be created on host via `make install`)
COPY . .

# Build static admin assets once in the image
RUN cp -r assets/fonts assets/js assets/bootstrap public/assets/ \
	&& cp assets/admin/css/*.css public/assets/admin/css/ 2>/dev/null || true \
	&& cp -r assets/admin/js public/assets/admin/ 2>/dev/null || true \
	&& cp -r assets/admin/img public/assets/admin/ 2>/dev/null || true

# Ensure Laravel runtime directories exist (also re-created on container start)
RUN mkdir -p bootstrap/cache storage/framework/cache storage/framework/sessions storage/framework/views storage/logs \
	&& chmod -R 775 bootstrap/cache storage

EXPOSE 8080

# Runtime: assume deps are already installed by `make install`.
# Just ensure dirs/assets exist (for mounted volume), run migrations, then start server.
CMD ["sh", "-c", "mkdir -p public/assets/admin/css public/assets/admin/js public/assets/admin/img bootstrap/cache storage/framework/cache storage/framework/sessions storage/framework/views storage/logs && chmod -R 775 bootstrap/cache storage && cp -r assets/fonts assets/js assets/bootstrap public/assets/ 2>/dev/null || true && cp assets/admin/css/*.css public/assets/admin/css/ 2>/dev/null || true && cp -r assets/admin/js public/assets/admin/ 2>/dev/null || true && cp -r assets/admin/img public/assets/admin/ 2>/dev/null || true && for i in 1 2 3 4 5 6 7 8 9 10; do php artisan migrate --force --no-interaction && break; sleep 3; done && php artisan config:clear && exec php artisan serve --host=0.0.0.0 --port=8080"]
