# syntax=docker/dockerfile:1-labs
FROM webdevops/php-nginx:8.2

WORKDIR /var/www/html

ENV WEB_DOCUMENT_ROOT /var/www/html/public

# Timeout for the post update script in minutes.
LABEL com.centurylinklabs.watchtower.lifecycle.post-update-timeout="1440"
# Watchtower will run this script after restarting the updated container.
LABEL com.centurylinklabs.watchtower.lifecycle.post-update="/var/www/html/docker/watchtower.post-update.sh"

RUN docker-service-enable cron
RUN docker-cronjob '* * * * * application /usr/local/bin/php /var/www/html/artisan schedule:run >> /dev/null 2>&1'

COPY composer.json composer.lock /var/www/html/
RUN composer install --no-interaction --no-dev --no-scripts

COPY ./docker/workers.conf /opt/docker/etc/supervisor.d/
COPY --chmod=755 ./docker/entryfile.sh /var/www/html/docker/entryfile.sh
COPY --chmod=755 ./docker/watchtower.post-update.sh /var/www/html/docker/watchtower.post-update.sh
COPY --chmod=755 --chown=application:application ./storage /var/www/html/storage

RUN apt update && \
    apt install -y --no-install-recommends default-mysql-client imagemagick jpegoptim optipng pngquant gifsicle webp && \
    rm -rf /var/lib/apt/lists/*

# Remove imagemagick policy preventing PDF conversion (security issue is fixed with the installed ghostscript version 10)
RUN sed -i '/disable ghostscript format types/,+6d' /etc/ImageMagick-6/policy.xml

## ffmpeg 6 - https://www.deb-multimedia.org/dists/stable/non-free/binary-amd64/
RUN printf "\ndeb https://www.deb-multimedia.org bookworm main non-free" >> /etc/apt/sources.list \
    && apt-get update -oAcquire::AllowInsecureRepositories=true \
    && apt-get -y --allow-unauthenticated install deb-multimedia-keyring \
    && wget https://www.deb-multimedia.org/pool/main/d/deb-multimedia-keyring/deb-multimedia-keyring_2016.8.1_all.deb \
    && dpkg -i deb-multimedia-keyring_2016.8.1_all.deb \
    && apt-get -y --allow-unauthenticated install ffmpeg

COPY \
    --exclude=./storage \
    --exclude=./docker/workers.conf \
    --exclude=./docker/entryfile.sh \
    --exclude=./docker/watchtower.post-update.sh \
    . /var/www/html

RUN php /var/www/html/artisan storage:link

ENTRYPOINT ["/var/www/html/docker/entryfile.sh"]
