FROM node:25.8.1-alpine AS frontend

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY . .
RUN npm run build

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

FROM dunglas/frankenphp:1.12.1-php8.5-alpine AS runner

RUN apk add --no-cache \
    curl \
    libzip-dev \
    libpng-dev \
    libxml2-dev \
    oniguruma-dev \
    linux-headers \
    supervisor

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

RUN mkdir -p ./public/assets
COPY --from=frontend /app/public/assets ./public/assets

RUN if [ -d assets ]; then cp -r assets/* public/assets/; fi

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
    }

    file_server
}
CADDYEOF

RUN printf "memory_limit=512M\nmax_execution_time=600\n" > /usr/local/etc/php/conf.d/custom.ini

COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/admin-entrypoint.sh /usr/local/bin/admin-entrypoint.sh
RUN chmod +x /usr/local/bin/admin-entrypoint.sh

EXPOSE 2222

ENV APP_ENV=production
ENV LOG_CHANNEL=stderr
ENV EMBEDDING_WORKER_SLEEP=1
ENV EMBEDDING_WORKER_IDLE=5
ENV EMBEDDING_WORKER_PRUNE_SECONDS=300

RUN cp public/uploads/img/image_default.png /tmp/image_default.png

CMD ["/usr/local/bin/admin-entrypoint.sh"]
