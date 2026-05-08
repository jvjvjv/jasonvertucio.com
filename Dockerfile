# ─────────────────────────────────────────────────────────────────────────────
# jasonvertucio.com – PHP 8.4 on Alpine Linux
# ─────────────────────────────────────────────────────────────────────────────
# Stages:
#   base        – shared system packages + PHP extensions
#   development – dev tools (Composer, npm, Xdebug), mounts source at runtime
#   production  – optimised, no dev dependencies, source baked in
# ─────────────────────────────────────────────────────────────────────────────

ARG PHP_VERSION=8.4
ARG ALPINE_VERSION=3.21

# ── base ────────────────────────────────────────────────────────────────────
FROM php:${PHP_VERSION}-fpm-alpine${ALPINE_VERSION} AS base

LABEL org.opencontainers.image.title="jasonvertucio.com"
LABEL org.opencontainers.image.description="Laravel 12 portfolio site"

# System dependencies required by Laravel + PHP extensions
RUN apk add --no-cache \
    bash \
    curl \
    git \
    unzip \
    zip \
    # GD / image processing
    freetype-dev \
    libjpeg-turbo-dev \
    libpng-dev \
    libwebp-dev \
    # Intl
    icu-dev \
    # Zip
    libzip-dev \
    # Mbstring
    oniguruma-dev \
    # GMP (WebAuthn / Keystone)
    gmp-dev \
    # Sodium (WebAuthn / Keystone)
    libsodium-dev \
    # MySQL client
    mysql-client \
    # Supervisor + Nginx
    supervisor \
    nginx \
    # Node 22 runtime (needed for DOCX generation scripts)
    nodejs \
    npm

# Configure and install PHP extensions
RUN docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg \
        --with-webp && \
    docker-php-ext-install -j$(nproc) \
        bcmath \
        exif \
        gd \
        gmp \
        intl \
        mbstring \
        opcache \
        pcntl \
        pdo \
        pdo_mysql \
        posix \
        sodium \
        zip

# Install Redis extension via PECL
RUN pecl install redis && docker-php-ext-enable redis

# ── development ─────────────────────────────────────────────────────────────
FROM base AS development

# Xdebug
RUN pecl install xdebug && docker-php-ext-enable xdebug

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# PHP configuration
COPY docker/php/php-dev.ini /usr/local/etc/php/conf.d/99-app-dev.ini
COPY docker/php/xdebug.ini  /usr/local/etc/php/conf.d/99-xdebug.ini

# Nginx config
COPY docker/nginx/default.conf /etc/nginx/http.d/default.conf

# Supervisor config
COPY docker/supervisord.dev.conf /etc/supervisor/conf.d/supervisord.conf

# Entrypoint
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

WORKDIR /var/www/app

EXPOSE 8003

ENTRYPOINT ["/entrypoint.sh"]

# ── production ──────────────────────────────────────────────────────────────
FROM base AS production

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY docker/php/php-prod.ini /usr/local/etc/php/conf.d/99-app-prod.ini
COPY docker/nginx/default.conf /etc/nginx/http.d/default.conf
COPY docker/supervisord.prod.conf /etc/supervisor/conf.d/supervisord.conf

WORKDIR /var/www/app
COPY . .

RUN composer install \
        --no-dev \
        --no-interaction \
        --prefer-dist \
        --optimize-autoloader

RUN npm ci && npm run build

# Keep node_modules for DOCX generation scripts (scripts/generate-resume.js)

RUN chown -R www-data:www-data storage bootstrap/cache && \
    chmod -R 775 storage bootstrap/cache

COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 8003

ENTRYPOINT ["/entrypoint.sh"]
