#!/usr/bin/env bash
set -e

echo "==> composer install"
docker compose exec -T php composer install

echo "==> app key"
docker compose exec -T php php artisan key:generate --force

echo "==> migrate + seed"
docker compose exec -T php php artisan migrate --seed --force

echo "==> storage link"
docker compose exec -T php php artisan storage:link

echo "==> build assets"
docker compose run --rm node sh -c "npm install && npm run build"

echo ""
echo "Done."
echo "  App:        http://localhost:8000/admin"
echo "  phpMyAdmin: http://localhost:8081  (root / secret)"
echo "  OnlyOffice: http://localhost:8082"
