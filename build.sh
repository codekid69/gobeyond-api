#!/usr/bin/env bash

set -e

echo "Running Composer install..."
composer install --no-dev --optimize-autoloader --no-interaction

echo "Clearing caches..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "Running migrations..."
php artisan migrate --force

echo "Deployment build complete!"
