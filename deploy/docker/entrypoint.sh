#!/bin/sh
set -e

echo "=== KHỞI ĐỘNG EQUIPMENT MANAGER CONTAINER ==="

# Tạo thư mục log nếu chưa có
mkdir -p /var/www/html/storage/logs
mkdir -p /var/www/html/storage/framework/cache
mkdir -p /var/www/html/storage/framework/sessions
mkdir -p /var/www/html/storage/framework/views

# Liên kết storage nếu chưa liên kết
php /var/www/html/artisan storage:link --force || true

# Đảm bảo quyền ghi cho www-data trên thư mục storage và cache
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache || true
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache || true

# Chờ Database sẵn sàng nếu có cấu hình DB
if [ -n "$DB_HOST" ]; then
    echo "Đang kiểm tra kết nối Database tại $DB_HOST:${DB_PORT:-3306}..."
    until php -r "try { new PDO('mysql:host='.getenv('DB_HOST').';port='.(getenv('DB_PORT') ?: 3306), getenv('DB_USERNAME'), getenv('DB_PASSWORD')); exit(0); } catch (Throwable \$e) { exit(1); }" 2>/dev/null || [ "$DB_CONNECTION" = "sqlite" ]; do
        echo "Database chưa sẵn sàng, chờ 2 giây..."
        sleep 2
    done
    echo "Database đã sẵn sàng!"
fi

# Chạy migration nếu cờ RUN_MIGRATIONS=true
if [ "$RUN_MIGRATIONS" = "true" ]; then
    echo "Đang thực thi database migrations..."
    php /var/www/html/artisan migrate --force
fi

# Khám phá packages và cache Laravel
php /var/www/html/artisan package:discover --ansi || true
php /var/www/html/artisan config:cache || true
php /var/www/html/artisan route:cache || true
php /var/www/html/artisan view:cache || true

echo "Khởi động Supervisor quản lý Nginx, PHP-FPM, Reverb, Workers..."
exec /usr/bin/supervisord -c /etc/supervisord.conf
