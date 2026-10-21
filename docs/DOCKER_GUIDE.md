# 🐳 HƯỚNG DẪN KHỞI CHẠY DỰ ÁN VỚI DOCKER & CÁC LỆNH XEM LOG

Tài liệu hướng dẫn chi tiết cách quản lý, khởi động, dừng các dịch vụ Docker và tra cứu toàn bộ log hệ thống (Laravel API, Real-time WebSocket Reverb, Hàng đợi Horizon, Scheduler, Redis, Mailpit và Frontend Vue 3).

---

## 📌 1. Danh sách Dịch vụ & Cổng mạng (Ports)

| Dịch vụ | Tên Container | Cổng truy cập | Mô tả chức năng |
| :--- | :--- | :--- | :--- |
| **Laravel API** | `backend-laravel.test-1` | `http://localhost` (Port 80) | Ứng dụng Backend API Laravel 11 |
| **Vue 3 Frontend** | Chạy qua Vite | `http://localhost:3000` | Giao diện người dùng Vuetify + Tailwind |
| **Reverb WebSocket** | `backend-reverb-1` | `ws://localhost:8090` | Máy chủ Real-time Socket tốc độ cao |
| **Laravel Horizon** | `backend-horizon-1` | `http://localhost/horizon` | Giám sát & Quản lý hàng đợi (Queue Worker) |
| **Scheduler** | `backend-scheduler-1` | Chạy ngầm | Tự động chạy tác vụ định kỳ (Cronjob) |
| **Redis Cache** | `backend-redis-1` | `localhost:6379` | Bộ nhớ tạm Cache, Queue & Reverb Pub/Sub |
| **Mailpit** | `backend-mailpit-1` | `http://localhost:8025` | Web Dashboard kiểm tra email gửi đi ở local |
| **MySQL (XAMPP)** | Máy host Windows | `localhost:3306` | CSDL MySQL (phpMyAdmin: `http://localhost:8080/phpmyadmin`) |

---

## 🚀 2. Các lệnh Khởi động & Dừng Hệ thống

### A. Khởi chạy toàn bộ cụm Docker Backend
Đứng tại thư mục gốc của dự án (`equipment_manager`):

```bash
# Khởi động toàn bộ container ở chế độ chạy ngầm (-d)
docker compose -f backend/compose.yaml up -d
```

### B. Kiểm tra trạng thái các container đang chạy
```bash
docker ps
# Hoặc xem bằng docker compose
docker compose -f backend/compose.yaml ps
```

### C. Dừng toàn bộ các container Docker
```bash
# Dừng container (giữ nguyên dữ liệu)
docker compose -f backend/compose.yaml stop

# Dừng và xóa container, network
docker compose -f backend/compose.yaml down
```

### D. Khởi động lại một dịch vụ cụ thể
```bash
# Khởi động lại web server Laravel
docker compose -f backend/compose.yaml restart laravel.test

# Khởi động lại server WebSocket Reverb
docker compose -f backend/compose.yaml restart reverb

# Khởi động lại worker Horizon
docker compose -f backend/compose.yaml restart horizon
```

### E. Khởi chạy Giao diện Frontend (Vue 3)
```bash
# Mở một cửa sổ terminal mới và chạy:
npm run dev --prefix Frontend
```
Giao diện sẽ chạy tại: **`http://localhost:3000`**

---

## 📋 3. Toàn bộ các Lệnh Xem Log (Logging Commands)

### A. Xem Log thời gian thực của TOÀN BỘ hệ thống (All-in-one)
Lệnh này sẽ stream log của tất cả container ra màn hình cùng lúc (mỗi container có 1 màu riêng):
```bash
docker compose -f backend/compose.yaml logs -f
```

---

### B. Xem Log của từng Container cụ thể

#### 1. Log Web & API Laravel (`backend-laravel.test-1`)
Xem các request HTTP, lỗi 500, access logs gửi lên API:
```bash
# Xem 100 dòng cuối và theo dõi trực tiếp (-f)
docker logs -f --tail 100 backend-laravel.test-1
```

#### 2. Log WebSocket Real-time Reverb (`backend-reverb-1`)
Xem kết nối client connect, subscribe kênh private `organization.{id}`, phát sự kiện:
```bash
docker logs -f --tail 100 backend-reverb-1
```

#### 3. Log Hàng đợi Laravel Horizon (`backend-horizon-1`)
Xem tiến trình xử lý Job ngầm (gửi mail, xử lý ảnh, xuất file Excel):
```bash
docker logs -f --tail 100 backend-horizon-1
```

#### 4. Log Scheduler Cronjob (`backend-scheduler-1`)
Xem các tác vụ tự động chạy mỗi phút:
```bash
docker logs -f --tail 50 backend-scheduler-1
```

#### 5. Log Bộ nhớ Redis (`backend-redis-1`)
```bash
docker logs -f --tail 50 backend-redis-1
```

---

### C. Xem trực tiếp File Log của Laravel Framework (`laravel.log`)

Khi gặp lỗi Exception hoặc Bug logic trong code Laravel:

```bash
# Cách 1: Đọc liên tục file storage/logs/laravel.log bên trong container
docker exec -it backend-laravel.test-1 tail -f storage/logs/laravel.log

# Cách 2: Sử dụng công cụ Laravel Pail (cực kỳ đẹp, có màu sắc & filter)
docker exec -it backend-laravel.test-1 php artisan pail

# Lọc chỉ xem log lỗi (Error only) với Pail:
docker exec -it backend-laravel.test-1 php artisan pail --level=error
```

---

## 🛠️ 4. Các lệnh Thao tác Thường dùng trong Container

| Mục đích | Câu lệnh chạy từ máy tính Host |
| :--- | :--- |
| **Vào Terminal Linux của Container** | `docker exec -it backend-laravel.test-1 bash` |
| **Chạy Migration CSDL** | `docker exec -it backend-laravel.test-1 php artisan migrate` |
| **Chạy Seeder nạp dữ liệu mẫu** | `docker exec -it backend-laravel.test-1 php artisan db:seed` |
| **Mở tương tác dòng lệnh Tinker** | `docker exec -it backend-laravel.test-1 php artisan tinker` |
| **Xóa & Tối ưu Cache Framework** | `docker exec -it backend-laravel.test-1 php artisan optimize` |
| **Xóa cache Config & Route** | `docker exec -it backend-laravel.test-1 php artisan optimize:clear` |
| **Xem danh sách toàn bộ Route API** | `docker exec -it backend-laravel.test-1 php artisan route:list` |
| **Kiểm tra trạng thái hàng đợi Horizon** | `docker exec -it backend-laravel.test-1 php artisan horizon:status` |
| **Tối ưu autoload Composer** | `docker exec -it backend-laravel.test-1 composer dump-autoload -o --classmap-authoritative` |

---

## 💡 5. Xử lý sự cố thường gặp (Troubleshooting)

1. **Không mở được phpMyAdmin:**
   - Mở **XAMPP Control Panel** trên máy tính ➔ Bấm **Start** cho cả **Apache** và **MySQL**.
   - Truy cập qua cổng: `http://localhost:8080/phpmyadmin` (không dùng cổng 80 vì Docker đã sử dụng).

2. **Dữ liệu hoặc Route mới thêm không nhận diện:**
   - Chạy lệnh xóa cache:
     ```bash
     docker exec -it backend-laravel.test-1 php artisan optimize:clear
     ```

3. **WebSocket báo Disconnected / Offline:**
   - Kiểm tra container Reverb có đang chạy không:
     ```bash
     docker ps | Select-String "reverb"
     ```
   - Nếu container chưa chạy, khởi động lại:
     ```bash
     docker compose -f backend/compose.yaml restart reverb
     ```
