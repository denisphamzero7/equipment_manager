#!/bin/sh
set -e

echo "=== KHỞI ĐỘNG EQUIPMENT MANAGER CONTAINER ==="

# Tạo thư mục log nếu chưa có
mkdir -p /var/www/html/storage/logs
mkdir -p /var/www/html/storage/framework/cache/data
mkdir -p /var/www/html/storage/framework/sessions
mkdir -p /var/www/html/storage/framework/views
mkdir -p /var/www/html/storage/app/public

# Tạo sẵn các file log để tránh permission denied
touch /var/www/html/storage/logs/laravel.log
touch /var/www/html/storage/logs/reverb.log
touch /var/www/html/storage/logs/worker.log
touch /var/www/html/storage/logs/scheduler.log

# Liên kết storage nếu chưa liên kết
php /var/www/html/artisan storage:link --force || true

# Xóa cache cũ trước (đảm bảo không dùng config từ lần build trước)
php /var/www/html/artisan optimize:clear || true

# Đảm bảo quyền ghi cho TẤT CẢ user trên storage và cache (fix Render permission)
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache || true
chmod -R 777 /var/www/html/storage /var/www/html/bootstrap/cache || true

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

# Tự động thực thi migration và seed dữ liệu nếu có Database
if [ -n "$DB_HOST" ]; then
    echo "Đang tự động thực thi database migrations..."
    php /var/www/html/artisan migrate --force || true

    echo "Đang kiểm tra và khởi tạo dữ liệu mẫu..."
    php -r "
        require '/var/www/html/vendor/autoload.php';
        \$app = require_once '/var/www/html/bootstrap/app.php';
        \$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('users') && \App\Modules\Core\Models\User::count() === 0) {
                echo 'Bảng users đang trống. Đang nạp tài khoản admin và dữ liệu mẫu...' . PHP_EOL;
                \Illuminate\Support\Facades\Artisan::call('db:seed', ['--force' => true]);
                echo \Illuminate\Support\Facades\Artisan::output();
            } else {
                echo 'Dữ liệu người dùng đã tồn tại, không cần seed lại.' . PHP_EOL;
            }
        } catch (Throwable \$e) {
            echo 'Thông báo: ' . \$e->getMessage() . PHP_EOL;
        }
    " || true
fi

# Khám phá packages và cache route/view (KHÔNG cache config để SSL runtime luôn đúng)
php /var/www/html/artisan package:discover --ansi || true
php /var/www/html/artisan route:cache || true
php /var/www/html/artisan view:cache || true

echo "Khởi động Supervisor quản lý Nginx, PHP-FPM, Reverb, Workers..."
sed -i "s/listen 80;/listen ${PORT:-80};/g" /etc/nginx/nginx.conf
exec /usr/bin/supervisord -c /etc/supervisord.conf
