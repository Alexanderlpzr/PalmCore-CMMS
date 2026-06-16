FROM php:8.4-cli

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

# 2. Instalar Composer desde su imagen oficial
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 3. Configurar el directorio de trabajo y copiar archivos
WORKDIR /var/www/html
COPY . .

# 4. Instalar las dependencias de PHP para producción evitando ejecutar scripts automáticos en el build
RUN composer install --no-dev --optimize-autoloader --no-scripts

# 5. Dar los permisos correctos a las carpetas de almacenamiento de Laravel
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# 6. Exponer el puerto que usará el servidor interno
EXPOSE 80

# 7. 🔥 SOLUCIÓN DEFINITIVA: Forzar la variable CORS directamente en el arranque
CMD ["sh", "-c", "CORS_ALLOWED_ORIGINS=* php artisan serve --host=0.0.0.0 --port=80"]