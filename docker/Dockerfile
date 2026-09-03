FROM php:8.2-apache

RUN apt-get update && apt-get install -y --no-install-recommends \
        libicu-dev \
        libzip-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libonig-dev \
        libxml2-dev \
        git \
        unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        intl \
        zip \
        gd \
        pdo_mysql \
        bcmath \
        exif \
        mbstring \
        opcache \
    && rm -rf /var/lib/apt/lists/*
# Note: deliberately not purging the -dev packages afterwards — apt's
# --auto-remove pulls the matching runtime shared libs (libicu76, libpng16,
# libzip5, ...) down with them since nothing else in this image depends on
# them, which breaks these extensions at runtime. Fine to keep the extra
# ~100MB for a local test image.

RUN a2enmod rewrite

# cPanel's Apache honors .htaccess with AllowOverride All — mirror that here so
# the data/ and includes/ protections actually get exercised in local testing.
RUN { \
        echo '<Directory /var/www/html>'; \
        echo '    AllowOverride All'; \
        echo '    Require all granted'; \
        echo '</Directory>'; \
    } > /etc/apache2/conf-available/allow-override.conf \
    && a2enconf allow-override

EXPOSE 80
