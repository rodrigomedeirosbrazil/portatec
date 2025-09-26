#!/bin/sh

# echo "Changing Nginx default port to ${HTTP_NGINX_PORT}/${HTTPS_NGINX_PORT}"
# /usr/bin/envsubst '$HTTP_NGINX_PORT,$HTTPS_NGINX_PORT' < /etc/nginx/nginx.conf.template > /etc/nginx/nginx.conf

echo "Installing npm dependencies..."
cd /var/www && npm ci

echo "Building Vite assets..."
cd /var/www && npm run build

echo "Running optimize command..."
/usr/local/bin/php /var/www/artisan optimize

echo "Starting Supervisor..."
/usr/bin/supervisord -c /etc/supervisord.conf
