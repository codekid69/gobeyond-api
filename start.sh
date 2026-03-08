#!/usr/bin/env bash
set -e

# Set DB path explicitly to the project database dir
export DB_DATABASE="/var/www/html/database/database.sqlite"

# Run migrations (creates tables if they don't exist)
php artisan migrate --force

# Start PHP-FPM and Nginx (handled by supervisor/render entrypoint)
