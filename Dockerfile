# syntax=docker/dockerfile:1

#############################################
# Stage: composer dependencies (production, no-dev)
#############################################
FROM php:8.2-cli AS vendor
RUN apt-get update && apt-get install -y --no-install-recommends unzip git \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist
COPY . .
RUN composer dump-autoload --optimize --no-dev

#############################################
# Stage: frontend assets (Vite production build)
#############################################
FROM node:20-alpine AS frontend
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY resources resources
COPY vite.config.js tailwind.config.js postcss.config.js ./
RUN npm run build

#############################################
# Stage: base PHP + Apache image with the extensions Laravel needs
#############################################
FROM php:8.2-apache AS base

RUN apt-get update && apt-get install -y --no-install-recommends \
        libzip-dev libpng-dev libjpeg-dev libfreetype6-dev libonig-dev \
        unzip git \
    && docker-php-ext-configure gd --with-jpeg --with-freetype \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql mbstring bcmath exif gd zip pcntl \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

COPY docker/apache/000-default.conf /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www/html

#############################################
# Stage: dev — code is bind-mounted by docker-compose.yml, this just
# provides the runtime + Composer + dev dependencies installed once at
# build time (overridden by the named "vendor" volume, see compose file).
#############################################
FROM base AS dev

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY . .
RUN composer install --no-interaction --no-scripts \
    && chown -R www-data:www-data storage bootstrap/cache

CMD ["apache2-foreground"]

#############################################
# Stage: production — self-contained image, no bind mounts expected.
#############################################
FROM base AS production

COPY . .
COPY --from=vendor /app/vendor ./vendor
COPY --from=frontend /app/public/build ./public/build
RUN chown -R www-data:www-data storage bootstrap/cache

CMD ["apache2-foreground"]
