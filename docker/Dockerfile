# syntax=docker/dockerfile:1-labs
FROM webdevops/php-nginx:8.4

WORKDIR /var/www/html

ENV WEB_DOCUMENT_ROOT=/var/www/html/public

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

## ffmpeg - https://deb-multimedia.org/dists/stable/main/binary-amd64/package/ffmpeg
RUN echo "Types: deb\nURIs: https://www.deb-multimedia.org\nSuites: bookworm\nComponents: main\nSigned-By: /usr/share/keyrings/deb-multimedia-keyring.pgp\nEnabled: yes" >> /etc/apt/sources.list.d/dmo.sources
ADD --checksum=sha256:8dc6cbb266c701cfe58bd1d2eb9fe2245a1d6341c7110cfbfe3a5a975dcf97ca \
 https://www.deb-multimedia.org/pool/main/d/deb-multimedia-keyring/deb-multimedia-keyring_2024.9.1_all.deb \
    deb-multimedia-keyring_2024.9.1_all.deb
RUN dpkg -i deb-multimedia-keyring_2024.9.1_all.deb \
    && apt update \
    && apt -y install ffmpeg

COPY \
    --exclude=./storage \
    --exclude=./docker/workers.conf \
    --exclude=./docker/entryfile.sh \
    --exclude=./docker/watchtower.post-update.sh \
    . /var/www/html

RUN php /var/www/html/artisan storage:link

ENTRYPOINT ["/var/www/html/docker/entryfile.sh"]
