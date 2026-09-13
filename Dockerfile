FROM php:8.4-cli

ARG NEW_RELIC_ENABLED=true
ARG NEW_RELIC_APP_NAME="Tech Challenge POS"
ARG NEW_RELIC_LICENSE_KEY
ARG NEW_RELIC_DAEMON_ADDRESS=newrelic:31339
ARG NEW_RELIC_KEY

ENV NEW_RELIC_ENABLED=${NEW_RELIC_ENABLED}
ENV NEW_RELIC_APP_NAME=${NEW_RELIC_APP_NAME}
ENV NEW_RELIC_LICENSE_KEY=${NEW_RELIC_LICENSE_KEY:-${NEW_RELIC_KEY}}
ENV NEW_RELIC_DAEMON_ADDRESS=${NEW_RELIC_DAEMON_ADDRESS}
ENV NEW_RELIC_KEY=${NEW_RELIC_KEY}

RUN apt-get update && apt-get install -y \
    git \
    curl \
    wget \
    ca-certificates \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    libonig-dev \
    zip \
    unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        zip \
        opcache \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /var/www/html

COPY . .

RUN composer install \
    --no-interaction \
    --prefer-dist \
    --no-progress

RUN curl -fsSL https://download.newrelic.com/php_agent/release/newrelic-php5-linux.tar.gz -o /tmp/newrelic.tar.gz \
    && mkdir -p /tmp/newrelic-install \
    && tar -xzf /tmp/newrelic.tar.gz -C /tmp/newrelic-install --strip-components=1 \
    && NR_INSTALL_SILENT=true NR_INSTALL_PATH=/usr/local/lib/php/extensions/ NR_INSTALL_LOG_FILE=/tmp/newrelic-install.log /tmp/newrelic-install/newrelic-install install \
    && rm -rf /tmp/newrelic.tar.gz /tmp/newrelic-install /tmp/newrelic-install.log

COPY docker/newrelic.ini /usr/local/etc/php/conf.d/newrelic.ini

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]