# 🏨 EQUIPMENT MANAGER - HỆ THỐNG QUẢN LÝ THIẾT BỊ PHÒNG & TÀI SẢN

Hệ thống quản lý vòng đời thiết bị, tài sản, định mức chuẩn và sơ đồ mặt bằng trực quan (Interactive Map View) dành cho Khách sạn, Resort, Căn hộ dịch vụ, Ký túc xá và Tòa nhà văn phòng.

> **Dành cho AI và người phát triển:** Xem [Hướng dẫn phát triển tính năng](docs/AI_FEATURE_DEVELOPMENT_GUIDE.md) trước khi mở rộng dự án để kế thừa pattern hiện có, giữ phạm vi thay đổi gọn và thiết kế giao diện nhất quán.

---

## 📌 1. Giới thiệu tổng quan

**Equipment Manager** được thiết kế nhằm giải quyết bài toán kiểm soát tài sản và trang thiết bị thực tế trong từng phòng ốc:
- Tránh thất thoát, nhầm lẫn thiết bị (Tivi, Điều khiển, Box TV, Router Wifi, Điều hòa, v.v.).
- Đối soát tự động số lượng thực tế so với **định mức chuẩn** của từng hạng phòng.
- Trực quan hóa tình trạng thiết bị qua **sơ đồ mặt bằng (Grid/Map View)** theo từng Block và Tầng.
- Quản lý quy trình báo hỏng, sửa chữa, bảo trì và điều chuyển tài sản linh hoạt.

---

## 🚀 2. Tính năng nổi bật

### 🗺️ Sơ đồ mặt bằng trực quan (Interactive Map View)
- Xem trạng thái trang thiết bị theo từng **Khu vực / Block** (Block A, Block B, Condos, Villa...) và theo từng **Tầng (Floor)**.
- Đánh dấu trực quan màu sắc phòng:
  - 🟢 **Đủ chuẩn**: Phòng có đầy đủ thiết bị và hoạt động tốt theo định mức quy định.
  - 🟡 **Thiếu thiết bị**: Phòng chưa trang bị đủ số lượng thiết bị theo tiêu chuẩn.
  - 🔴 **Có thiết bị hỏng**: Phòng đang có thiết bị gặp sự cố cần sửa chữa.
  - ⚪ **Đang bảo trì / Kiểm tra**: Cảnh báo kỹ thuật viên xử lý kịp thời.

### 📐 Quản lý Định mức Tiêu chuẩn (Room Standard Equipments)
- Cấu hình định mức thiết bị bắt buộc cho từng loại phòng (`room_types`).
- *Ví dụ*: Phòng VIP bắt buộc gồm 2 TV 32", 1 Smart TV 40", 4 Box FPT Play, 4 Remote, 1 Router Wifi.
- Tự động so sánh số lượng thực tế đang có trong phòng với định mức chuẩn để cảnh báo thừa/thiếu ngay lập tức.

### 🏷️ Quản lý Chi tiết Từng Thiết bị (Asset Tracking by Serial / QR)
- Quản lý theo 3 cấp phân cấp:
  1. **Nhóm thiết bị (`equipment_categories`)**: TV, Điều khiển, TV Box, Thiết bị mạng, Điều hòa...
  2. **Dòng Model (`equipment_models`)**: Hãng sản xuất, mã hiệu, thông số kỹ thuật (JSON specs).
  3. **Cá thể thiết bị (`room_equipments`)**: Số Serial/Mã QR duy nhất, trạng thái hoạt động, vị trí đặt cụ thể (Phòng khách, Phòng ngủ 1...).
- Quản lý trạng thái:
  - `good`: Hoạt động tốt
  - `broken`: Đang hỏng hóc
  - `maintenance`: Đang bảo trì / bảo dưỡng
  - `lost`: Thất lạc / mất
  - `in_stock`: Tồn trong kho (chưa gán phòng)
  - `disposed`: Đã thanh lý

### 🛠️ Quy trình Báo hỏng & Lịch sử Bảo trì (Maintenance Logs)
- Ghi nhận yêu cầu sửa chữa, mô tả sự cố và đính kèm hình ảnh hiện trường (`spatie/laravel-medialibrary`).
- Theo dõi tiến độ xử lý: `pending` ➔ `in_progress` ➔ `completed` / `unfixable`.
- Lưu vết chi phí sửa chữa, kỹ thuật viên phụ trách và ngày hoàn thành.

### 🔄 Điều chuyển & Thu hồi Thiết bị
- Điều chuyển thiết bị từ kho vào phòng hoặc hoán đổi giữa các phòng.
- Thu hồi thiết bị về kho khi phòng sửa chữa hoặc trả phòng.
- Cập nhật trạng thái hàng loạt (Bulk Update Status).

### 📊 Xuất / Nhập Excel & Thống kê
- Nhập danh sách thiết bị hàng loạt từ file Excel (`.xlsx`, `.csv`).
- Xuất danh sách kiểm kê thiết bị theo phòng, theo tình trạng.
- Thống kê tổng số lượng thiết bị, tỷ lệ hư hỏng, thiết bị trong kho và chi phí bảo trì.

### 🏢 Multi-tenancy & Phân quyền bảo mật
- Tách biệt dữ liệu theo tổ chức / chi nhánh (`organization_id`).
- Phân quyền theo vai trò (Role-Based Access Control) với `spatie/laravel-permission`.
- Xác thực API bảo mật thông qua `Laravel Sanctum`.

### 👥 Quản trị người dùng, vai trò và quyền
- Tìm kiếm, lọc trạng thái, tạo, cập nhật và xóa tài khoản người dùng.
- Gán nhiều vai trò theo từng tổ chức; mật khẩu chỉ thay đổi khi nhập mật khẩu mới.
- Tạo, chỉnh sửa và xóa vai trò; chọn quyền theo nhóm để dễ kiểm soát phạm vi truy cập.
- Quản lý mã quyền và nhóm quyền; cảnh báo tác động khi đổi/xóa quyền đang dùng.
- Các màn hình quản trị chỉ hiển thị theo permission tương ứng; API tiếp tục áp dụng middleware phân quyền hiện có.

---

## 🗄️ 3. Mô hình Dữ liệu (Database Schema)

```
equipment_categories (Nhóm thiết bị)
       │ 1-N
       ▼
equipment_models (Dòng model, hãng, thông số)
  │              │ 1-N
  │ 1-N          ▼
  │         room_equipments (Cá thể thiết bị: Serial, Status, Room ID)
  │              │ 1-N
  │              ▼
  │         equipment_maintenance_logs (Lịch sử báo hỏng, chi phí sửa)
  │
  ▼
room_standard_equipments (Định mức tiêu chuẩn: room_type_id + equipment_model_id + standard_quantity)
```

---

## 📂 4. Cấu trúc Dự án (Modular Architecture)

Hệ thống được tổ chức theo kiến trúc Module chuẩn (Domain-Driven / Feature-driven):

```
equipment_manager/
├── Backend/
│   ├── app/
│   │   ├── Modules/
│   │   │   ├── Equipment/
│   │   │   │   ├── Controllers/
│   │   │   │   │   ├── EquipmentController.php            # CRUD, thống kê, import/export
│   │   │   │   │   ├── EquipmentMapViewController.php     # API sơ đồ phòng theo Block/Tầng
│   │   │   │   │   └── RoomEquipmentDetailController.php  # Chi tiết thiết bị & đối soát định mức
│   │   │   │   ├── Enums/
│   │   │   │   │   ├── EquipmentStatusEnum.php            # Trạng thái thiết bị
│   │   │   │   │   └── MaintenanceStatusEnum.php          # Trạng thái bảo trì
│   │   │   │   ├── Exports/                               # Xuất file Excel
│   │   │   │   ├── Imports/                               # Nhập file Excel
│   │   │   │   ├── Models/                                # Eloquent Models
│   │   │   │   ├── Requests/                              # Form Requests & Validation
│   │   │   │   ├── Resources/                             # API Resources (JSON Transforms)
│   │   │   │   ├── Routes/
│   │   │   │   │   └── equipment.php                      # Khai báo Endpoint API
│   │   │   │   └── Services/                              # Business Logic Layer
│   │   │   └── Motel/                                     # Module Phòng & Cơ sở lưu trú
│   ├── database/
│   │   ├── migrations/                                    # Migrations bảng thiết bị & sơ đồ phòng
│   │   └── seeders/
│   │       └── EquipmentSeeder.php                        # Seeder dữ liệu thiết bị mẫu
├── Frontend/                                              # Ứng dụng Giao diện (SPA)
└── README.md
```

---

## 🔌 5. Danh sách API Endpoints chính

Tất cả các route đều được bảo vệ bởi middleware `auth:sanctum`:

| Phương thức | Endpoint | Mô tả |
| :--- | :--- | :--- |
| `GET` | `/api/map-view/blocks` | Lấy danh sách các Block/Khu vực hiện có |
| `GET` | `/api/map-view?block=...&floor=...` | Lấy sơ đồ phòng và trạng thái thiết bị theo tầng |
| `GET` | `/api/rooms/{roomId}` | Lấy chi tiết thiết bị phòng & đối soát định mức chuẩn |
| `GET` | `/api/equipment` | Danh sách thiết bị (hỗ trợ phân trang, lọc, tìm kiếm) |
| `POST` | `/api/equipment` | Thêm mới thiết bị vào hệ thống |
| `PUT` | `/api/equipment/{id}` | Cập nhật thông tin thiết bị |
| `DELETE` | `/api/equipment/{id}` | Xóa thiết bị |
| `PATCH` | `/api/equipment/{id}/status` | Đổi trạng thái thiết bị |
| `POST` | `/api/equipment/assign-to-room` | Điều chuyển thiết bị vào phòng hoặc về kho |
| `POST` | `/api/equipment/{id}/report-broken` | Báo hỏng thiết bị và tạo phiếu bảo trì |
| `POST` | `/api/equipment/{id}/upload-photo` | Đính kèm hình ảnh thiết bị / sự cố |
| `GET` | `/api/equipment/stats` | Thống kê số lượng theo loại, tình trạng |
| `GET` | `/api/equipment/export` | Xuất dữ liệu ra file Excel |
| `POST` | `/api/equipment/import` | Nhập dữ liệu hàng loạt từ file Excel |

---

## ⚙️ 6. Hướng dẫn Cài đặt & Khởi chạy

### Yêu cầu hệ thống:
- **PHP**: >= 8.2 (Bật các extension: `pdo_mysql`, `gd`, `zip`, `fileinfo`, `mbstring`)
- **MySQL / MariaDB**: >= 8.0
- **Composer**: >= 2.x
- **Node.js & npm**: (cho Frontend / Assets)

### Các bước cài đặt:

1. **Di chuyển vào thư mục Backend:**
   ```bash
   cd Backend
   ```

2. **Cài đặt các gói phụ thuộc PHP:**
   ```bash
   composer install
   ```

3. **Cấu hình file môi trường:**
   - Sao chép file `.env.example` thành `.env`:
     ```bash
     cp .env.example .env
     ```
   - Cấu hình thông số kết nối cơ sở dữ liệu trong file `.env`:
     ```env
     DB_CONNECTION=mysql
     DB_HOST=127.0.0.1
     DB_PORT=3306
     DB_DATABASE=equipment_manager
     DB_USERNAME=root
     DB_PASSWORD=
     ```

4. **Tạo Application Key:**
   ```bash
   php artisan key:generate
   ```

5. **Chạy Migration và nạp dữ liệu mẫu:**
   ```bash
   php artisan migrate
   php artisan db:seed --class=EquipmentSeeder
   ```

6. **Tạo symbolic link cho thư mục lưu trữ ảnh:**
   ```bash
   php artisan storage:link
   ```

7. **Khởi chạy Local Server:**
   ```bash
   php artisan serve
   ```
   API Backend sẽ sẵn sàng tại: `http://127.0.0.1:8000`

---

## 🐳 6. Hướng dẫn Khởi chạy với Docker & Xem Log

> 📖 **Xem tài liệu chi tiết đầy đủ:** [docs/DOCKER_GUIDE.md](docs/DOCKER_GUIDE.md)

### A. Khởi chạy toàn bộ hệ thống
```bash
# 1. Khởi động cụm Docker Backend (Laravel, Reverb WebSocket, Horizon, Scheduler, Redis, Mailpit)
docker compose -f backend/compose.yaml up -d

# 2. Khởi động Frontend Vue 3 (mở terminal mới)
npm run dev --prefix Frontend
```

- **Giao diện Web:** `http://localhost:3000`
- **Backend API:** `http://localhost`
- **Reverb WebSocket:** `ws://localhost:8090`
- **Horizon Queue Dashboard:** `http://localhost/horizon`
- **Mailpit Email Dashboard:** `http://localhost:8025`
- **phpMyAdmin (XAMPP):** `http://localhost:8080/phpmyadmin`

### B. Các lệnh xem log thời gian thực
```bash
# Xem log toàn bộ hệ thống
docker compose -f backend/compose.yaml logs -f

# Xem log Backend API
docker logs -f --tail 100 backend-laravel.test-1

# Xem log Real-time WebSocket Reverb
docker logs -f --tail 100 backend-reverb-1

# Xem log Hàng đợi Horizon
docker logs -f --tail 100 backend-horizon-1

# Xem file laravel.log với công cụ Pail
docker exec -it backend-laravel.test-1 php artisan pail
```

---

## 🛣️ 7. Kế hoạch phát triển (Roadmap)

- [x] Thiết kế Cơ sở dữ liệu và Migrations Module Thiết bị.
- [x] Xây dựng Backend API: Sơ đồ phòng, CRUD, Định mức, Báo hỏng, Import/Export Excel.
- [x] Seed dữ liệu danh mục, mẫu thiết bị chuẩn (TV, Box, Remote, Wifi...).
- [x] Phát triển Giao diện người dùng (Frontend Vue 3 + Vuetify + Tailwind):
  - [x] Dashboard tổng quan & biểu đồ thống kê.
  - [x] Sơ đồ mặt bằng tương tác (Interactive Grid / Floor Map).
  - [x] Popup kiểm tra & đối soát thiết bị từng phòng.
  - [x] Tối ưu Responsive chuẩn cho điện thoại di động & máy tính bảng.
- [x] Tích hợp WebSocket Real-time (Laravel Reverb & Echo): Tự động đổi màu phòng, chuông thông báo tức thời khi có sự cố hoặc thay đổi thiết bị.
