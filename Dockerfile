ARG BUILD_FROM=ghcr.io/hassio-addons/base:16.0.0
# hadolint ignore=DL3006
FROM $BUILD_FROM

# hadolint ignore=DL3018
RUN apk update && apk add --no-cache \
    supervisor \
    composer \
    php83 \
    php83-common \
    php83-fpm \
    php83-pdo \
    php83-zip \
    php83-phar \
    php83-iconv \
    php83-cli \
    php83-curl \
    php83-openssl \
    php83-mbstring \
    php83-tokenizer \
    php83-fileinfo \
    php83-json \
    php83-xml \
    php83-xmlwriter \
    php83-simplexml \
    php83-dom \
    php83-tokenizer \
    php83-pecl-redis \
    php83-pcntl \
    php83-posix && \
    mkdir /home/app

COPY ./ /home/app
WORKDIR /home/app
RUN composer install --no-interaction --no-dev -vvv
RUN chmod a+x run.sh

CMD [ "/home/app/run.sh" ]
