FROM php:8.2-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git curl zip unzip libzip-dev libonig-dev libxml2-dev \
    libpng-dev libjpeg-dev libfreetype6-dev libpq-dev \
    gnupg2 ca-certificates lsb-release sudo \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_mysql pdo_pgsql zip mbstring exif pcntl gd sockets

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Install Node.js 20+
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs \
    && npm install -g npm@latest

# Set working directory
WORKDIR /var/www/superspotwifi

# Copy package and composer files first for caching
COPY package*.json ./
COPY composer.json composer.lock ./

# Install Node dependencies
RUN npm install

# Install PHP dependencies without running scripts yet
RUN composer install --no-dev --no-scripts --no-interaction --no-progress

# Copy the full app source (including artisan)
COPY . .

# Force PHP-FPM to listen on TCP 9000
RUN sed -i 's/^listen = .*/listen = 0.0.0.0:9000/' /usr/local/etc/php-fpm.d/www.conf \
    && sed -i 's/^;listen.allowed_clients = .*/listen.allowed_clients = 0.0.0.0/' /usr/local/etc/php-fpm.d/www.conf

# Run Composer scripts now that artisan exists
RUN composer dump-autoload --optimize

# Optional: clear caches to ensure clean build
RUN php artisan config:clear
RUN php artisan route:clear
RUN php artisan view:clear

# Build frontend
RUN npm run build

# Set Laravel permissions
RUN chown -R www-data:www-data /var/www/superspotwifi \
    && chmod -R 755 /var/www/superspotwifi/storage /var/www/superspotwifi/bootstrap/cache

# Switch to www-data
USER www-data

EXPOSE 9000
