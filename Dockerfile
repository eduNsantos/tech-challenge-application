FROM php:8.4-cli

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
&& docker-php-ext-install -j$(nproc) pdo_mysql mbstring exif pcntl bcmath gd zip opcache \
&& pecl install pcov \
&& docker-php-ext-enable pcov \
&& curl -fsSL https://download.newrelic.com/php_agent/release/newrelic-php5-linux.tar.gz -o /tmp/newrelic.tar.gz \
&& mkdir -p /tmp/newrelic \
&& tar -xzf /tmp/newrelic.tar.gz -C /tmp/newrelic --strip-components=1 \
&& /tmp/newrelic/newrelic-install install \
&& rm -rf /tmp/newrelic /tmp/newrelic.tar.gz \
&& rm -rf /var/lib/apt/lists/* \
&& printf '%s\n' \
    'extension=newrelic.so' \
    'newrelic.enabled=${NEW_RELIC_ENABLED}' \
    'newrelic.appname="${NEW_RELIC_APP_NAME}"' \
    'newrelic.license="${NEW_RELIC_LICENSE_KEY}"' \
    'newrelic.daemon.address="${NEW_RELIC_DAEMON_ADDRESS}"' \
    'newrelic.logfile="/dev/stdout"' \
    > /usr/local/etc/php/conf.d/newrelic.ini

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /var/www/html

COPY . .

RUN composer install --no-interaction --prefer-dist --no-progress

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
