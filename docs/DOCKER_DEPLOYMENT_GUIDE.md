# Hướng dẫn đóng gói và triển khai bằng Docker (All-In-One Container)

Bộ đóng gói này gộp toàn bộ dự án (**Vue 3 Frontend, Laravel API, Nginx, PHP-FPM, Reverb WebSocket, Queue Worker, MySQL, Redis**) vào mô hình Docker tiêu chuẩn, giúp bạn chạy trên bất kỳ máy tính hoặc VPS nào chỉ với **1 câu lệnh**.

---

## 🚀 Cách chạy trên bất kỳ máy nào (Local / VPS mới)

### Bước 1: Chuẩn bị file môi trường
Tạo file `.env.docker` từ file mẫu:
```bash
cp .env.docker.example .env.docker
```
*(Nếu chạy trên VPS có tên miền, hãy sửa `DOMAIN=yourdomain.com` và `APP_SCHEME=https`)*

### Bước 2: Khởi động hệ thống
Chạy lệnh duy nhất sau để Docker tự build và chạy ngầm toàn bộ dịch vụ:
```bash
docker compose -f docker-compose.prod.yml --env-file .env.docker up -d --build
```

### Bước 3: Truy cập hệ thống
- Mở trình duyệt truy cập: `http://localhost:8080` (hoặc IP / domain của bạn).
- Đăng nhập và trải nghiệm đầy đủ realtime WebSocket, quản lý thiết bị, phòng trọ.

---

## 📦 Cách sao lưu / Di chuyển sang VPS khác
Khi bạn có VPS mới, bạn chỉ cần nén toàn bộ thư mục dự án này hoặc kéo từ GitHub về VPS, sau đó chạy đúng lệnh ở **Bước 2**.

Nếu muốn lưu toàn bộ Image thành 1 file duy nhất để mang đi máy không có Internet:
```bash
docker save -o equipment-manager.tar equipment-manager:latest
```
Sau đó ở máy mới chỉ cần load lại:
```bash
docker load -i equipment-manager.tar
```

---

## 🛠 Các lệnh quản trị hữu ích

- **Xem logs hệ thống (Nginx, Laravel, Reverb):**
  ```bash
  docker compose -f docker-compose.prod.yml logs -f app
  ```

- **Tạo tài khoản Admin / Chạy Seeder:**
  ```bash
  docker compose -f docker-compose.prod.yml exec app php artisan db:seed
  ```

- **Dừng hệ thống:**
  ```bash
  docker compose -f docker-compose.prod.yml down
  ```
