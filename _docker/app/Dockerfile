# Образ для локального запуска в Docker (docker-compose.yml).
# Код в образ не копируется: он подключается томом. Зависимости ставятся
# на компьютере (composer install, npm install) или внутри контейнера:
# docker compose exec app composer install
# Для прод-сервера используется _docker/app/Dockerfile.prod.
FROM php:8.4-fpm

# Системные зависимости и расширения PHP (GD с JPEG и WebP — для фото пользователей)
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libjpeg62-turbo-dev \
    libwebp-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install \
        pdo_mysql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Лимит загрузки файла: фотография пользователя до 2 МБ проверяется в Laravel,
# а PHP по умолчанию отклоняет файлы больше 2M ещё до проверки
RUN echo 'upload_max_filesize = 3M' > /usr/local/etc/php/conf.d/uploads.ini

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

EXPOSE 9000

CMD ["php-fpm"]
