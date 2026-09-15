#!/bin/sh
set -e

# echo "Changing Nginx default port to ${HTTP_NGINX_PORT}/${HTTPS_NGINX_PORT}"
# /usr/bin/envsubst '$HTTP_NGINX_PORT,$HTTPS_NGINX_PORT' < /etc/nginx/nginx.conf.template > /etc/nginx/nginx.conf

# storage e o banco são montados do host: o dono precisa ser acertado a cada
# arranque. Os chowns de resources/js/* saíram junto com o `npm run build` —
# o bundle agora vem pronto do CI, e a conexão do Reverb chega em runtime pelo
# documento (config/reverb_client.php).
chown -R www-data:www-data /var/www/storage
chown www-data:www-data /var/www/database/database.sqlite

echo "Running database migrations..."
su www-data -s /bin/sh -c "/usr/local/bin/php /var/www/artisan migrate --force"

echo "Running optimize command..."
/usr/local/bin/php /var/www/artisan optimize

echo "Starting Supervisor..."
/usr/bin/supervisord -c /etc/supervisord.conf
