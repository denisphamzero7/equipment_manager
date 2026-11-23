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
    RETRY_COUNT=0
    until php -r "
        \$host = getenv('DB_HOST');
        \$port = getenv('DB_PORT') ?: 3306;
        \$user = getenv('DB_USERNAME');
        \$pass = getenv('DB_PASSWORD');
        \$opts = [];
        if (file_exists('/etc/ssl/certs/ca-certificates.crt')) {
            \$opts[PDO::MYSQL_ATTR_SSL_CA] = '/etc/ssl/certs/ca-certificates.crt';
        }
        try {
            new PDO(\"mysql:host=\$host;port=\$port\", \$user, \$pass, \$opts);
            exit(0);
        } catch (Throwable \$e) {
            echo 'Thông báo kết nối: ' . \$e->getMessage() . PHP_EOL;
            exit(1);
        }
    " || [ "$DB_CONNECTION" = "sqlite" ]; do
        RETRY_COUNT=$((RETRY_COUNT+1))
        if [ $RETRY_COUNT -gt 15 ]; then
            echo "Cảnh báo: Không thể kết nối Database sau 30 giây. Bỏ qua để khởi động tiếp..."
            break
        fi
        echo "Database chưa sẵn sàng, chờ 2 giây... (Lần $RETRY_COUNT/15)"
        sleep 2
    done
    echo "Tiếp tục quy trình khởi động..."
fi

# Chạy migration nếu cờ RUN_MIGRATIONS=true
if [ "$RUN_MIGRATIONS" = "true" ]; then
    echo "Đang thực thi database migrations..."
    php /var/www/html/artisan migrate --force
fi

# Chạy seed dữ liệu mẫu nếu cờ RUN_SEEDERS=true
if [ "$RUN_SEEDERS" = "true" ]; then
    echo "Đang thực thi database seeders..."
    php /var/www/html/artisan db:seed --force || true
fi

# Khám phá packages và cache Laravel
php /var/www/html/artisan package:discover --ansi || true
php /var/www/html/artisan config:cache || true
php /var/www/html/artisan route:cache || true
php /var/www/html/artisan view:cache || true

echo "Khởi động Supervisor quản lý Nginx, PHP-FPM, Reverb, Workers..."
sed -i "s/listen 80;/listen ${PORT:-80};/g" /etc/nginx/nginx.conf
exec /usr/bin/supervisord -c /etc/supervisord.conf
