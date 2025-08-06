#!/bin/bash
set -e

# Color codes for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${YELLOW}Starting Laravel application setup...${NC}"

# Wait for database to be ready with timeout
echo -e "${YELLOW}Waiting for database...${NC}"
timeout=60
while ! nc -z mysql 3306; do
    timeout=$((timeout - 1))
    if [ $timeout -le 0 ]; then
        echo -e "${RED}Database connection timeout!${NC}"
        exit 1
    fi
    sleep 1
done
echo -e "${GREEN}Database is ready!${NC}"

# Navigate to application directory
cd /var/www/html

# Check if .env file exists, if not create from example
if [ ! -f .env ]; then
    echo -e "${YELLOW}Creating .env file...${NC}"
    cp .env.example .env
fi

# Generate app key if not exists
if ! grep -q "APP_KEY=base64:" .env; then
    echo -e "${YELLOW}Generating application key...${NC}"
    php artisan key:generate --force
fi

# Clear and cache configurations
echo -e "${YELLOW}Optimizing Laravel...${NC}"
php artisan config:clear
php artisan config:cache
php artisan route:clear
php artisan route:cache
php artisan view:clear
php artisan view:cache

# Run migrations
echo -e "${YELLOW}Running migrations...${NC}"
php artisan migrate --force

# Run seeders (only if specified)
if [ "${RUN_SEEDERS:-false}" = "true" ]; then
    echo -e "${YELLOW}Running seeders...${NC}"
    php artisan db:seed --force
fi

# Set proper permissions
echo -e "${YELLOW}Setting permissions...${NC}"
chown -R www-data:www-data /var/www/html
find /var/www/html -type f -exec chmod 644 {} \;
find /var/www/html -type d -exec chmod 755 {} \;
chmod -R 775 /var/www/html/storage
chmod -R 775 /var/www/html/bootstrap/cache

echo -e "${GREEN}Laravel setup completed successfully!${NC}"

# Start supervisor
echo -e "${YELLOW}Starting services...${NC}"
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf