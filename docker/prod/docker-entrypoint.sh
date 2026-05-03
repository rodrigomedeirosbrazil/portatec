#!/bin/sh

# echo "Changing Nginx default port to ${HTTP_NGINX_PORT}/${HTTPS_NGINX_PORT}"
# /usr/bin/envsubst '$HTTP_NGINX_PORT,$HTTPS_NGINX_PORT' < /etc/nginx/nginx.conf.template > /etc/nginx/nginx.conf

# Rebuild frontend assets so VITE_* (e.g. VITE_REVERB_*) from the mounted .env are embedded.
# The image is built in CI without .env, so the initial build has undefined Reverb URL and WebSocket fails.
chown -R www-data:www-data /var/www/storage

echo "Building frontend assets with current .env (VITE_* for Reverb/WebSocket)..."
su www-data -s /bin/sh -c "cd /var/www && npm run build"

echo "Running optimize command..."
/usr/local/bin/php /var/www/artisan optimize

echo "Starting Supervisor..."
/usr/bin/supervisord -c /etc/supervisord.conf
