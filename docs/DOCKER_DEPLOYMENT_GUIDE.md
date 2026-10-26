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

## 🔄 Quy trình Đóng gói & Cập nhật Code sau khi chỉnh sửa

Khi bạn vừa sửa giao diện Frontend (Vue/CSS/HTML) hoặc sửa nghiệp vụ Backend (Controller, Service, Model, Route, Resource, Migrations), hãy lựa chọn một trong các cách cập nhật dưới đây:

### ⚡ Cách 1: Tự động 1-Click (Khuyên dùng - Nhanh nhất ~15 giây)

Dự án đã tích hợp sẵn 2 script tự động hóa toàn bộ việc build Frontend, đồng bộ mã nguồn vào Docker container, xóa cache và reload Nginx:

- **Trên Windows File Explorer:**
  Nhấp đúp chuột trực tiếp vào file **`update-app.bat`** tại thư mục gốc của dự án.
- **Trên Windows Terminal / CMD:**
  ```cmd
  update-app.bat
  ```
- **Trên PowerShell:**
  ```powershell
  .\update-app.ps1
  ```

> **Script tự động thực hiện những gì?**
> 1. Chạy `npm run build` trong `Frontend/` để tạo các bundle JS/CSS tối ưu mới nhất vào thư mục `Frontend/dist`.
> 2. Sao chép toàn bộ gói giao diện vừa build vào Nginx public web của container `equipment_manager-app-1`.
> 3. Sao chép toàn bộ mã nguồn Backend (`Backend/app`, `Backend/routes`, `Backend/config`, `Backend/database`) vào container.
> 4. Chạy `php artisan optimize:clear` để làm mới route/config cache trong Laravel.
> 5. Reload máy chủ Nginx (`nginx -s reload`) để nhận diện thay đổi tức thì mà **không cần khởi động lại container hay mất kết nối Database**.

---

### 🛠️ Cách 2: Cập nhật thủ công từng bước (Dành cho dòng lệnh / CI)

Nếu muốn tự chạy từng bước hoặc debug chi tiết:

```bash
# 1. Đóng gói mã nguồn Frontend
cd Frontend
npm run build
cd ..

# 2. Đồng bộ giao diện Frontend đã đóng gói vào Docker container
docker cp Frontend/dist/. equipment_manager-app-1:/var/www/html/public/

# 3. Đồng bộ mã nguồn Backend (Logic, API, Models, Services)
docker cp Backend/app/. equipment_manager-app-1:/var/www/html/app/
docker cp Backend/routes/. equipment_manager-app-1:/var/www/html/routes/
docker cp Backend/config/. equipment_manager-app-1:/var/www/html/config/
docker cp Backend/database/. equipment_manager-app-1:/var/www/html/database/

# 4. Làm mới Cache và nạp lại máy chủ trong container
docker exec equipment_manager-app-1 php artisan optimize:clear
docker exec equipment_manager-app-1 nginx -s reload
```

---

### 🏗️ Cách 3: Rebuild toàn bộ Docker Image (Khi thay đổi cấu hình lớn)

**Khi nào cần Rebuild toàn bộ Image?**
- Khi cài thêm package mới vào `Frontend/package.json` (cần chạy lại `npm install` sâu trong Docker).
- Khi cài thêm thư viện PHP mới vào `Backend/composer.json` (cần `composer install`).
- Khi chỉnh sửa Dockerfile, cấu hình Supervisor, Nginx gốc của container, hoặc các biến môi trường lớn trong `.env.docker`.

**Lệnh thực hiện:**
```bash
docker compose -f docker-compose.prod.yml --env-file .env.docker up -d --build
```
*(Quá trình này sẽ mất từ 1 - 3 phút để cài đặt lại các dependency từ đầu và nén lại image mới)*

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
