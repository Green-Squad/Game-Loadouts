# Use PHP 5.6 with Apache as the base image
FROM php:5.6-apache

# Set the working directory inside the container
WORKDIR /var/www/html

ENV HOSTNAME docker

# Update repository URLs to archive and update packages
RUN sed -i 's/http:\/\/deb.debian.org/http:\/\/archive.debian.org/g' /etc/apt/sources.list \
    && sed -i 's/http:\/\/security.debian.org/http:\/\/archive.debian.org/g' /etc/apt/sources.list \
    && sed -i '/stretch-updates/d' /etc/apt/sources.list \
    && echo 'Acquire::Check-Valid-Until "false";' > /etc/apt/apt.conf.d/99no-check-valid-until \
    && echo 'Acquire::AllowInsecureRepositories "true";' > /etc/apt/apt.conf.d/99allow-insecure \
    && apt-get update

# Install dependencies one by one
RUN apt-get install -y libpng-dev \
    && apt-get install -y libjpeg-dev \
    && apt-get install -y libfreetype6-dev \
    && apt-get install -y libmcrypt-dev \
    && apt-get install -y libzip-dev \
    && apt-get install -y zip \
    && apt-get install -y git \
    && apt-get install -y libxml2-dev

# Configure and install PHP extensions
RUN docker-php-ext-configure gd --with-freetype-dir=/usr/include/ --with-jpeg-dir=/usr/include/ \
    && docker-php-ext-install -j$(nproc) gd pdo pdo_mysql mbstring tokenizer xml zip mcrypt

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Copy the current directory contents into the container at /var/www/html
COPY . /var/www/html

# Set ownership and permissions for the storage directory
RUN chown -R www-data:www-data /var/www/html/app/storage \
    && chmod -R 775 /var/www/html/app/storage

# Set Apache document root to Laravel public directory
ENV APACHE_DOCUMENT_ROOT /var/www/html/public

# Update Apache configuration with the new document root
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Enable Apache modules
RUN a2enmod rewrite

# Install all PHP dependencies
RUN composer install

# Expose port 80
EXPOSE 80

# Run Apache server in the foreground
CMD ["apache2-foreground"]