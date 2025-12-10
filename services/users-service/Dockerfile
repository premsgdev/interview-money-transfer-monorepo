FROM php:8.2-cli

# System deps
RUN apt-get update && apt-get install -y \
    git unzip libzip-dev libicu-dev libxml2-dev libonig-dev zlib1g-dev \
    openssl \
    && docker-php-ext-install pdo_mysql intl zip opcache

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /srv/app

# Copy composer files first and install dependencies for faster builds
COPY composer.json composer.lock /srv/app/
RUN composer install --no-dev --no-interaction --no-plugins --no-scripts || true

# Copy full source (if using image for demo); in local dev we bind-mount host code
COPY . /srv/app

RUN chown -R www-data:www-data /srv/app || true

EXPOSE 8000

CMD ["sh", "-c", "composer dump-autoload --ansi && php -S 0.0.0.0:8000 -t public"]
