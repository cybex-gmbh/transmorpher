# syntax=docker/dockerfile:1.7

FROM cybexwebdev/php-laravel:8.4-alpine-devtools

WORKDIR /var/www

RUN set -ex; \
    apk update; \
    apk add \
      jpegoptim \
      optipng \
      pngquant \
      gifsicle \
      libwebp \
      libwebp-tools \
      # For running tests \
      ffmpeg; \
    rm -rf /var/cache/apk/*
