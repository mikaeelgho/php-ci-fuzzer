FROM php:8.3-cli

RUN apt-get update && \
    apt-get install -y \
        libzip-dev \
        unzip \
        git \
        zlib1g-dev \
        libxml2-dev \
        && docker-php-ext-install zip xml \

RUN pecl install xdebug \
    && docker-php-ext-enable xdebug

COPY php.ini /usr/local/etc/php/

WORKDIR /app

CMD ["php"]
