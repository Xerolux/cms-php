#!/bin/sh
set -e

# Install dependencies if vendor directory is missing (handling volume mount overlay)
if [ ! -d "vendor" ]; then
    echo "Vendor directory not found. Installing dependencies..."
    composer install --no-interaction --optimize-autoloader --no-dev
fi

# Run migrations (optional, or manual)
# echo "Running migrations..."
# php artisan migrate --force

# Cache config and routes for production
# echo "Caching config..."
# php artisan config:cache
# php artisan route:cache
# php artisan view:cache

# Execute the main command
exec "$@"
