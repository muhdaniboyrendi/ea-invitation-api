#!/bin/bash
# ea-invitation-api/docker/entrypoint.sh

set -e

# Switch to root to perform system operations
USER root

# Start PHP-FPM in background
php-fpm -D

# Start Nginx
nginx -g "daemon off;" &

# Switch back to www-data
USER www-data

# Wait for database to be ready (optional)
# php artisan wait-for-db

# Run Laravel commands
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Create storage link if not exists
if [ ! -L "/var/www/public/storage" ]; then
    php artisan storage:link
fi

# Run migrations (optional, be careful in production)
# php artisan migrate --force

# Start supervisor for queue workers
USER root
supervisord -c /etc/supervisor/supervisord.conf

# Keep container running
wait