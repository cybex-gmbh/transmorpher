# syntax=docker/dockerfile:1-labs
FROM webdevops/php-nginx:8.4 as base

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

COPY \
    --exclude=./storage \
    --exclude=./docker/workers.conf \
    --exclude=./docker/entryfile.sh \
    --exclude=./docker/watchtower.post-update.sh \
    . /var/www/html

RUN php /var/www/html/artisan storage:link

ENTRYPOINT ["/var/www/html/docker/entryfile.sh"]

FROM base as binaries

ENV IMAGEMAGICK_VERSION="7.1.2-8"
ENV IMAGICK_VERSION="3.8.0"
ENV FFMPEG_KEYRING=deb-multimedia-keyring_2024.9.1_all.deb
ENV FFMPEG_KEYRING_CHECKSUM=sha256:8dc6cbb266c701cfe58bd1d2eb9fe2245a1d6341c7110cfbfe3a5a975dcf97ca

RUN apt update
RUN apt install -y --no-install-recommends default-mysql-client
RUN apt install -y --no-install-recommends jpegoptim optipng pngquant gifsicle webp
RUN apt install -y --no-install-recommends libjpeg-dev libpng-dev libwebp-dev
RUN rm -rf /var/lib/apt/lists/*

# Install ImageMagick from source - https://github.com/ImageMagick/ImageMagick
WORKDIR /ImageMagick-$IMAGEMAGICK_VERSION

RUN git clone --depth 1 --branch $IMAGEMAGICK_VERSION https://github.com/ImageMagick/ImageMagick.git .
RUN ./configure
RUN make
RUN make install
RUN ldconfig /usr/local/lib

# Install Imagick from source - https://github.com/Imagick/imagick
WORKDIR /Imagick-$IMAGICK_VERSION

RUN git clone --depth 1 --branch $IMAGICK_VERSION https://github.com/Imagick/imagick .
RUN phpize && ./configure
RUN make
RUN make install

# Install FFmpeg - https://deb-multimedia.org/dists/stable/main/binary-amd64/package/ffmpeg
WORKDIR /ffmpeg

RUN echo "Types: deb\nURIs: https://www.deb-multimedia.org\nSuites: bookworm\nComponents: main\nSigned-By: /usr/share/keyrings/deb-multimedia-keyring.pgp\nEnabled: yes" >> /etc/apt/sources.list.d/dmo.sources
ADD --checksum=$FFMPEG_CHECKSUM \
 https://www.deb-multimedia.org/pool/main/d/deb-multimedia-keyring/$FFMPEG_KEYRING \
    $FFMPEG_KEYRING
RUN dpkg -i $FFMPEG_KEYRING \
    && apt update \
    && apt -y install ffmpeg

WORKDIR /var/www/html
