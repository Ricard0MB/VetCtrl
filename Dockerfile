# Usa PHP con Apache
FROM php:8.2-apache

RUN docker-php-ext-install mysqli pdo pdo_mysql

COPY . /var/www/html/

# ⚠️ ESTA LÍNEA DEBE ESTAR COMENTADA (con # al inicio)
# RUN sed -i 's|/var/www/html|/var/www/html/public|g' /etc/apache2/sites-available/000-default.conf

RUN a2enmod rewrite
RUN chown -R www-data:www-data /var/www/html/
EXPOSE 80
