#!/bin/bash

# Wait for database to be ready
echo "Waiting for database..."
while ! nc -z mysql 3306; do
    sleep 1
done
echo "Database is ready!"

# Run Laravel setup commands
cd /var/www/html

# Generate app key if not exists
if [ ! -f .env ]; then
    cp .env.example .env
fi

php artisan key:generate --force

# Clear and cache config
php artisan config:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Run migrations and seeders
php artisan migrate --force
php artisan db:seed --force

# Set proper permissions
chown -R www-data:www-data /var/www/html
chmod -R 755 /var/www/html/storage
chmod -R 755 /var/www/html/bootstrap/cache

# Start supervisor
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf