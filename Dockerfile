FROM php:8.4-cli

ARG NEW_RELIC_ENABLED=true
ARG NEW_RELIC_APP_NAME="Tech Challenge POS"
ARG NEW_RELIC_LICENSE_KEY
ARG NEW_RELIC_DAEMON_ADDRESS=newrelic:31339
ARG NEW_RELIC_KEY

ENV NEW_RELIC_ENABLED=${NEW_RELIC_ENABLED}
ENV NEW_RELIC_APP_NAME=${NEW_RELIC_APP_NAME}
ENV NEW_RELIC_LICENSE_KEY=${NEW_RELIC_LICENSE_KEY:-${NEW_RELIC_KEY}}
ENV NR_INSTALL_KEY=${NEW_RELIC_LICENSE_KEY:-${NEW_RELIC_KEY}}
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
    && curl -fsSL https://download.newrelic.com/php_agent/release/newrelic-php5-12.10.0.39-linux.tar.gz -o /tmp/newrelic.tar.gz \
    && mkdir -p /tmp/newrelic \
    && tar -xzf /tmp/newrelic.tar.gz -C /tmp/newrelic --strip-components=1 \
    && NR_INSTALL_SILENT=yes NR_INSTALL_KEY=${NEW_RELIC_LICENSE_KEY} /tmp/newrelic/newrelic-install install \
    && curl -Ls https://download.newrelic.com/install/newrelic-cli/scripts/install.sh | bash \
    && rm -rf /tmp/newrelic /tmp/newrelic.tar.gz \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /var/www/html

COPY . .

RUN composer install \
    --no-interaction \
    --prefer-dist \
    --no-progress

COPY docker/newrelic.ini /usr/local/etc/php/conf.d/newrelic.ini
COPY docker/entrypoint.sh /usr/local/bin/docker-entrypoint
RUN chmod +x /usr/local/bin/docker-entrypoint

ENTRYPOINT ["/usr/local/bin/docker-entrypoint"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]