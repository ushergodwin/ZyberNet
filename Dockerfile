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
COPY composer*.json ./

# Install Node and PHP dependencies
RUN npm install
RUN composer install --no-dev --optimize-autoloader

# Copy the full app source
COPY . .

# Build frontend
RUN npm run build

# Set Laravel permissions
RUN chown -R www-data:www-data /var/www/superspotwifi \
    && chmod -R 755 /var/www/superspotwifi/storage /var/www/superspotwifi/bootstrap/cache

# Switch to www-data
USER www-data

EXPOSE 9000
