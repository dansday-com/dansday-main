# ── Stage 1: Frontend Build ──────────────────────────────────────
FROM node:25.8.1-alpine AS frontend

WORKDIR /app

COPY package.json ./
RUN npm install

COPY . .
RUN npm run build

# ── Stage 2: PHP Dependencies ────────────────────────────────────
FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock* ./

RUN composer install \
    --no-dev \
    --no-interaction \
    --no-scripts \
    --optimize-autoloader \
    --prefer-dist

COPY . .
RUN composer run-script post-autoload-dump || true

# ── Stage 3: Runtime ─────────────────────────────────────────────
FROM dunglas/frankenphp:1.12.1-php8.5-alpine AS runner

RUN apk add --no-cache \
    curl \
    libzip-dev \
    libpng-dev \
    libxml2-dev \
    oniguruma-dev \
    linux-headers

RUN install-php-extensions \
    pdo_mysql \
    zip \
    gd \
    xml \
    mbstring \
    redis \
    opentelemetry \
    opcache

WORKDIR /app

COPY --from=vendor /app/vendor ./vendor
COPY --from=vendor /app ./

RUN mkdir -p ./public/build ./public/assets
COPY --from=frontend /app/public ./public

RUN if [ -d assets ]; then cp -r assets/* public/assets/; fi

# Write Caddyfile inline
RUN mkdir -p /etc/caddy && cat > /etc/caddy/Caddyfile <<'CADDYEOF'
{
    frankenphp
    order php_server before file_server
}

:2222 {
    root * /app/public
    encode gzip

    @fonts {
        path_regexp \.(woff2?|ttf|eot|otf)$
    }

    header @fonts {
        Access-Control-Allow-Origin "*"
        Access-Control-Allow-Methods "GET, OPTIONS"
        Access-Control-Allow-Headers "*"
        Cache-Control "public, max-age=31536000, immutable"
    }

    @static {
        path_regexp \.(css|js|webp|png|jpg|jpeg|gif|svg|ico|webmanifest|json)$
    }

    header @static {
        Access-Control-Allow-Origin "*"
        Cache-Control "public, max-age=31536000, immutable"
    }

    php_server {
        worker /app/public/index.php
        read_timeout 300s
        write_timeout 300s
    }

    file_server
}
CADDYEOF

EXPOSE 2222

ENV APP_ENV=production
ENV LOG_CHANNEL=stderr

CMD ["sh", "-c", "php artisan optimize:clear && php artisan migrate --force && frankenphp run --config /etc/caddy/Caddyfile --adapter caddyfile"]
