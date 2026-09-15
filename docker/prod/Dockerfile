# Estágio de build: dependências PHP e bundle do frontend.
#
# Precisa de PHP e do vendor, e não só de Node: o `vite build` roda o plugin do
# wayfinder, que chama `php artisan` para gerar as rotas em TypeScript.
FROM php:8.4.3-fpm AS build

RUN apt-get update \
    && curl -sL https://deb.nodesource.com/setup_22.x -o nodesource_setup.sh \
    && bash nodesource_setup.sh \
    && apt install -y \
    gettext libzip-dev libxml2-dev libpng-dev \
    git unzip \
    nodejs \
    && rm -r /var/lib/apt/lists/*

RUN pecl install --force redis \
    && rm -rf /tmp/pear \
    && docker-php-ext-enable redis

RUN docker-php-ext-install \
    zip calendar dom gd \
    intl pcntl bcmath

ENV APP_HOME=/var/www
WORKDIR $APP_HOME

COPY . $APP_HOME

# Antes do composer, e não depois: o `composer install` dispara
# `artisan package:discover`, que boota o framework e resolve o caminho de cache
# das views. Sem os diretórios ele morre com "Please provide a valid cache
# path" — e eles não chegam pelo contexto, porque o .dockerignore exclui
# storage/ inteiro.
RUN mkdir -p storage/app storage/logs \
             storage/framework/sessions \
             storage/framework/views \
             storage/framework/cache

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/bin/ --filename=composer \
    && composer install --no-dev --optimize-autoloader

RUN npm ci && npm run build

# Imagem final: sem Node, sem composer, sem node_modules.
FROM php:8.4.3-fpm

# Mesma lista do estágio de build, menos o que era só de build: nodejs (e o
# repositório da nodesource), git e unzip. certbot, nano e cron já tinham saído.
RUN apt-get update \
    && apt install -y \
    gettext libzip-dev libxml2-dev libpng-dev \
    nginx supervisor \
    && rm -r /var/lib/apt/lists/*

RUN pecl install --force redis \
    && rm -rf /tmp/pear \
    && docker-php-ext-enable redis

RUN docker-php-ext-install \
    zip calendar dom gd \
    intl pcntl bcmath

ENV APP_HOME=/var/www
WORKDIR $APP_HOME

# O código vem do contexto (sem vendor e sem node_modules, pelo .dockerignore);
# vendor e o bundle vêm prontos do estágio de build.
COPY --chown=www-data:www-data . $APP_HOME
COPY --from=build --chown=www-data:www-data /var/www/vendor $APP_HOME/vendor
COPY --from=build --chown=www-data:www-data /var/www/public/build $APP_HOME/public/build

RUN mkdir -p storage/app \
    && mkdir -p storage/logs \
    && mkdir -p storage/framework/sessions \
    && mkdir -p storage/framework/views \
    && mkdir -p storage/framework/cache \
    && chown -R www-data:www-data storage \
    && chmod -R 775 storage

# O `php artisan optimize` que rodava aqui saiu: no build não existe .env, então
# ele cacheava configuração com valores de placeholder. O entrypoint já roda
# optimize em runtime, com o .env montado — que é onde faz sentido.

COPY ./docker/prod/supervisord.conf /etc/supervisord.conf
COPY ./docker/prod/nginx.conf /etc/nginx/nginx.conf
COPY ./docker/prod/www.conf /usr/local/etc/php-fpm.d/www.conf
RUN mv /usr/local/etc/php/php.ini-production /usr/local/etc/php/php.ini
COPY ./docker/prod/sites/* /etc/nginx/sites-available/

COPY ./docker/prod/docker-entrypoint.sh /
RUN chmod +x /docker-entrypoint.sh

CMD ["/docker-entrypoint.sh"]
