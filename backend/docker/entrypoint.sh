#!/usr/bin/env bash
set -e

cd /var/www

# Remove stale bootstrap caches BEFORE any artisan command. A leftover
# packages.php/services.php from a previous checkout can reference a
# ServiceProvider that isn't installed yet and crash the framework on boot —
# and because every artisan call boots the framework first, it can't clear
# itself out of the loop. Deleting the files forces fresh package discovery.
rm -f bootstrap/cache/packages.php bootstrap/cache/services.php bootstrap/cache/config.php

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
