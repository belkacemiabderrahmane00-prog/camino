#!/bin/sh
set -e

php artisan migrate --force
php artisan db:seed --class=CategorySeeder --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan storage:link 2>/dev/null || true

sed -i "s/listen 8080/listen ${PORT:-8080}/" /etc/nginx/conf.d/default.conf

php-fpm -D
nginx -g "daemon off;"
