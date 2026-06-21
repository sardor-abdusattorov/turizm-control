#!/bin/sh
set -e

# The bind-mounted storage/ is usually owned by the host user (often root),
# while the php-fpm workers run as www-data — so Laravel can't write logs,
# caches or the documents it generates from templates. Make the writable
# paths exist and be owned by www-data on every container start. Best-effort:
# a harmless no-op on bind mounts that ignore chown (e.g. Docker Desktop).
if [ "$(id -u)" = "0" ]; then
    mkdir -p \
        storage/app/public \
        storage/app/private \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache

    chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
    chmod -R ug+rwX storage bootstrap/cache 2>/dev/null || true
fi

exec "$@"
