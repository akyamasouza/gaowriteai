# Use PHP 8.2 com Apache
FROM php:8.2-apache

# Instalar dependências do sistema
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip

# Limpar cache
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Instalar dependências para ZIP
RUN apt-get update && apt-get install -y libzip-dev

# Instalar extensões PHP
RUN docker-php-ext-install mbstring exif pcntl bcmath gd zip

# Instalar Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Configurar diretório de trabalho
WORKDIR /var/www

# Copiar código da aplicação
COPY . /var/www

# Instalar dependências do Laravel
RUN composer install

# Criar banco SQLite se não existir
RUN mkdir -p /var/www/database && touch /var/www/database/database.sqlite

# Criar banco SQLite se não existir
RUN mkdir -p /var/www/database && touch /var/www/database/database.sqlite

# Configurar permissões
RUN chown -R www-data:www-data /var/www \
    && chmod -R 755 /var/www/storage \
    && chmod -R 755 /var/www/bootstrap/cache \
    && chmod 664 /var/www/database/database.sqlite

# Criar link simbólico para storage
RUN php artisan storage:link || true \
    && chmod 664 /var/www/database/database.sqlite

# Habilitar mod_rewrite
RUN a2enmod rewrite

# Configurar DocumentRoot do Apache
ENV APACHE_DOCUMENT_ROOT /var/www/public

RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Expor porta 80
EXPOSE 80

# Iniciar Apache
CMD ["apache2-foreground"]