#!/bin/sh
set -e

# Force SQLite - override any MySQL env vars that may be set in Render dashboard
export DB_CONNECTION=sqlite
export DB_DATABASE=/var/www/database/database.sqlite

# Ensure storage directories exist at runtime (safety net)
mkdir -p /var/www/storage/framework/sessions
mkdir -p /var/www/storage/framework/views
mkdir -p /var/www/storage/framework/cache/data
mkdir -p /var/www/storage/logs
chmod -R 775 /var/www/storage

# Fix SQLite permissions so PHP-FPM (www-data) can write to it
# Note: SQLite needs write access to the *directory* it sits in to create lock/journal files
chown -R www-data:www-data /var/www/database
chmod -R 775 /var/www/database

# Run artisan optimization (env vars available NOW at runtime, not at build time)
php /var/www/artisan config:clear
php /var/www/artisan config:cache
php /var/www/artisan route:cache

# Run migrations (creates tables if they don't exist)
php /var/www/artisan migrate --force
