FROM php:8.4-cli-alpine

# Install system utilities and extension installer
RUN apk add --no-cache \
    bash \
    curl \
    git \
    unzip \
    sqlite \
    sqlite-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libzip-dev \
    icu-dev \
    nodejs \
    npm

RUN git config --global --add safe.directory /var/www/html

ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/

RUN chmod +x /usr/local/bin/install-php-extensions && \
    install-php-extensions \
        bcmath \
        ctype \
        curl \
        dom \
        fileinfo \
        filter \
        gd \
        intl \
        mbstring \
        openssl \
        pcntl \
        pdo \
        pdo_mysql \
        pdo_sqlite \
        posix \
        redis \
        session \
        tokenizer \
        xml \
        zip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

EXPOSE 8000

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
