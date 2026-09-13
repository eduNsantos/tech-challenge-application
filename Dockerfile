FROM php:8.4-cli

ARG NEW_RELIC_AGENT_VERSION=12.10.0.39

ENV NEW_RELIC_ENABLED=true
ENV NEW_RELIC_APP_NAME="Tech Challenge POS"
ENV NEW_RELIC_DAEMON_ADDRESS=newrelic:31339

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
        opcache


RUN set -eux; \
    cd /tmp; \
    curl -fsSL \
      "https://download.newrelic.com/php_agent/release/newrelic-php5-${NEW_RELIC_AGENT_VERSION}-linux.tar.gz" \
      -o newrelic.tar.gz; \
    mkdir newrelic; \
    tar -xzf newrelic.tar.gz \
      -C newrelic \
      --strip-components=1; \
    NR_INSTALL_SILENT=1 \
      NR_INSTALL_USE_CP_NOT_LN=1 \
      ./newrelic/newrelic-install install; \
    rm -rf /tmp/newrelic /tmp/newrelic.tar.gz

# New Relic CLI
RUN curl -Ls \
    https://download.newrelic.com/install/newrelic-cli/scripts/install.sh \
    | bash


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