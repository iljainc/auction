FROM php:8.2-fpm

# Установка зависимостей
RUN apt-get update && apt-get install -y \
    libmemcached-dev \
    zlib1g-dev \
    gnupg \
    libcurl4-openssl-dev \
    apt-utils \
    libpq-dev \
    libpng-dev \
    libzip-dev \
    zip unzip \
    git \
    cron \
    htop \

    build-essential \
    systemd-timesyncd \
    rsyslog \
    procps \
    && pecl install memcached \
    && docker-php-ext-enable memcached \
    && docker-php-ext-install pdo_mysql \
    && docker-php-ext-install bcmath \
    && docker-php-ext-install gd \
    && docker-php-ext-install zip \
    && docker-php-ext-install curl \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

# Конфигурация rsyslog для логирования cron
RUN echo "cron.* /var/log/cron.log" >> /etc/rsyslog.conf

RUN echo "* * * * * root /usr/local/bin/php /var/www/artisan schedule:run >> /var/www/storage/logs/cron.log 2>&1" >> /etc/crontab \
    && echo "*/5 * * * * root /usr/local/bin/php /var/www/monitor_cron_jobs.php >> /var/www/storage/logs/cron_monitor.log 2>&1" >> /etc/crontab \
    && echo "*/30 * * * * root timedatectl set-ntp true >> /var/www/storage/logs/timesyncd.log 2>&1" >> /etc/crontab

# Установка рабочей директории
WORKDIR /var/www

# Установка Node.js
RUN curl -fsSL https://deb.nodesource.com/setup_18.x | bash - \
    && apt-get install -y nodejs

# Копирование package.json и установка зависимостей
COPY package*.json ./
RUN npm install

# Копирование всего кода проекта
COPY . .

# Сборка фронтенда
RUN npm run build

# Установка Composer
ENV COMPOSER_ALLOW_SUPERUSER=1
RUN curl -sS https://getcomposer.org/installer | php -- \
    --filename=composer \
    --install-dir=/usr/local/bin

# Копирование настроек PHP
COPY ./_docker/app/php.ini /usr/local/etc/php/conf.d/php.ini

CMD ["sh", "-c", "rsyslogd && cron && php-fpm"]

