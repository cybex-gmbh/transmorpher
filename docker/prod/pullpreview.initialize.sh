#!/bin/sh

# All commands need to be executed with "shell" to preserve www-data permissions!

if ${PULLPREVIEW:-false}; then
    shell php /var/www/artisan migrate --force

    if ${PULLPREVIEW_FIRST_RUN:-false}; then
        shell php /var/www/artisan db:seed --class=PullpreviewSeeder --force
    fi
fi
