FROM php:8.3-cli-alpine

RUN apk add --no-cache \
    bash \
    git \
    icu-dev \
    libpq-dev \
    unzip \
    zip \
    && docker-php-ext-install intl pdo pdo_pgsql

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/app

COPY composer.json composer.lock* ./
RUN composer install --no-interaction --prefer-dist --no-progress || true

COPY . .

EXPOSE 8080

CMD ["sh", "docker/app/entrypoint.sh"]
