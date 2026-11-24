# syntax=docker/dockerfile:1.7

FROM cybexwebdev/php-laravel:8.5-devtools

WORKDIR /var/www

COPY --chown=www-data:www-data docker/common/nginx.default.conf /etc/nginx/conf.d/default.conf.template

# Alpine
#RUN set -ex; \
#    apk update; \
#    apk add \
#      jpegoptim \
#      optipng \
#      pngquant \
#      gifsicle \
#      libwebp \
#      libwebp-tools \
#      # For running tests \
#      ffmpeg; \
#    rm -rf /var/cache/apk/*

RUN set -ex; \
    export DEBIAN_FRONTEND=noninteractive; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
      jpegoptim \
      optipng \
      pngquant \
      gifsicle \
      webp \
      libwebp-dev \
      # For running tests \
      ffmpeg; \
    rm -rf /var/lib/apt/lists/* /var/cache/apt/archives/*
