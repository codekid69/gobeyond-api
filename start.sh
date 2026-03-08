#!/bin/sh
set -e

# Set DB path explicitly to the project database dir
export DB_DATABASE="/var/www/database/database.sqlite"

# Ensure storage directories exist at runtime (safety net)
mkdir -p /var/www/storage/framework/sessions
mkdir -p /var/www/storage/framework/views
mkdir -p /var/www/storage/framework/cache/data
mkdir -p /var/www/storage/logs
chmod -R 775 /var/www/storage

# Run artisan optimization (env vars available NOW at runtime, not at build time)
php /var/www/artisan config:clear
php /var/www/artisan config:cache
php /var/www/artisan route:cache

# Run migrations (creates tables if they don't exist)
php /var/www/artisan migrate --force
