#!/usr/bin/env bash

set -e

echo "Running Composer install..."
composer install --no-dev --optimize-autoloader --no-interaction

echo "Creating storage framework directories..."
mkdir -p storage/framework/sessions
mkdir -p storage/framework/views
mkdir -p storage/framework/cache
chmod -R 775 storage/framework

echo "Clearing caches..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "Running migrations..."
php artisan migrate --force

echo "Deployment build complete!"
