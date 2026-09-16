FROM php:8.2-apache

# Apache necesita rewrite y PHP necesita pdo_mysql
RUN a2enmod rewrite \
    && docker-php-ext-install pdo pdo_mysql \
    && sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf \
    && sed -i 's#DocumentRoot /var/www/html#DocumentRoot /var/www/html/front#' /etc/apache2/sites-available/000-default.conf \
    && sed -i 's#<Directory /var/www/>#<Directory /var/www/html/front/>#' /etc/apache2/apache2.conf

WORKDIR /var/www/html