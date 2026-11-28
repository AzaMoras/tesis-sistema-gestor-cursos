# syntax=docker/dockerfile:1
FROM php:8.4-fpm-bookworm AS app
RUN apt-get update && apt-get install -y --no-install-recommends libicu-dev libzip-dev libonig-dev libsqlite3-dev unzip git \
    && docker-php-ext-install pdo_mysql pdo_sqlite intl zip bcmath mbstring opcache \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
WORKDIR /var/www/html
COPY . .
RUN composer install --no-interaction --prefer-dist --no-progress \
    && mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs storage/app/private storage/app/public \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R u+rwX,g+rwX storage bootstrap/cache
COPY docker/php.ini /usr/local/etc/php/conf.d/aula.ini
COPY docker/entrypoint.sh /usr/local/bin/aula-entrypoint
RUN chmod 755 /usr/local/bin/aula-entrypoint
USER www-data
ENTRYPOINT ["aula-entrypoint"]
CMD ["php-fpm", "-F"]

FROM node:22-bookworm-slim AS assets
WORKDIR /src
COPY package.json package-lock.json ./
RUN npm ci
COPY resources ./resources
COPY public ./public
COPY vite.config.js ./
# Tailwind scans Laravel's pagination templates.
COPY --from=app /var/www/html/vendor/laravel/framework/src/Illuminate/Pagination/resources/views ./vendor/laravel/framework/src/Illuminate/Pagination/resources/views
RUN npm run build

FROM app AS runtime
COPY --from=assets /src/public/build ./public/build

FROM runtime AS production
USER root
RUN composer install --no-dev --no-interaction --prefer-dist --no-progress --optimize-autoloader
RUN chown -R www-data:www-data storage bootstrap/cache
USER www-data

FROM nginxinc/nginx-unprivileged:stable-alpine AS nginx
COPY nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=runtime /var/www/html/public /var/www/html/public
USER 101:101
