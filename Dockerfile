FROM php:8.2-apache

# Установка системных зависимостей
RUN apt-get update && apt-get install -y \
    libzip-dev \
    libxml2-dev \
    unzip \
    && rm -rf /var/lib/apt/lists/*

# Установка расширений PHP (включая dom, xml, zip для PHPSpreadsheet)
RUN docker-php-ext-install pdo pdo_mysql mysqli dom xml zip

# Установка Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Включаем mod_rewrite
RUN a2enmod rewrite

# Копируем файлы проекта
COPY . /var/www/html/

# Установка PHP зависимостей
WORKDIR /var/www/html
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Настройка прав доступа
RUN chown -R www-data:www-data /var/www/html

# Порт
EXPOSE 80

# Запуск Apache
CMD ["apache2-foreground"]
