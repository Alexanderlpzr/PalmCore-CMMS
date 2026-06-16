FROM php:8.4-apache

# 1. Instalar dependencias del sistema y extensiones de PHP necesarias
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    libpq-dev \
    libicu-dev \
    unzip \
    git \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd pdo pdo_mysql pdo_pgsql zip bcmath intl pcntl

# 🔥 SOLUCIÓN AL ERROR MPM: Desactivar el módulo conflictivo mpm_event y forzar mpm_prefork
RUN a2dismod mpm_event && a2enmod mpm_prefork

# 2. Habilitar mod_rewrite para Apache (esencial para las rutas de Laravel)
RUN a2enmod rewrite

# 3. Apuntar Apache directamente a la carpeta /public de Laravel
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# 4. Instalar Composer desde su imagen oficial
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 5. Copiar los archivos de tu proyecto al contenedor
WORKDIR /var/www/html
COPY . .

# 6. Instalar las dependencias de PHP para producción evitando ejecutar scripts automáticos
RUN composer install --no-dev --optimize-autoloader --no-scripts

# 7. Dar los permisos correctos a las carpetas de almacenamiento de Laravel
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 80