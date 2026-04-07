FROM php:8.1-apache

# Instala extensiones necesarias para PDO + MySQL
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Copia todo el proyecto al servidor web
COPY . /var/www/html/

# Permisos correctos
RUN chmod -R 755 /var/www/html/

EXPOSE 80
