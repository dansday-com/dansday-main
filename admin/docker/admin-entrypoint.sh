#!/bin/sh
set -e

mkdir -p public/uploads/img && cp -n /tmp/image_default.png public/uploads/admin/image_default.png || true

mkdir -p storage/app/media/linkedin/documents storage/app/media/linkedin/videos || true

role="${CONTAINER_ROLE:-all}"

if [ "$role" != "scheduler" ]; then
	php artisan optimize:clear
	php artisan migrate --force
fi

if [ "$role" = "scheduler" ]; then
	exec /bin/sh -c 'exec php artisan linkedin:work --idle=${LINKEDIN_WORKER_IDLE:-60} --max=${LINKEDIN_WORKER_MAX:-5}'
fi

if [ "$role" = "web" ]; then
	exec frankenphp run --config /etc/caddy/Caddyfile --adapter caddyfile
fi

exec /usr/bin/supervisord -c /etc/supervisord.conf
