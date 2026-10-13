FROM php:5.6.40-apache@sha256:0a40fd273961b99d8afe69a61a68c73c04bc0caa9de384d3b2dd9e7986eec86d
WORKDIR /var/www/html
RUN printf "deb http://archive.debian.org/debian stretch main\n deb http://archive.debian.org/debian-security stretch/updates main\n" > /etc/apt/sources.list && apt-get -o Acquire::Check-Valid-Until=false -o Acquire::ForceIPv4=true -o Acquire::http::Timeout=30 -o Acquire::Retries=2 update && apt-get -o Acquire::ForceIPv4=true -o Acquire::http::Timeout=30 -o Acquire::Retries=2 install -y --no-install-recommends libpng-dev libjpeg-dev libfreetype6-dev libmcrypt-dev libxml2-dev zlib1g-dev unzip git openssl && rm -rf /var/lib/apt/lists/*
RUN docker-php-ext-configure gd --with-freetype-dir=/usr/include/ --with-jpeg-dir=/usr/include/ && docker-php-ext-install -j2 gd pdo_mysql mbstring xml zip mcrypt
COPY --from=composer:2.2.25@sha256:be23a9c3f73293f966a981be03896f9aaaade6c2cc9415f4cf828d1f39c5005c /usr/bin/composer /usr/local/bin/composer
COPY composer.json ./
RUN composer install --no-dev --no-scripts --prefer-dist --no-interaction
# Keep large original asset layers stable across demo source updates.
COPY public ./public
COPY app ./app
COPY bootstrap ./bootstrap
COPY artisan .env.php ./
COPY hand-forged ./hand-forged
RUN composer dump-autoload --no-dev --no-scripts --optimize && mkdir -p app/storage/logs app/storage/cache app/storage/views app/storage/sessions public/uploads/thumb public/uploads/header && chown -R www-data:www-data app/storage public/uploads && a2enmod rewrite && sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf && printf '<VirtualHost *:8080>\n DocumentRoot /var/www/html/public\n <Directory /var/www/html/public>\n Require all granted\n AllowOverride All\n </Directory>\n</VirtualHost>\n' > /etc/apache2/sites-available/000-default.conf && printf 'Listen 8080\n' > /etc/apache2/ports.conf
RUN printf '<IfModule mpm_prefork_module>\n StartServers 2\n MinSpareServers 1\n MaxSpareServers 3\n ServerLimit 4\n MaxRequestWorkers 4\n MaxConnectionsPerChild 500\n</IfModule>\n' > /etc/apache2/mods-available/mpm_prefork.conf && printf '<Directory /var/www/html/public/uploads>\n php_admin_flag engine off\n Options -ExecCGI\n <FilesMatch "\\.(php|phtml|phar)$">\n Require all denied\n </FilesMatch>\n</Directory>\n' > /etc/apache2/conf-available/hand-forged.conf && a2enconf hand-forged
ENV HAND_FORGED_DEMO=true HAND_FORGED_HOST=handforged.gameloadouts.com PORT=8080
EXPOSE 8080
ENTRYPOINT ["sh","hand-forged/start.sh"]
