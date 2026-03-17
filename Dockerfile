# Usa PHP con Apache
FROM php:8.2-apache

# Instalar extensiones necesarias (incluyendo zip, unzip para Composer)
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    && docker-php-ext-install mysqli pdo pdo_mysql zip \
    && apt-get clean

# Instalar Composer manualmente
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
    && php composer-setup.php --install-dir=/usr/local/bin --filename=composer \
    && php -r "unlink('composer-setup.php');"

# Copiar los archivos del proyecto
COPY . /var/www/html/

# Establecer el directorio de trabajo
WORKDIR /var/www/html/

# Instalar dependencias de Composer con salida detallada (para debug)
RUN composer install --no-dev --optimize-autoloader -vvv

# ⚠️ Si quieres que la raíz web sea la carpeta 'public', descomenta la siguiente línea
# RUN sed -i 's|/var/www/html|/var/www/html/public|g' /etc/apache2/sites-available/000-default.conf

# Habilitar el módulo rewrite de Apache
RUN a2enmod rewrite

# Ajustar permisos
RUN chown -R www-data:www-data /var/www/html/

EXPOSE 80
