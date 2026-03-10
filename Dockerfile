# Usa PHP con Apache
FROM php:8.2-apache

# Instala extensiones de PHP necesarias para MySQL
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Copia TODOS tus archivos al contenedor
COPY . /var/www/html/

# Configura Apache para que use la carpeta /public como raíz
RUN sed -i 's|/var/www/html|/var/www/html/public|g' /etc/apache2/sites-available/000-default.conf

# Habilita el módulo de reescritura de Apache
RUN a2enmod rewrite

# Asegura permisos correctos
RUN chown -R www-data:www-data /var/www/html/

# Expone el puerto estándar
EXPOSE 80
