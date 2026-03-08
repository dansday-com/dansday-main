#!/bin/sh
set -e
mkdir -p bootstrap/cache storage/framework/cache storage/framework/sessions storage/framework/views storage/logs public/uploads
chmod -R 775 bootstrap/cache storage public/uploads

# Ensure .env exists (e.g. from container env or .env.example)
test -f .env || cp .env.example .env

# Sync injected env into .env so Laravel picks them up
if [ -f .env ]; then
  [ -n "$DB_HOST" ]       && sed -i "s|^DB_HOST=.*|DB_HOST=$DB_HOST|" .env
  [ -n "$DB_USERNAME" ]   && sed -i "s|^DB_USERNAME=.*|DB_USERNAME=$DB_USERNAME|" .env
  [ -n "$DB_PASSWORD" ]   && sed -i "s|^DB_PASSWORD=.*|DB_PASSWORD=$DB_PASSWORD|" .env
  [ -n "$APP_URL" ]       && sed -i "s|^APP_URL=.*|APP_URL=$APP_URL|" .env
  [ -n "$REDIS_HOST" ]    && sed -i "s|^REDIS_HOST=.*|REDIS_HOST=$REDIS_HOST|" .env
fi

php artisan config:clear
exec php artisan serve --host=0.0.0.0 --port=80
