FROM php:8.4-fpm-alpine

# --- Stage 1: Build frontend assets ---
FROM node:22-alpine AS node-builder

WORKDIR /build

COPY app/package.json app/package-lock.json* ./
RUN npm install --ignore-scripts

COPY app/vite.config.js ./
COPY app/resources ./resources

RUN npm run build
# Output lands in /build/public/build/

# --- Stage 2: PHP app ---
FROM php:8.4-fpm-alpine

RUN apk add --no-cache \
    bash \
    curl \
    git \
    libzip-dev \
    oniguruma-dev \
    unzip \
    icu-dev \
    && docker-php-ext-install \
    bcmath \
    intl \
    mbstring \
    pdo_mysql \
    zip

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /var/www/html

COPY docker/php/conf.d/local.ini /usr/local/etc/php/conf.d/local.ini
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

# Copy compiled assets from the node stage
COPY --from=node-builder /build/public/build ./public/build

ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]