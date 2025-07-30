#!/bin/bash

# Deploy script for EAInvitation Laravel Application
# Usage: ./deploy.sh

set -e

echo "🚀 Starting deployment for EAInvitationAPI..."

# Configuration
APP_DIR="/opt/eainvitation"
BACKUP_DIR="/opt/backups/eainvitation"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)

# Create directories if they don't exist
sudo mkdir -p $APP_DIR
sudo mkdir -p $BACKUP_DIR
sudo mkdir -p $APP_DIR/docker/ssl

echo "📁 Directories created/verified"

# Backup current deployment (if exists)
if [ -d "$APP_DIR/storage" ]; then
    echo "📦 Creating backup..."
    sudo tar -czf $BACKUP_DIR/backup_$TIMESTAMP.tar.gz -C $APP_DIR .
    echo "✅ Backup created at $BACKUP_DIR/backup_$TIMESTAMP.tar.gz"
fi

# Stop existing containers
echo "🛑 Stopping existing containers..."
cd $APP_DIR
sudo docker-compose down --remove-orphans || true

# Pull latest code (if using git directly)
if [ -d ".git" ]; then
    echo "📥 Pulling latest code..."
    git pull origin main
fi

# Generate APP_KEY if not exists in .env
if [ -f ".env" ] && ! grep -q "APP_KEY=base64:" .env; then
    echo "🔑 Generating APP_KEY..."
    APP_KEY=$(docker run --rm php:8.3-cli php -r "echo 'base64:' . base64_encode(random_bytes(32));")
    sed -i "s/APP_KEY=.*/APP_KEY=$APP_KEY/" .env
fi

# Build and start containers
echo "🏗️ Building and starting containers..."
sudo docker-compose up -d --build

# Wait for services to be ready
echo "⏳ Waiting for services to be ready..."
sleep 30

# Run Laravel commands
echo "🔧 Running Laravel optimization commands..."
sudo docker-compose exec -T app php artisan config:cache
sudo docker-compose exec -T app php artisan route:cache
sudo docker-compose exec -T app php artisan view:cache

# Run database migrations
echo "🗄️ Running database migrations..."
sudo docker-compose exec -T app php artisan migrate --force

# Run database seeding
echo "🌱 Running database seeding..."
sudo docker-compose exec -T app php artisan db:seed --force

# Clear and optimize
echo "🧹 Clearing and optimizing..."
sudo docker-compose exec -T app php artisan optimize:clear
sudo docker-compose exec -T app php artisan optimize

# Set proper permissions
echo "🔐 Setting proper permissions..."
sudo docker-compose exec -T app chown -R www-data:www-data /var/www/html/storage
sudo docker-compose exec -T app chown -R www-data:www-data /var/www/html/bootstrap/cache

# Clean up old images
echo "🧼 Cleaning up old Docker images..."
sudo docker image prune -f

# Health check
echo "🏥 Performing health check..."
sleep 10
if curl -f -s -o /dev/null https://eainvitaiton.com/api/health || curl -f -s -o /dev/null http://localhost/api/health; then
    echo "✅ Health check passed!"
else
    echo "❌ Health check failed. Please check the logs."
    sudo docker-compose logs --tail=50
fi

echo "🎉 Deployment completed successfully!"
echo "📊 Container status:"
sudo docker-compose ps

echo ""
echo "📝 Useful commands:"
echo "  View logs: sudo docker-compose logs -f"
echo "  Restart: sudo docker-compose restart"
echo "  Shell access: sudo docker-compose exec app bash"
echo "  Laravel commands: sudo docker-compose exec app php artisan [command]"