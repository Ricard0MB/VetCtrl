# Usa PHP con Apache
FROM php:8.2-apache

# Instalar extensiones necesarias
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Instalar utilidades necesarias para Composer (unzip, zip, git)
RUN apt-get update && apt-get install -y \
    unzip \
    zip \
    git \
    && rm -rf /var/lib/apt/lists/*

# Instalar Composer (usando la imagen oficial)
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copiar los archivos del proyecto
COPY . /var/www/html/

# Establecer el directorio de trabajo
WORKDIR /var/www/html/

# Instalar dependencias de Composer (sin dev y con autoload optimizado para producción)
RUN composer install --no-dev --optimize-autoloader

# ⚠️ Si quieres que la raíz web sea la carpeta 'public', descomenta la siguiente línea
# RUN sed -i 's|/var/www/html|/var/www/html/public|g' /etc/apache2/sites-available/000-default.conf

# Habilitar el módulo rewrite de Apache
RUN a2enmod rewrite

# Ajustar permisos para que Apache pueda leer/escribir
RUN chown -R www-data:www-data /var/www/html/

# Exponer el puerto 80
EXPOSE 80
