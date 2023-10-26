FROM webdevops/php-nginx:8.2

WORKDIR /var/www/html

ENV WEB_DOCUMENT_ROOT /var/www/html/public

COPY . /var/www/html

RUN composer install --no-interaction

RUN chmod +x entryfile.sh

# Timeout for the post update script in minutes.
LABEL com.centurylinklabs.watchtower.lifecycle.post-update-timeout="1440"
# Watchtower will run this script after restarting the updated container.
LABEL com.centurylinklabs.watchtower.lifecycle.post-update="/var/www/html/entryfile.sh"

RUN chmod 777 -R ./storage/

RUN php artisan config:cache && \
    php artisan config:clear && \
    php artisan route:cache && \
    php artisan storage:link

RUN apt update
RUN apt install -y imagemagick jpegoptim optipng pngquant gifsicle webp ffmpeg

CMD ["supervisord"]
