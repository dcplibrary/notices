FROM php:8.3-apache

COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/

RUN apt-get update && \
    mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" && \
    curl -sL https://deb.nodesource.com/setup_20.x | bash - && \
    apt-get update && \
    apt-get upgrade -y && \
    apt-get install -y nodejs libicu-dev && \
    apt-get install -y zlib1g-dev mysql-client-* libzip-dev zip nano && \
    apt-get install -y freetds-dev freetds-bin freetds-common && \
    docker-php-ext-configure pdo_dblib --with-libdir=/lib/x86_64-linux-gnu && \
    docker-php-ext-install pdo_dblib && \
    install-php-extensions imap && \
    docker-php-ext-configure zip && \
    docker-php-ext-install zip mysqli pdo_mysql intl ftp && \
    a2enmod rewrite && \
    sed -i 's!/var/www/html!/var/www/public!g' /etc/apache2/sites-enabled/000-default.conf && \
    mv /var/www/html /var/www/public && \
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer && \
    echo 'AllowEncodedSlashes On' >> /etc/apache2/apache2.conf && \
    rm -rf /var/lib/apt/lists/*

RUN php -m | grep -E "pdo_dblib|ftp|imap" || echo "Warning: Required extensions not found"
