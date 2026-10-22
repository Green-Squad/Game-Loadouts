FROM php:8.4.27-apache@sha256:a0b75cee7023ba2c127138dcad2ecc6ec7e09435534645e70450f29f24ea2a5a
WORKDIR /var/www/html
RUN apt-get update && apt-get install -y --no-install-recommends libpng-dev libjpeg62-turbo-dev libfreetype6-dev libmcrypt-dev libonig-dev libxml2-dev libzip-dev unzip git openssl patch && rm -rf /var/lib/apt/lists/*
RUN docker-php-ext-configure gd --with-freetype --with-jpeg && docker-php-ext-install -j2 gd pdo_mysql mbstring xml zip && pecl install mcrypt-1.0.9 && docker-php-ext-enable mcrypt
COPY --from=composer:2.2.25@sha256:be23a9c3f73293f966a981be03896f9aaaade6c2cc9415f4cf828d1f39c5005c /usr/bin/composer /usr/local/bin/composer
COPY composer.json ./
COPY hand-forged/composer.lock ./
COPY hand-forged/php84.patch /tmp/php84.patch
COPY hand-forged/sitemap-package.json /tmp/sitemap-package.json
RUN composer config repositories.hand-forged-sitemap "$(cat /tmp/sitemap-package.json)" && composer install --no-autoloader --no-dev --no-scripts --prefer-dist --no-interaction && patch --batch --fuzz=0 -p1 < /tmp/php84.patch && rm /tmp/php84.patch
# Keep large original asset layers stable across demo source updates.
COPY --chown=www-data:www-data public ./public
COPY app ./app
COPY bootstrap ./bootstrap
COPY artisan .env.php ./
COPY hand-forged ./hand-forged
RUN composer dump-autoload --no-dev --no-scripts --optimize && mkdir -p app/storage/logs app/storage/cache app/storage/views app/storage/sessions public/uploads/thumb public/uploads/header && chown -R www-data:www-data app/storage && a2enmod rewrite && sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf && printf '<VirtualHost *:8080>\n DocumentRoot /var/www/html/public\n <Directory /var/www/html/public>\n Require all granted\n AllowOverride All\n </Directory>\n</VirtualHost>\n' > /etc/apache2/sites-available/000-default.conf && printf 'Listen 8080\n' > /etc/apache2/ports.conf
RUN printf '<IfModule mpm_prefork_module>\n StartServers 2\n MinSpareServers 1\n MaxSpareServers 3\n ServerLimit 4\n MaxRequestWorkers 4\n MaxConnectionsPerChild 500\n</IfModule>\n' > /etc/apache2/mods-available/mpm_prefork.conf && printf '<Directory /var/www/html/public/uploads>\n php_admin_flag engine off\n Options -ExecCGI\n <FilesMatch "\\.(php|phtml|phar)$">\n Require all denied\n </FilesMatch>\n</Directory>\n' > /etc/apache2/conf-available/hand-forged.conf && a2enconf hand-forged
RUN printf "display_errors=Off\nlog_errors=On\ndate.timezone=UTC\n" > /usr/local/etc/php/conf.d/hand-forged.ini
ENV HAND_FORGED_DEMO=true HAND_FORGED_HOST=handforged.gameloadouts.com PORT=8080
EXPOSE 8080
ENTRYPOINT ["sh","hand-forged/start.sh"]
