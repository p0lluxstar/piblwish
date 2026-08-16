FROM php:8.4-fpm 

# Установка системных зависимостей
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    && apt-get clean

# Установка расширений PHP
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

# Установка Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Настройка рабочей директории
WORKDIR /var/www

# Копирование файлов проекта
COPY . /var/www

# Установка зависимостей Composer
RUN composer install --no-interaction --optimize-autoloader --no-dev

# Настройка прав
RUN chown -R www-data:www-data /var/www \
    && chmod -R 755 /var/www/storage \
    && chmod -R 755 /var/www/bootstrap/cache

# Ожидание БД и запуск миграций (опционально)
# COPY _docker/app/wait-for-it.sh /usr/local/bin/
# RUN chmod +x /usr/local/bin/wait-for-it.sh

# Команда запуска
CMD ["php-fpm"]

EXPOSE 9000