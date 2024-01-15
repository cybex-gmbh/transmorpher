#!/usr/bin/env bash

if ${PULLPREVIEW:-false}; then
    php /var/www/html/artisan migrate --force
    php /var/www/html/artisan protector:import --remote --force
fi

exec /entrypoint supervisord "$@"
