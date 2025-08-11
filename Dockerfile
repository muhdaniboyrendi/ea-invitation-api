# ===== Stage 1: Build Dependencies =====
FROM composer:2.7 AS vendor

WORKDIR /app

# Copy composer files
COPY composer.json composer.lock ./

# Install dependencies (no dev)
RUN composer install --no-dev --optimize-autoloader --no-interaction

# ===== Stage 2: Production App =====
FROM php:8.3-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git zip unzip libpng-dev libjpeg-dev libfreetype6-dev \
    libonig-dev libxml2-dev libzip-dev curl \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip \
    && rm -rf /var/lib/apt/lists/*

# Set working directory
WORKDIR /var/www

# Copy vendor from builder stage
COPY --from=vendor /app/vendor ./vendor

# Copy app code
COPY . .

# Set permissions for Laravel
RUN chown -R www-data:www-data \
    /var/www/storage \
    /var/www/bootstrap/cache

# Cache Laravel configuration
RUN php artisan config:cache && \
    php artisan route:cache && \
    php artisan view:cache || true

EXPOSE 9000
CMD ["php-fpm"]
