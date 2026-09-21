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
LABEL org.opencontainers.image.description="Laravel 13 portfolio site"

# System dependencies required by Laravel + PHP extensions
RUN apk add --no-cache \
    bash \
    curl \
    git \
    unzip \
    zip \
    # phpize build toolchain (autoconf/gcc/g++/make/...) — needed by `pecl
    # install redis`/`pecl install xdebug` below, which build from source.
    $PHPIZE_DEPS \
    linux-headers \
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

# WeasyPrint — every generatePdf() renders a document's composed HTML with
# the `weasyprint` CLI (see PdfRenderer). Production is not Docker (see
# CLAUDE.md: the real host runs apache + supervisord) and installs its own
# system WeasyPrint, so this is needed only so PDF generation works in the
# local container. Without it every PDF render fails with the renderer
# reported as unavailable while DOCX generation still succeeds, which
# surfaces as "approved, but document generation failed".
#
# The documents' own faces come from `@font-face` rules pointing at the
# static TTFs in resources/resume/assets/fonts (see HtmlDocumentComposer), not
# from anything installed here. But WeasyPrint's text layer (Pango/FontConfig)
# segfaults on startup if FontConfig has *no* face at all to fall back to,
# even one that ends up unused — font-dejavu exists purely to give it one.
RUN apk add --no-cache weasyprint font-dejavu

# poppler-utils / qpdf — pdffonts/pdftotext/pdfinfo and qpdf's stream
# decompression back the PDF fidelity test suite (embedded-font, page-count
# and content-stream assertions). Test tooling only; production never runs
# PHPUnit.
RUN apk add --no-cache poppler-utils qpdf

# Xdebug
RUN pecl install xdebug && docker-php-ext-enable xdebug

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# PHP configuration
COPY docker/php/php-dev.ini /usr/local/etc/php/conf.d/99-app-dev.ini
COPY docker/php/xdebug.ini  /usr/local/etc/php/conf.d/99-xdebug.ini

# Nginx config
COPY docker/nginx/default.conf /etc/nginx/http.d/default.conf

# PHP-FPM pool: listen on the unix socket nginx's fastcgi_pass expects,
# instead of the base image's default 127.0.0.1:9000. Both files are needed:
# www.conf carries the pool's other settings, zzz-listen.conf re-asserts the
# socket after the base image's own zz-docker.conf (loaded later
# alphabetically) resets `listen` back to 9000.
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/www.conf
COPY docker/php/zzz-listen.conf /usr/local/etc/php-fpm.d/zzz-listen.conf

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
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/www.conf
COPY docker/php/zzz-listen.conf /usr/local/etc/php-fpm.d/zzz-listen.conf
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
