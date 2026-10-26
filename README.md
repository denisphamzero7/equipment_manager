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

## ⚙️ 6. Hướng dẫn Cài đặt & Khởi chạy (Getting Started)

Dự án hỗ trợ 3 phương thức khởi chạy linh hoạt tùy theo nhu cầu sử dụng:

### 🚀 Lựa chọn 1: Chạy Siêu Tốc với All-In-One Docker (Khuyên dùng - Nhanh nhất)

> Không cần cài đặt PHP, Composer hay MySQL trên máy. Toàn bộ hệ thống (**Frontend Vue 3, Backend API Laravel, Nginx, Reverb WebSocket, MySQL, Redis**) được đóng gói sẵn trong một cụm container chuẩn.

#### 1. Chuẩn bị file môi trường
```bash
cp .env.docker.example .env.docker
```

#### 2. Khởi động hệ thống
```bash
docker compose -f docker-compose.prod.yml --env-file .env.docker up -d --build
```

#### 3. Khởi tạo dữ liệu mẫu (Chạy lần đầu)
```bash
docker compose -f docker-compose.prod.yml exec app php artisan db:seed
```

#### 4. Truy cập ứng dụng
- **Giao diện Web & API:** `http://localhost:8088` (hoặc cổng được cấu hình trong `.env.docker`)
- **Tài khoản mẫu đăng nhập:**
  - **Super Admin:** `admin@example.com` / Mật khẩu: `quandcore**11`
  - **Tài khoản phụ:** `pnhau2001@gmail.com` / Mật khẩu: `12345`

#### 🔄 Cập nhật code sau khi chỉnh sửa (Hot Update):
Khi bạn sửa code Frontend hoặc Backend, chỉ cần chạy 1 câu lệnh để nạp thay đổi vào container trong ~15 giây:
- **Windows File Explorer:** Nhấp đúp chuột vào file **`update-app.bat`**.
- **PowerShell:** `.\update-app.ps1`
- **Dừng hệ thống:** `docker compose -f docker-compose.prod.yml down`

> 📖 **Xem hướng dẫn chi tiết:** [docs/DOCKER_DEPLOYMENT_GUIDE.md](docs/DOCKER_DEPLOYMENT_GUIDE.md)

---

### 💻 Lựa chọn 2: Chạy Môi Trường Phát Triển Cục Bộ (Local Dev - XAMPP / PHP + Node.js)

Dành cho lập trình viên muốn code trực tiếp với tính năng Hot-Reload của Vite và debug trực tiếp trên máy.

#### Yêu cầu hệ thống:
- **PHP**: >= 8.2 (kèm các extension: `pdo_mysql`, `gd`, `zip`, `fileinfo`, `mbstring`, `intl`)
- **MySQL / MariaDB**: >= 8.0 (ví dụ MySQL trong XAMPP)
- **Composer**: >= 2.x
- **Node.js**: >= 18.x & npm

#### A. Khởi chạy Backend (Laravel API):
```bash
# 1. Di chuyển vào thư mục Backend
cd Backend

# 2. Cài đặt các thư viện PHP
composer install

# 3. Tạo file cấu hình môi trường
cp .env.example .env
# Chỉnh sửa thông số kết nối Database (DB_DATABASE, DB_USERNAME, DB_PASSWORD) trong .env cho khớp với MySQL của bạn.

# 4. Tạo Application Key
php artisan key:generate

# 5. Tạo liên kết thư mục lưu trữ ảnh/tệp tin
php artisan storage:link

# 6. Chạy Migration và nạp dữ liệu mẫu
php artisan migrate --seed

# 7. Khởi chạy máy chủ API
php artisan serve
```
Backend API sẽ hoạt động tại: `http://127.0.0.1:8000`

*(Tùy chọn) Bật WebSocket Realtime:*
```bash
php artisan reverb:start
```

#### B. Khởi chạy Frontend (Vue 3 + Vite):
Mở một cửa sổ Terminal mới:
```bash
# 1. Di chuyển vào thư mục Frontend
cd Frontend

# 2. Cài đặt các thư viện Node.js
npm install

# 3. Khởi chạy máy chủ phát triển
npm run dev
```
Frontend Web sẽ mở tại: `http://localhost:3000` (đã cấu hình sẵn proxy chuyển tiếp request `/api` và `/storage` sang Backend).

---

### 🐳 Lựa chọn 3: Chạy Môi Trường Docker Cho Lập Trình Viên (Sail / Multi-container)

Nếu bạn muốn môi trường dev riêng biệt qua Docker Compose (Laravel Sail, Reverb, Horizon, Redis, Mailpit):

```bash
# 1. Khởi động cụm Docker Backend
docker compose -f backend/compose.yaml up -d

# 2. Khởi chạy Frontend
npm run dev --prefix Frontend
```

- **Giao diện Web:** `http://localhost:3000`
- **Backend API:** `http://localhost`
- **Reverb WebSocket:** `ws://localhost:8090`
- **Horizon Dashboard:** `http://localhost/horizon`
- **Mailpit:** `http://localhost:8025`

> 📖 **Xem hướng dẫn chi tiết:** [docs/DOCKER_GUIDE.md](docs/DOCKER_GUIDE.md)

---

## 📚 7. Tài liệu Hướng dẫn Chuyên sâu

| Tài liệu | Mô tả |
| :--- | :--- |
| [docs/DOCKER_DEPLOYMENT_GUIDE.md](docs/DOCKER_DEPLOYMENT_GUIDE.md) | Hướng dẫn đóng gói và triển khai Docker All-In-One, quy trình cập nhật hot-update |
| [docs/DEPLOYMENT_GUIDE.md](docs/DEPLOYMENT_GUIDE.md) | Hướng dẫn triển khai CI/CD GitHub Actions lên máy chủ VPS không downtime |
| [docs/BUSINESS_WORKFLOW_GUIDE.md](docs/BUSINESS_WORKFLOW_GUIDE.md) | Đặc tả chi tiết toàn bộ luồng nghiệp vụ kiểm kê, đối soát, định mức và báo hỏng |
| [docs/USER_GUIDE_EQUIPMENT_MANAGER.md](docs/USER_GUIDE_EQUIPMENT_MANAGER.md) | Cẩm nang sử dụng thực tế dành cho người quản lý thiết bị và kỹ thuật viên |
| [docs/AI_FEATURE_DEVELOPMENT_GUIDE.md](docs/AI_FEATURE_DEVELOPMENT_GUIDE.md) | Quy chuẩn kiến trúc, UI/UX và phạm vi thay đổi khi phát triển tính năng |
| [docs/SYSTEM_OPTIMIZATION_AND_BEST_PRACTICES.md](docs/SYSTEM_OPTIMIZATION_AND_BEST_PRACTICES.md) | Các giải pháp tối ưu hóa hiệu năng và best practices |

---

## 🛣️ 8. Kế hoạch phát triển (Roadmap)

- [x] Thiết kế Cơ sở dữ liệu và Migrations Module Thiết bị.
- [x] Xây dựng Backend API: Sơ đồ phòng, CRUD, Định mức, Báo hỏng, Import/Export Excel.
- [x] Seed dữ liệu danh mục, mẫu thiết bị chuẩn (TV, Box, Remote, Wifi...).
- [x] Phát triển Giao diện người dùng (Frontend Vue 3 + Vuetify + Tailwind):
  - [x] Dashboard tổng quan & biểu đồ thống kê.
  - [x] Sơ đồ mặt bằng tương tác (Interactive Grid / Floor Map).
  - [x] Popup kiểm tra & đối soát thiết bị từng phòng.
  - [x] Tối ưu Responsive chuẩn cho điện thoại di động & máy tính bảng.
- [x] Tích hợp WebSocket Real-time (Laravel Reverb & Echo): Tự động đổi màu phòng, chuông thông báo tức thời khi có sự cố hoặc thay đổi thiết bị.
