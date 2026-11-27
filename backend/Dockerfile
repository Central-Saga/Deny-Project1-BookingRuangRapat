# ==============================================================================
# Backend Dockerfile - Multi-Stage & Non-Root User (Audit 496 - Issue #182)
# ==============================================================================

# ------------------------------------------------------------------------------
# Stage 1: Vendor Dependencies Builder
# ------------------------------------------------------------------------------
FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --no-interaction \
    --no-plugins \
    --no-scripts \
    --prefer-dist \
    --optimize-autoloader

# ------------------------------------------------------------------------------
# Stage 2: Production Runtime Stage (Multi-Stage & Non-Root)
# ------------------------------------------------------------------------------
FROM php:8.3-fpm AS runner

# Install dependensi sistem dan ekstensi PHP
RUN apt-get update && apt-get install -y --no-install-recommends \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libzip-dev \
    libpq-dev \
    libonig-dev \
    unzip \
    curl \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) pdo pdo_pgsql pgsql mbstring zip exif pcntl bcmath gd \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www

# Salin kode aplikasi dan dependensi vendor dari builder stage
COPY . .
COPY --from=vendor /app/vendor ./vendor

# Salin skrip entrypoint & release
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
COPY docker/release.sh /usr/local/bin/release.sh

RUN chmod +x /usr/local/bin/entrypoint.sh /usr/local/bin/release.sh \
    && mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/app/public bootstrap/cache \
    && chown -R www-data:www-data /var/www storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Non-root user execution (Audit 496 - Issue #182)
USER www-data

EXPOSE 8000

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
