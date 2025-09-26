FROM php:8.4.3-fpm

RUN apt-get update \
    && curl -sL https://deb.nodesource.com/setup_22.x -o nodesource_setup.sh \
    && bash nodesource_setup.sh \
    && apt install -y \
    gettext libzip-dev libxml2-dev libpng-dev \
    nginx nano cron git unzip supervisor \
    certbot python3-certbot-nginx \
    nodejs \
    && rm -r /var/lib/apt/lists/*

RUN pecl install --force redis \
    && rm -rf /tmp/pear \
    && docker-php-ext-enable redis

RUN docker-php-ext-install \
    zip calendar dom gd \
    intl pcntl bcmath

ENV APP_HOME /var/www/

WORKDIR $APP_HOME

COPY --chown=www-data:www-data . $APP_HOME

RUN mkdir -p storage/app \
    && mkdir -p storage/logs \
    && mkdir -p storage/framework/sessions \
    && mkdir -p storage/framework/views \
    && mkdir -p storage/framework/cache \
    && chown -R www-data:www-data storage \
    && chmod -R 775 storage

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/bin/ --filename=composer && composer install --no-dev

# Install npm dependencies and build assets
RUN npm ci && npm run build

RUN php artisan optimize

COPY ./docker/prod/supervisord.conf /etc/supervisord.conf
COPY ./docker/prod/nginx.conf /etc/nginx/nginx.conf
COPY ./docker/prod/www.conf /usr/local/etc/php-fpm.d/www.conf
# COPY ./docker/prod/php.ini /usr/local/etc/php/php.ini
RUN mv /usr/local/etc/php/php.ini-production /usr/local/etc/php/php.ini
COPY ./docker/prod/sites/* /etc/nginx/sites-available/

COPY ./docker/prod/docker-entrypoint.sh /
RUN chmod +x /docker-entrypoint.sh

CMD ["/docker-entrypoint.sh"]
