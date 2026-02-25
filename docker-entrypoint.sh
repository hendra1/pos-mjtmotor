#!/bin/bash
set -e

echo "==> Starting NexoPOS entrypoint..."

# Copy .env if it doesn't exist inside the container working dir
if [ ! -f /var/www/html/.env ]; then
    echo "==> .env not found, copying from .env.example..."
    cp /var/www/html/.env.example /var/www/html/.env
fi

# Ensure APP_KEY is set
if [ -z "$(grep -E '^APP_KEY=.+' /var/www/html/.env)" ]; then
    echo "==> Generating APP_KEY..."
    php artisan key:generate --ansi --force
fi

# Create storage link if not exists
echo "==> Setting up storage link..."
php artisan storage:link --force 2>/dev/null || true

# Clear caches FIRST so that generated files are also permission-fixed
echo "==> Clearing caches..."
php artisan config:clear 2>/dev/null || true
php artisan cache:clear 2>/dev/null || true
php artisan view:clear 2>/dev/null || true

# Fix permissions
echo "==> Fixing permissions..."
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/.env
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

echo "==> NexoPOS is ready. Starting PHP-FPM..."
exec php-fpm
