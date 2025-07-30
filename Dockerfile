
# Production stage
FROM php:8.3-fpm

RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    libonig-dev \
    libxml2-dev \
    libftp-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_mysql mbstring exif pcntl bcmath gd zip ftp opcache \
    && pecl install redis \
    && docker-php-ext-enable redis opcache

    RUN apt-get clean && rm -rf /var/lib/apt/lists/*

    # Install PHP extensions
    RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd ftp
    # Install Redis extension
    RUN pecl list | grep redis > /dev/null || pecl install redis
    RUN docker-php-ext-enable redis

    COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

    WORKDIR /var/www

    COPY . /var/www
    COPY .env.ci .env
    COPY backup.txt composer.json

    ENV COMPOSER_ALLOW_SUPERUSER=1

    COPY --chown=www-data:www-data . /var/www
    # Install application dependencies
    RUN composer install --no-scripts --no-autoloader

    RUN chown -R www-data:www-data /var/www/storage \
        && chmod -R 775 /var/www/storage
    
    # Generate optimized autoload files
    RUN composer dump-autoload --optimize

    RUN chown -R www-data:www-data /var/www

    # PHP INI for production
    RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

    # Add custom php.ini settings
    COPY php.ini $PHP_INI_DIR/conf.d/

    EXPOSE 9000
    CMD ["php-fpm"]