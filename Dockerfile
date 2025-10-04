FROM php:8.2-apache

# Установка расширений PHP
RUN docker-php-ext-install pdo pdo_mysql mysqli

# Включаем mod_rewrite
RUN a2enmod rewrite

# Копируем файлы проекта
COPY . /var/www/html/

# Настройка прав доступа
RUN chown -R www-data:www-data /var/www/html

# Порт
EXPOSE 80

# Запуск Apache
CMD ["apache2-foreground"]
