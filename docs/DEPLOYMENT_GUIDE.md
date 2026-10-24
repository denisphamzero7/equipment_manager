# 🚀 HƯỚNG DẪN TRIỂN KHAI TỰ ĐỘNG (CI/CD DEPLOYMENT GUIDE)

Tài liệu hướng dẫn thiết lập hệ thống **CI/CD tự động hóa 100% qua GitHub Actions** cho dự án **Equipment Hub**. Mỗi khi lập trình viên hoặc AI đẩy mã nguồn (`git push`) lên nhánh `main`, hệ thống sẽ tự động build Frontend, kiểm tra Backend, đồng bộ lên máy chủ VPS qua SSH và nạp lại toàn bộ dịch vụ mà không làm gián đoạn người dùng (Zero-Downtime Deployment).

---

## 📌 1. Danh sách Tệp Triển Khai trong Dự Án

| Tệp tin | Vị trí | Chức năng |
| :--- | :--- | :--- |
| **Workflow CI/CD** | `.github/workflows/deploy.yml` | Kịch bản GitHub Actions tự động Build & Deploy khi push vào `main` |
| **Kịch bản Deploy Server** | `scripts/deploy.sh` | Shell script chạy các bước cập nhật mã nguồn, migrate, cache trên server |
| **Cấu hình Nginx** | `deploy/nginx/equipment_manager.conf` | Cấu hình máy chủ Web, SSL HTTPS, Proxy API, Static Cache & WebSocket WSS |
| **Cấu hình Supervisor** | `deploy/supervisor/equipment_manager.conf` | Quản lý tiến trình chạy ngầm 24/7 cho Reverb WebSocket, Queue & Cronjob |

---

## 🔑 2. Cấu hình GitHub Secrets (Bắt buộc)

Trên giao diện GitHub Repository của bạn:
1. Truy cập: **Settings** $\rightarrow$ **Secrets and variables** $\rightarrow$ **Actions**.
2. Nhấn **New repository secret** và thêm đủ 5 biến bí mật sau:

| Tên Secret | Ví dụ giá trị | Giải thích |
| :--- | :--- | :--- |
| `SSH_HOST` | `103.155.xx.xx` hoặc `app.yourhotel.com` | Địa chỉ IP hoặc tên miền của máy chủ VPS |
| `SSH_USER` | `ubuntu` (hoặc `root`, `deployer`) | Tên người dùng SSH trên VPS |
| `SSH_KEY` | `-----BEGIN OPENSSH PRIVATE KEY-----...` | Nội dung khóa riêng Private Key để đăng nhập SSH |
| `SSH_PORT` | `22` | Cổng SSH của server (mặc định là `22`) |
| `DEPLOY_PATH` | `/var/www/equipment_manager` | Thư mục đặt mã nguồn dự án trên server |

---

## 🖥️ 3. Chuẩn bị Máy Chủ VPS Lần Đầu (Ubuntu 22.04 / 24.04)

Nếu bạn vừa thuê VPS mới, hãy chạy các lệnh sau một lần duy nhất để cài đặt môi trường cần thiết:

### Bước 1: Cài đặt Nginx, PHP 8.3, Composer, Node.js, Redis & Supervisor
```bash
# Cập nhật hệ điều hành
sudo apt update && sudo apt upgrade -y

# Cài đặt Nginx, Git, Curl, Unzip, Supervisor, Redis
sudo apt install -y nginx git curl unzip supervisor redis-server

# Thêm PPA PHP 8.3
sudo apt install -y software-properties-common
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update

# Cài đặt PHP 8.3 và các extension thiết yếu cho Laravel & Spatie Media
sudo apt install -y php8.3-fpm php8.3-cli php8.3-mysql php8.3-curl php8.3-gd \
    php8.3-mbstring php8.3-xml php8.3-zip php8.3-bcmath php8.3-intl php8.3-redis \
    php8.3-sockets php8.3-exif

# Cài đặt Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Cài đặt Node.js 20 LTS
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs
```

---

### Bước 2: Clone mã nguồn dự án về thư mục `/var/www`
```bash
cd /var/www
sudo git clone https://github.com/denisphamzero7/equipment_manager.git
cd equipment_manager

# Phân quyền thư mục
sudo chown -R $USER:www-data /var/www/equipment_manager
sudo chmod -R 775 /var/www/equipment_manager/Backend/storage /var/www/equipment_manager/Backend/bootstrap/cache
```

---

### Bước 3: Thiết lập file môi trường `Backend/.env` trên VPS
Tạo file `.env` cho Backend tại `/var/www/equipment_manager/Backend/.env`:
```env
APP_NAME="Equipment Hub"
APP_ENV=production
APP_KEY=base64:your_generated_app_key_here
APP_DEBUG=false
APP_URL=https://your-domain.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=equipment_manager
DB_USERNAME=your_db_user
DB_PASSWORD=your_secure_password

CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis

REDIS_HOST=127.0.0.1
REDIS_PORT=6379

BROADCAST_CONNECTION=reverb
REVERB_APP_ID=758493
REVERB_APP_KEY=pnhau_core_reverb_key
REVERB_APP_SECRET=pnhau_core_reverb_secret
REVERB_HOST=127.0.0.1
REVERB_PORT=8090
REVERB_SCHEME=http

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="your-domain.com"
VITE_REVERB_PORT=443
VITE_REVERB_SCHEME="https"
```

Khởi tạo ứng dụng lần đầu:
```bash
cd /var/www/equipment_manager/Backend
composer install --no-dev --optimize-autoloader
php artisan key:generate
php artisan migrate --seed --force
php artisan storage:link
```

---

### Bước 4: Kích hoạt Nginx và cấp chứng chỉ SSL HTTPS Miễn phí
```bash
# 1. Copy cấu hình Nginx từ dự án vào Nginx của hệ điều hành
sudo cp /var/www/equipment_manager/deploy/nginx/equipment_manager.conf /etc/nginx/sites-available/equipment_manager.conf

# 2. Mở file sửa 'your-domain.com' thành tên miền thực tế của bạn
sudo nano /etc/nginx/sites-available/equipment_manager.conf

# 3. Kích hoạt website và test cấu hình
sudo ln -s /etc/nginx/sites-available/equipment_manager.conf /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t

# 4. Cài đặt Certbot và lấy chứng chỉ SSL tự động
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d your-domain.com -d www.your-domain.com

# 5. Khởi động lại Nginx
sudo systemctl restart nginx
```

---

### Bước 5: Kích hoạt Supervisor để duy trì WebSocket Reverb & Queue 24/7
```bash
# Copy cấu hình Supervisor
sudo cp /var/www/equipment_manager/deploy/supervisor/equipment_manager.conf /etc/supervisor/conf.d/equipment_manager.conf

# Nạp cấu hình và khởi động các tiến trình
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start all

# Kiểm tra trạng thái hoạt động (phải hiện RUNNING)
sudo supervisorctl status
```

---

## ⚡ 4. Vận hành & Tự động Deploy khi Push Code

Từ nay về sau, bạn chỉ cần thực hiện quy trình phát triển thông thường:

```bash
# 1. Lập trình tính năng hoặc sửa lỗi trên máy của bạn
# 2. Commit và đẩy lên GitHub:
git add .
git commit -m "feat: cap nhat tinh nang moi"
git push origin main
```

Ngay lập tức, tab **Actions** trên GitHub sẽ tự động:
1. 🟢 **Tải mã nguồn và Build Vue 3**: Biên dịch Frontend thành các tệp tĩnh siêu tốc.
2. 🟢 **Kiểm tra cú pháp PHP & Composer**: Đảm bảo Backend không bị lỗi.
3. 🟢 **SSH vào VPS Server**: Tải tệp mới về, chạy `php artisan migrate`, làm mới Cache, nạp lại WebSocket và Nginx.
4. 🎉 **Toàn bộ quá trình chỉ mất ~45 - 60 giây**, người dùng không bị rớt mạng hay trắng trang!
