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

# Set working directory
WORKDIR /var/www/superspotwifi

# Copy app source into the working directory
COPY . /var/www/superspotwifi

# Fix permissions
RUN chown -R www-data:www-data /var/www/superspotwifi \
    && chmod -R 755 /var/www/superspotwifi/storage /var/www/superspotwifi/bootstrap/cache

# Install Node.js 20+
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs \
    && npm install -g npm@latest

# Expose PHP-FPM port
EXPOSE 9000

# Run as www-data user
USER www-data
