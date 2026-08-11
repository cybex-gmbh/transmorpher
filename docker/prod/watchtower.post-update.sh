#!/bin/sh

# All commands need to be executed with "shell" to preserve www-data permissions!

shell php /var/www/artisan migrate --force
