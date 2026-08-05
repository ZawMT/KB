# Development image for a Laravel app.
# Copy this file into the root of the Laravel project (next to artisan).

FROM php:8.4-cli

# UID/GID of the host user, so files created inside the container are editable outside it.
# Override at build time:  docker compose build --build-arg UID=$(id -u)
ARG UID=1000
ARG GID=1000

# System packages needed to build the PHP extensions below.
RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        curl \
        unzip \
        ca-certificates \
        libzip-dev \
        libicu-dev \
        libpng-dev \
        libjpeg-dev \
        libfreetype6-dev \
    && rm -rf /var/lib/apt/lists/*

# PHP extensions. pdo_mysql and mbstring are required by Laravel;
# the rest are what a typical app ends up needing.
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql \
        mbstring \
        zip \
        intl \
        gd \
        bcmath \
        opcache

# The redis extension is not bundled with PHP, so it comes from PECL.
RUN pecl install redis && docker-php-ext-enable redis

# Composer, copied from its official image rather than installed by script.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Node, for Vite asset building (npm run dev / npm run build).
RUN curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && rm -rf /var/lib/apt/lists/*

# A non-root user matching the host, so the bind-mounted files stay writable both sides.
RUN groupadd --force -g "$GID" app \
    && useradd -ms /bin/bash --no-user-group -g "$GID" -u "$UID" app

WORKDIR /var/www/html

USER app

EXPOSE 8000

# Overridden by docker-compose.yml; kept here so `docker run` alone works too.
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
