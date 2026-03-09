#!/bin/sh
set -e
mkdir -p bootstrap/cache storage/framework/cache storage/framework/sessions storage/framework/views storage/logs public/uploads
chmod -R 775 bootstrap/cache storage public/uploads

# Ensure .env exists (e.g. from container env or .env.example)
test -f .env || cp .env.example .env

# Sync injected env into .env so Laravel picks them up (Coolify sets these)
if [ -f .env ]; then
  [ -n "$APP_URL" ]        && sed -i "s|^APP_URL=.*|APP_URL=$APP_URL|" .env
  [ -n "$DB_HOST" ]        && sed -i "s|^DB_HOST=.*|DB_HOST=$DB_HOST|" .env
  [ -n "$DB_PORT" ]        && sed -i "s|^DB_PORT=.*|DB_PORT=$DB_PORT|" .env
  [ -n "$DB_DATABASE" ]    && sed -i "s|^DB_DATABASE=.*|DB_DATABASE=$DB_DATABASE|" .env
  [ -n "$DB_USERNAME" ]    && sed -i "s|^DB_USERNAME=.*|DB_USERNAME=$DB_USERNAME|" .env
  [ -n "$DB_PASSWORD" ]    && sed -i "s|^DB_PASSWORD=.*|DB_PASSWORD=$DB_PASSWORD|" .env
  [ -n "$REDIS_HOST" ]     && sed -i "s|^REDIS_HOST=.*|REDIS_HOST=$REDIS_HOST|" .env
  [ -n "$REDIS_PORT" ]     && sed -i "s|^REDIS_PORT=.*|REDIS_PORT=$REDIS_PORT|" .env
  [ -n "$REDIS_PASSWORD" ] && sed -i "s|^REDIS_PASSWORD=.*|REDIS_PASSWORD=$REDIS_PASSWORD|" .env
fi

php artisan config:clear
exec php artisan serve --host=0.0.0.0 --port="${PORT:-80}"
