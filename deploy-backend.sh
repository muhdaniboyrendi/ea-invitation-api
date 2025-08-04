#!/bin/bash
# deploy-backend.sh - Script untuk deployment manual

set -e

echo "🚀 Starting EA Invitation API Deployment..."

# Configuration
PROJECT_PATH="/var/www/ea-invitation-api"
BACKUP_PATH="/var/backups/ea-invitation-api"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Functions
log_info() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

log_warn() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Check if running as root
if [[ $EUID -eq 0 ]]; then
   log_error "This script should not be run as root"
   exit 1
fi

# Create backup
log_info "Creating backup..."
sudo mkdir -p "$BACKUP_PATH"
sudo cp -r "$PROJECT_PATH" "$BACKUP_PATH/backup_$TIMESTAMP"

# Navigate to project directory
cd "$PROJECT_PATH"

# Pull latest changes
log_info "Pulling latest code from repository..."
git fetch origin
git reset --hard origin/main

# Copy environment file
log_info "Setting up environment..."
if [ ! -f .env ]; then
    cp .env.production .env
    log_info "Environment file copied from .env.production"
fi

# Stop existing containers
log_info "Stopping existing containers..."
docker-compose down

# Build new images
log_info "Building Docker images..."
docker-compose build --no-cache

# Start containers
log_info "Starting containers..."
docker-compose up -d

# Wait for containers to be ready
log_info "Waiting for containers to start..."
sleep 30

# Check if containers are running
if ! docker-compose ps | grep -q "Up"; then
    log_error "Containers failed to start properly"
    exit 1
fi

# Run Laravel commands
log_info "Running Laravel migrations and cache commands..."
docker-compose exec -T ea-invitation-api php artisan migrate --force
docker-compose exec -T ea-invitation-api php artisan config:clear
docker-compose exec -T ea-invitation-api php artisan cache:clear
docker-compose exec -T ea-invitation-api php artisan route:clear
docker-compose exec -T ea-invitation-api php artisan view:clear
docker-compose exec -T ea-invitation-api php artisan config:cache
docker-compose exec -T ea-invitation-api php artisan route:cache
docker-compose exec -T ea-invitation-api php artisan view:cache

# Storage link
docker-compose exec -T ea-invitation-api php artisan storage:link

# Clean up old Docker images
log_info "Cleaning up old Docker images..."
docker system prune -f

# Verify deployment
log_info "Verifying deployment..."
sleep 10
if curl -f -s http://localhost:8000/api/health > /dev/null; then
    log_info "✅ Deployment successful! API is responding."
else
    log_warn "⚠️  API might not be fully ready yet. Check logs with: docker-compose logs"
fi

echo ""
log_info "🎉 Deployment completed!"
log_info "📝 Backup created at: $BACKUP_PATH/backup_$TIMESTAMP"
log_info "🔍 Check logs with: docker-compose logs -f"
log_info "🌐 API should be available at: https://api.eainvitaiton.com"