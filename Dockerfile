# ==========================================
# GIAI ĐOẠN 1: Build Vue 3 Frontend
# ==========================================
FROM node:20-alpine AS frontend-builder
WORKDIR /app/frontend

COPY Frontend/package*.json ./
RUN npm ci

COPY Frontend/ ./
# Nhận các biến build (mặc định lấy theo domain gốc "/")
ARG VITE_API_BASE_URL=""
ARG VITE_REVERB_APP_KEY="pnhau_core_reverb_key"
ARG VITE_REVERB_HOST=""
ARG VITE_REVERB_PORT=""
ARG VITE_REVERB_SCHEME="https"

RUN npm run build

# ==========================================
# GIAI ĐOẠN 2: PHP 8.2 Production + FPM + Nginx
# ==========================================
FROM php:8.2-fpm-alpine

# Cài đặt các gói hệ thống và thư viện cần thiết
RUN apk add --no-cache \
    nginx \
    supervisor \
    curl \
    git \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libzip-dev \
    icu-dev \
    oniguruma-dev \
    linux-headers \
    $PHPIZE_DEPS \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        zip \
        intl \
        opcache \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del --purge $PHPIZE_DEPS

# Cài đặt Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Thiết lập thư mục làm việc
WORKDIR /var/www/html

# Copy mã nguồn Backend
COPY Backend/composer*.json ./
RUN composer install --no-dev --optimize-autoloader --no-scripts --no-interaction

COPY Backend/ ./

# Copy build Frontend từ Giai đoạn 1 vào thư mục public của Laravel
COPY --from=frontend-builder /app/frontend/dist /var/www/html/public

# Cấu hình PHP Opcache & Production
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" && \
    echo "upload_max_filesize = 50M" >> "$PHP_INI_DIR/conf.d/custom.ini" && \
    echo "post_max_size = 50M" >> "$PHP_INI_DIR/conf.d/custom.ini" && \
    echo "memory_limit = 256M" >> "$PHP_INI_DIR/conf.d/custom.ini" && \
    echo "opcache.enable=1" >> "$PHP_INI_DIR/conf.d/custom.ini" && \
    echo "opcache.jit=tracing" >> "$PHP_INI_DIR/conf.d/custom.ini" && \
    echo "opcache.jit_buffer_size=64M" >> "$PHP_INI_DIR/conf.d/custom.ini"

# Copy cấu hình Nginx và Supervisor
COPY deploy/docker/nginx.conf /etc/nginx/nginx.conf
COPY deploy/docker/supervisord.conf /etc/supervisord.conf
COPY deploy/docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Cấp quyền thư mục storage và bootstrap/cache
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache && \
    chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 80 8090

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
