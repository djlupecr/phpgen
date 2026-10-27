FROM php:8.2-apache

# Extensões PHP necessárias
RUN apt-get update && apt-get install -y \
        libzip-dev libpng-dev libjpeg-dev libfreetype6-dev \
        libonig-dev unzip git curl \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_mysql gd zip mbstring \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Apache mod_rewrite
RUN a2enmod rewrite

# Código
WORKDIR /var/www/html
COPY . .

# Instalar dependências
RUN composer install --optimize-autoloader --classmap-authoritative --no-interaction

# Permissões
RUN mkdir -p public/uploads generated \
    && chown -R www-data:www-data public/uploads generated \
    && chmod -R 775 public/uploads generated

# Apache: DocumentRoot = public/ e AllowOverride All
RUN sed -ri -e 's!/var/www/html!/var/www/html/public!g' \
        /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!/var/www/html/public!g' \
        /etc/apache2/apache2.conf \
        /etc/apache2/conf-available/*.conf \
    && sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# [FIX] Apache: repassar o header Authorization para o PHP (crucial para JWT)
RUN echo "SetEnvIf Authorization \"(.*)\" HTTP_AUTHORIZATION=\$1" >> /etc/apache2/apache2.conf

# [FIX] Garante que o mod_headers está ativo (para CORS e headers customizados)
RUN a2enmod headers

EXPOSE 80