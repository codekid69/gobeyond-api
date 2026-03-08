FROM php:8.2-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    git \
    curl \
    libpng-dev \
    libxml2-dev \
    icu-dev \
    zip \
    unzip \
    nginx \
    supervisor \
    nodejs \
    npm

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql exif pcntl bcmath gd intl opcache

# Get latest Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www

# Copy existing application directory contents
COPY . /var/www

# Copy nginx config
COPY docker/nginx.conf /etc/nginx/http.d/default.conf

# Copy supervisord config
COPY docker/supervisord.conf /etc/supervisord.conf

# Install dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Create required Laravel framework directories (these are gitignored but MUST exist)
RUN mkdir -p /var/www/storage/framework/sessions \
    && mkdir -p /var/www/storage/framework/views \
    && mkdir -p /var/www/storage/framework/cache/data \
    && mkdir -p /var/www/storage/logs \
    && mkdir -p /var/www/bootstrap/cache \
    && chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache \
    && chmod -R 775 /var/www/storage /var/www/bootstrap/cache

# Make start.sh executable
RUN chmod +x /var/www/start.sh

# Expose port (Render automatically uses PORT env var, we'll configure nginx to listen to 80 and let Render route to it)
EXPOSE 80

# Run startup script (artisan cache/migrate) then hand off to supervisor
CMD ["/bin/sh", "-c", "/var/www/start.sh && /usr/bin/supervisord -c /etc/supervisord.conf"]
