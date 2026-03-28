FROM docker.io/php:8.4-fpm

RUN apt-get update && apt-get install -y \
    libpq-dev libsodium-dev libicu-dev libzip-dev libgmp-dev zip git \
    && docker-php-ext-install pdo pdo_pgsql sodium intl opcache zip gmp bcmath

COPY --from=docker.io/composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

COPY . .

RUN composer install --optimize-autoloader

RUN chown -R www-data:www-data var public
RUN chmod +x docker-init.sh

ENTRYPOINT ["/var/www/docker-init.sh"]
