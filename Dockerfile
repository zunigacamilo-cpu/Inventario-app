FROM php:8.1-apache

# libonig-dev: requerido para compilar mbstring (mb_substr en reservas/comunicados)
RUN apt-get update && apt-get install -y --no-install-recommends libonig-dev \
    && docker-php-ext-install mysqli pdo pdo_mysql mbstring \
    && rm -rf /var/lib/apt/lists/*

# Copia todo el proyecto al servidor web
COPY . /var/www/html/

# Permisos correctos
RUN chmod -R 755 /var/www/html/

EXPOSE 80
