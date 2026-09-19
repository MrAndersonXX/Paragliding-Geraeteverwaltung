FROM php:8.3-fpm-alpine

RUN apk add --no-cache \
    bash \
    curl \
    curl-dev \
    git \
    icu-dev \
    libzip-dev \
    oniguruma-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    mariadb-client \
    zip \
    unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install curl pdo pdo_mysql intl gd zip \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

WORKDIR /var/www/html
COPY . /var/www/html

RUN composer install --no-dev --no-interaction --prefer-dist --no-progress \
    && mkdir -p /var/www/html/resources \
    && curl -fL https://unicode.org/Public/emoji/latest/emoji-test.txt -o /var/www/html/resources/emoji-test.txt \
    && sed -i 's/^;clear_env = no/clear_env = no/' /usr/local/etc/php-fpm.d/www.conf \
    && chmod +x /var/www/html/docker/php/entrypoint.sh

ENTRYPOINT ["/var/www/html/docker/php/entrypoint.sh"]
CMD ["php-fpm"]
