#!/bin/sh
set -e

mkdir -p public/uploads/img && cp -n /tmp/image_default.png public/uploads/img/image_default.png || true

role="${CONTAINER_ROLE:-all}"

if [ "$role" != "scheduler" ]; then
	php artisan optimize:clear
	php artisan migrate --force
fi

if [ "$role" = "scheduler" ]; then
	exec /bin/sh -c 'exec php artisan embeddings:work --sleep=${EMBEDDING_WORKER_SLEEP:-1} --idle=${EMBEDDING_WORKER_IDLE:-5} --prune-seconds=${EMBEDDING_WORKER_PRUNE_SECONDS:-300}'
fi

if [ "$role" = "web" ]; then
	exec frankenphp run --config /etc/caddy/Caddyfile --adapter caddyfile
fi

exec /usr/bin/supervisord -c /etc/supervisord.conf
