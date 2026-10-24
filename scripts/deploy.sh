#!/bin/bash
# ==============================================================================
# Script Triển khai / Cập nhật Production Equipment Hub
# Chạy script này trên Server: bash scripts/deploy.sh
# ==============================================================================

set -e

# Đổi về thư mục gốc dự án
PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$PROJECT_DIR"

echo "=========================================================="
echo "🚀 Bắt đầu quá trình Deploy Equipment Hub tại: $PROJECT_DIR"
echo "Thời gian: $(date '+%Y-%m-%d %H:%M:%S')"
echo "=========================================================="

# 1. Bật chế độ bảo trì tạm thời (nếu muốn)
# cd Backend && php artisan down --render="errors::503" --secret="secret-bypass-token" && cd ..

# 2. Đồng bộ mã nguồn từ nhánh main
echo "📥 1. Kéo mã nguồn mới nhất từ GitHub (origin/main)..."
git fetch origin main
git reset --hard origin/main

# 3. Build Giao diện Frontend Vue 3
echo "📦 2. Cài đặt dependencies và Build Frontend Production..."
cd Frontend
npm ci
npm run build
cd ..

# 4. Cài đặt Backend PHP Dependencies
echo "🐘 3. Cài đặt Composer Dependencies Backend (--no-dev)..."
cd Backend
composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

# 5. Chạy Migration CSDL
echo "🗄️ 4. Chạy Database Migrations..."
php artisan migrate --force

# 6. Tạo Symlink Storage nếu chưa có
php artisan storage:link || true

# 7. Xóa và tối ưu hóa Caches
echo "⚡ 5. Xây dựng lại Production Cache..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# 8. Phân quyền bảo mật thư mục
echo "🔒 6. Phân quyền thư mục Storage và Cache cho www-data..."
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

# 9. Khởi động lại Queue và WebSocket Reverb
echo "🔄 7. Khởi động lại Queue Worker & WebSocket Reverb..."
php artisan queue:restart || true

if command -v supervisorctl >/dev/null 2>&1; then
    sudo supervisorctl restart all || true
fi

# 10. Tắt chế độ bảo trì
# php artisan up

# 11. Nạp lại Nginx
echo "🌐 8. Nạp lại Web Server Nginx..."
sudo systemctl reload nginx || sudo service nginx reload || true

echo "=========================================================="
echo "🎉 DEPLOY THÀNH CÔNG HỆ THỐNG EQUIPMENT HUB!"
echo "Thời gian hoàn tất: $(date '+%Y-%m-%d %H:%M:%S')"
echo "=========================================================="
