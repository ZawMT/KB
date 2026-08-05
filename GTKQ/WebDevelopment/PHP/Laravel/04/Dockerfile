# syntax=docker/dockerfile:1
#
# Production image for a Laravel app. Copy into the root of the Laravel project.
#
# This builds TWO images from one file, using build targets:
#
#   docker build --target app -t myapp:1.0.0     .
#   docker build --target web -t myapp-web:1.0.0 .
#
#   app  = PHP-FPM, runs the application code
#   web  = nginx, serves public/ and proxies .php to the app container
#
# Same architecture as lesson 03 (nginx in front, PHP-FPM behind), only now
# each part is an immutable image instead of a package installed on a server.

# ---------------------------------------------------------------------------
# Stage 1 — PHP dependencies
# ---------------------------------------------------------------------------
FROM composer:2 AS vendor

WORKDIR /app

# Copy only the dependency manifests first. Docker caches this layer, so
# editing application code does NOT re-run composer install — only a change
# to composer.json/composer.lock does.
COPY composer.json composer.lock ./

# --no-scripts because artisan does not exist yet at this point (no app code
# copied), and post-install scripts would try to run it.
RUN composer install \
        --no-dev \
        --no-scripts \
        --no-autoloader \
        --prefer-dist \
        --no-interaction

# Now the application code, and the optimised autoloader over the top.
COPY . .

RUN composer dump-autoload --no-dev --optimize --classmap-authoritative

# ---------------------------------------------------------------------------
# Stage 2 — frontend assets
# ---------------------------------------------------------------------------
FROM node:22-alpine AS assets

WORKDIR /app

# Same caching trick as above: lockfile first, then source.
COPY package.json package-lock.json ./
RUN npm ci

COPY . .

# Produces public/build/ — the fingerprinted CSS/JS that Vite emits.
RUN npm run build

# ---------------------------------------------------------------------------
# Stage 3 — the application image (PHP-FPM)
# ---------------------------------------------------------------------------
FROM php:8.4-fpm AS app

# Same extension set as the development image in lesson 02. Development and
# production drifting apart on extensions is a classic source of "works on my
# machine" — keep these two lists in sync.
RUN apt-get update && apt-get install -y --no-install-recommends \
        libzip-dev \
        libicu-dev \
        libpng-dev \
        libjpeg-dev \
        libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql \
        mbstring \
        zip \
        intl \
        gd \
        bcmath \
        opcache \
    && pecl install redis && docker-php-ext-enable redis \
    && apt-get purge -y --auto-remove \
    && rm -rf /var/lib/apt/lists/*

# Production OPcache settings — see opcache.ini for what and why.
COPY opcache.ini /usr/local/etc/php/conf.d/99-opcache.ini

# Production PHP defaults. The php.ini-production file ships inside the image;
# without this step PHP runs on its (development-oriented) built-in defaults.
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

WORKDIR /var/www/html

# Application code + vendor from stage 1, built assets from stage 2.
# Note what is NOT copied: .env, .git, node_modules — see .dockerignore.
COPY --chown=www-data:www-data --from=vendor /app ./
COPY --chown=www-data:www-data --from=assets /app/public/build ./public/build

# The only writable paths. Everything else in the image stays read-only, which
# is the point of an immutable image.
RUN chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwx storage bootstrap/cache

COPY --chmod=0755 entrypoint.sh /usr/local/bin/entrypoint.sh

USER www-data

EXPOSE 9000

ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]

# ---------------------------------------------------------------------------
# Stage 4 — the web image (nginx)
# ---------------------------------------------------------------------------
FROM nginx:1.27-alpine AS web

# nginx serves static files itself, so it needs its own copy of public/.
# It does NOT get vendor/, app/ or .env — there is nothing sensitive in this
# image, because there is nothing above the document root in it at all.
COPY --from=vendor  /app/public          /var/www/html/public
COPY --from=assets  /app/public/build    /var/www/html/public/build

COPY nginx.conf /etc/nginx/conf.d/default.conf

EXPOSE 80
