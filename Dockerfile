FROM php:8.4-cli

# 1. Instalar dependencias del sistema, extensiones de PHP y NODE.JS con NPM
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    libpq-dev \
    libicu-dev \
    unzip \
    git \
    curl \
    && curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd pdo pdo_mysql pdo_pgsql zip bcmath intl pcntl

# 2. Instalar Composer desde su imagen oficial
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 3. Configurar el directorio de trabajo y copiar archivos
WORKDIR /var/www/html
COPY . .

# 4. Instalar las dependencias de PHP para producción (sin scripts automáticos)
RUN composer install --no-dev --optimize-autoloader --no-scripts

# 5. 🔥 COMPILAR EL FRONTEND (VITE) PARA PRODUCCIÓN
# Esto genera los archivos CSS/JS reales que Laravel necesita para no dar Error 500
RUN npm install && npm run build

# 6. Dar los permisos correctos a las carpetas de almacenamiento de Laravel
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# 7. Exponer el puerto de Railway
EXPOSE 80

# 8. Arrancar el servidor forzando la variable de CORS
CMD ["sh", "-c", "CORS_ALLOWED_ORIGINS=* php artisan serve --host=0.0.0.0 --port=80"]