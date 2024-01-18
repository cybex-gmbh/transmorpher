FROM webdevops/php-nginx:8.2

WORKDIR /var/www/html

ENV WEB_DOCUMENT_ROOT /var/www/html/public

# Timeout for the post update script in minutes.
LABEL com.centurylinklabs.watchtower.lifecycle.post-update-timeout="1440"
# Watchtower will run this script after restarting the updated container.
LABEL com.centurylinklabs.watchtower.lifecycle.post-update="/var/www/html/docker/watchtower.post-update.sh"

COPY . /var/www/html
COPY ./docker/workers.conf /opt/docker/etc/supervisor.d/

RUN composer install --no-interaction --no-dev

RUN chmod +x /var/www/html/docker/entryfile.sh
RUN chmod +x /var/www/html/docker/watchtower.post-update.sh
RUN chmod 755 -R /var/www/html/storage
RUN chown -R application:application /var/www/html/storage

RUN php /var/www/html/artisan storage:link

RUN apt update
RUN apt install -y imagemagick jpegoptim optipng pngquant gifsicle webp ffmpeg

RUN docker-service-enable cron
RUN docker-cronjob '0 2 * * * application php /var/www/html/artisan ffmpeg:delete-temp'

ENTRYPOINT ["/var/www/html/docker/entryfile.sh"]
