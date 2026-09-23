# syntax=docker/dockerfile:1.7

FROM cybexwebdev/php-laravel:8.4-alpine-devtools as build

WORKDIR /var/www

COPY --chown=www-data:www-data composer.json composer.lock /var/www/
RUN composer install --no-interaction --no-dev --no-scripts

COPY --chown=www-data:www-data . /var/www

RUN chmod +x /var/www/docker/prod/pullpreview.initialize.sh; \
    chmod +x /var/www/docker/prod/watchtower.post-update.sh;

RUN php /var/www/artisan storage:link

## Transcoder

FROM cybexwebdev/php-laravel:8.4-transcoder as transcoder

COPY --from=build /var/www /var/www

## App

FROM cybexwebdev/php-laravel:8.4-alpine as app

# Timeout for the post update script in minutes.
LABEL com.centurylinklabs.watchtower.lifecycle.post-update-timeout="1440"
# Watchtower will run this script after restarting the updated container.
LABEL com.centurylinklabs.watchtower.lifecycle.post-update="/var/www/docker/watchtower.post-update.sh"

COPY --chown=www-data:www-data docker/common/nginx.default.conf /etc/nginx/conf.d/default.conf.template

RUN set -ex; \
    apk add \
      jpegoptim \
      optipng \
      pngquant \
      gifsicle \
      libwebp \
      libwebp-tools;

RUN set -ex; \
    apk update; \
    apk upgrade; \
    rm -rf /var/cache/apk/*

COPY --from=build /var/www /var/www
