FROM php:8.2-fpm

# Install dependencies
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    zip \
    unzip \
    git \
    curl \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd pdo pdo_mysql

# Cài đặt Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set thư mục làm việc
WORKDIR /var/www/api_zalo_shop

# Copy toàn bộ file vào container
COPY . .

# Đặt lại quyền thư mục
RUN chown -R www-data:www-data /var/www/api_zalo_shop \
    && chmod -R 775 /var/www/api_zalo_shop/storage /var/www/api_zalo_shop/bootstrap/cache

# Chạy Composer Install
RUN composer install --no-dev --optimize-autoloader || true

# Expose cổng PHP-FPM
EXPOSE 9999

CMD ["php-fpm"]