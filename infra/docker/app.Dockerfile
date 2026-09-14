# Control-plane image: PHP 8.3 CLI/FPM with the extensions Laravel + the adapters need.
FROM php:8.3-cli-alpine

RUN apk add --no-cache icu-dev libzip-dev postgresql-dev oniguruma-dev gmp-dev linux-headers $PHPIZE_DEPS \
    && docker-php-ext-install -j"$(nproc)" intl pdo_pgsql bcmath gmp zip pcntl opcache \
    && pecl install redis && docker-php-ext-enable redis \
    && apk del $PHPIZE_DEPS

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html

# Production build: copy the code, install without dev dependencies, cache config/routes/events.
# In docker-compose the working tree is bind-mounted instead.
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction || true
COPY . .
RUN composer dump-autoload --optimize --no-dev 2>/dev/null || true

ENV PHP_OPCACHE_VALIDATE_TIMESTAMPS=0
EXPOSE 8000
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
