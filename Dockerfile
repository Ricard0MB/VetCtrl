# Usa PHP con Apache
FROM php:8.2-apache

# Instalar extensiones necesarias
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Instalar utilidades para Composer
RUN apt-get update && apt-get install -y \
    unzip \
    zip \
    git \
    && rm -rf /var/lib/apt/lists/*

# Instalar Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copiar los archivos del proyecto
COPY . /var/www/html/

WORKDIR /var/www/html/

# Instalar dependencias de Composer
RUN composer install --no-dev --optimize-autoloader

# Habilitar módulo rewrite
RUN a2enmod rewrite

# 🔥 CAMBIO CRÍTICO: Apache debe escuchar en $PORT
# Render asigna un puerto dinámico en la variable de entorno $PORT
RUN sed -i 's/80/${PORT}/g' /etc/apache2/sites-available/000-default.conf \
    && sed -i 's/Listen 80/Listen ${PORT}/g' /etc/apache2/ports.conf \
    && echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Ajustar permisos
RUN chown -R www-data:www-data /var/www/html/

# NO expongas un puerto fijo (Render ignora EXPOSE)
# EXPOSE 80  ← borra o comenta esta línea

CMD ["apache2-foreground"]
