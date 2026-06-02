#!/usr/bin/env bash
set -e

cd /var/www

if [ ! -f .env ]; then
    cp .env.example .env
    php artisan key:generate --force
fi

php artisan storage:link --quiet || true

if [ "${MIGRATE_ON_BOOT:-false}" = "true" ]; then
    php artisan migrate --force
fi

php artisan config:clear
php artisan route:clear
php artisan view:clear

exec "$@"
