ARG BUILD_FROM=ghcr.io/hassio-addons/base:15.0.6
FROM ghcr.io/roadrunner-server/roadrunner:2023.3.10 AS roadrunner
FROM $BUILD_FROM

RUN apk add --no-cache \
    supervisor \
    composer \
    php82 \
    php82-common \
    php82-fpm \
    php82-pdo \
    php82-zip \
    php82-phar \
    php82-iconv \
    php82-cli \
    php82-curl \
    php82-openssl \
    php82-mbstring \
    php82-tokenizer \
    php82-fileinfo \
    php82-json \
    php82-xml \
    php82-xmlwriter \
    php82-simplexml \
    php82-dom \
    php82-tokenizer \
    php82-pecl-redis \
    php82-pcntl \
    php82-posix

RUN mkdir /home/app
COPY ./ /home/app
WORKDIR /home/app
RUN composer install --no-interaction --no-dev --no-suggest
RUN cp .env.example .env && php artisan key:generate
COPY --from=roadrunner /usr/bin/rr rr
RUN chmod a+x run.sh

CMD [ "/home/app/run.sh" ]
