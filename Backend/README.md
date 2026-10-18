# Motel Management System (PMS) - Backend API

Hệ thống Backend API quản lý nhà nghỉ / khách sạn toàn diện (Property Management System) xây dựng trên nền tảng **Laravel 12**, áp dụng kiến trúc Modular tại `app/Modules`, kết hợp **Laravel Reverb (WebSockets)** cho cập nhật trạng thái phòng thời gian thực và **Spatie Permission** cho phân quyền RBAC đa cấp độ.

---

## 🚀 Các Module Chức Năng

- **`Motel` (Trọng tâm hệ thống):**
  - **Loại phòng & Phòng (`RoomType`, `Room`):** Quản lý định giá (theo giờ, theo ngày), số giường, sơ đồ phòng và vòng đời trạng thái phòng (`ready` -> `occupied` -> `not_ready` -> `ready`).
  - **Dịch vụ / Minibar (`Service`, `BookingService`):** Danh mục đồ ăn, thức uống, quản lý tồn kho và lưu vết đơn giá snapshot tại thời điểm khách gọi món.
  - **Khách hàng (`Customer`):** Hồ sơ khách lưu trú, tích điểm loyalty, hỗ trợ cả khách quen lẫn khách vãng lai (*walk-in guest*).
  - **Thuê & Đặt phòng (`Booking`):** Check-in trực tiếp, đặt trước, tự động tính toán phụ phí quá giờ và tiền dịch vụ khi Check-out. Cơ chế *Pessimistic Locking* chống xung đột phòng (race condition).
  - **Thanh toán (`Payment`):** Quản lý các đợt đặt cọc, thanh toán trước, thanh toán tất toán bằng tiền mặt hoặc chuyển khoản.
  - **Real-time Alert:** Tự động quét và phát sự kiện cảnh báo qua WebSocket trước khi phòng hết giờ 15 phút.
- **`Auth`:** Xác thực API Bearer Token (Sanctum), đăng nhập, đăng xuất, cấp quyền người dùng / nhân viên.
- **`Core`:** Quản trị người dùng, vai trò & quyền hạn (Spatie RBAC), cấu hình hệ thống, log nhật ký hoạt động (`log_activities`).
- **`Post` & `Document`:** Quản lý bài viết tin tức, thông báo và văn bản hành chính đính kèm.

---

## 🛠️ Yêu Cầu Môi Trường

- **PHP:** 8.2 trở lên (kèm extension: `pdo`, `mbstring`, `openssl`, `bcmath`, `curl`).
- **Composer:** 2.x.
- **Cơ sở dữ liệu:** MySQL 8.0+ hoặc MariaDB 10.4+ (có thể dùng qua XAMPP).
- **Node.js & NPM:** Để biên dịch asset hoặc chạy công cụ dev.
- **Docker & Docker Compose:** *(Tùy chọn)* Nếu muốn chạy qua Laravel Sail.

---

## ⚡ Cài Đặt Nhanh

### Cách 1: Chạy trực tiếp trên máy (XAMPP / Local)

1. **Cài đặt dependencies:**
   ```bash
   composer install
   npm install
   ```

2. **Cấu hình môi trường:**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```
   *Mở file `.env` cập nhật thông tin database (DB_DATABASE, DB_USERNAME, DB_PASSWORD).*

3. **Chạy Migration và Seeder:**
   ```bash
   php artisan migrate --seed
   ```

4. **Khởi chạy ứng dụng:**
   - Khởi động API Server:
     ```bash
     php artisan serve
     ```
   - Khởi động WebSocket Server (Laravel Reverb):
     ```bash
     php artisan reverb:start
     ```
   - Khởi động Queue Worker:
     ```bash
     php artisan queue:listen --tries=1 --timeout=0
     ```
   - Khởi động Scheduler (quét cảnh báo phòng sắp hết giờ mỗi phút):
     ```bash
     php artisan schedule:work
     ```

*(Hoặc dùng lệnh tổng hợp: `composer dev`)*

---

### Cách 2: Chạy qua Docker (Laravel Sail)

1. **Khởi động containers:**
   ```bash
   ./vendor/bin/sail up -d
   ```

2. **Khởi tạo dữ liệu:**
   ```bash
   ./vendor/bin/sail artisan key:generate
   ./vendor/bin/sail artisan migrate --seed
   ```

3. **Chạy toàn bộ dịch vụ:**
   ```bash
   ./vendor/bin/sail composer dev
   ```

---

## 📡 Danh Mục API Chính (`/api/motel/...`)

| Phân hệ | Phương thức | Endpoint | Mô tả chức năng |
| :--- | :--- | :--- | :--- |
| **Loại phòng** | `GET` / `POST` | `/api/motel/room-types` | Danh sách / Thêm loại phòng & bảng giá |
| | `GET` / `PUT` / `DELETE` | `/api/motel/room-types/{id}` | Chi tiết / Sửa / Xóa loại phòng |
| **Phòng** | `GET` / `POST` | `/api/motel/rooms` | Sơ đồ danh sách phòng / Thêm phòng mới |
| | `GET` / `PUT` / `DELETE` | `/api/motel/rooms/{id}` | Chi tiết / Sửa / Xóa phòng |
| | `PATCH` | `/api/motel/rooms/{id}/status` | Đổi trạng thái phòng (`ready`, `not_ready`, `maintenance`) |
| **Dịch vụ** | `GET` / `POST` | `/api/motel/services` | Danh mục món minibar / Thêm dịch vụ mới |
| | `GET` / `PUT` / `DELETE` | `/api/motel/services/{id}` | Chi tiết / Sửa thông tin / Xóa món |
| | `PATCH` | `/api/motel/services/{id}/stock` | Cập nhật số lượng tồn kho dịch vụ |
| **Khách hàng** | `GET` / `POST` | `/api/motel/customers` | Danh sách (tìm theo SĐT, tên) / Thêm khách mới |
| | `GET` / `PUT` / `DELETE` | `/api/motel/customers/{id}` | Chi tiết kèm lịch sử ở / Sửa / Xóa hồ sơ |
| **Đặt & Thuê phòng** | `GET` / `POST` | `/api/motel/bookings` | Danh sách đặt phòng / Đặt trước phòng |
| | `POST` | `/api/motel/bookings/check-in` | Nhận phòng (Check-in cho khách vãng lai hoặc đặt trước) |
| | `POST` | `/api/motel/bookings/{id}/services` | Gọi thêm đồ uống/dịch vụ vào phòng |
| | `DELETE` | `/api/motel/bookings/{id}/services/{item_id}` | Hủy món dịch vụ nếu nhập nhầm |
| | `GET` | `/api/motel/bookings/{id}/preview-checkout`| Xem trước bảng tính tiền phòng + phụ phí quá giờ + dịch vụ |
| | `POST` | `/api/motel/bookings/{id}/checkout` | Hoàn tất trả phòng, cập nhật phòng thành `not_ready` |
| **Thanh toán** | `GET` / `POST` | `/api/motel/bookings/{id}/payments` | Lịch sử thanh toán / Tạo phiếu thu (cọc, tất toán) |
| **Báo cáo** | `GET` | `/api/motel/reports/dashboard` | Thống kê tỷ lệ phòng trống, phòng đang ở, doanh thu ngày |

---

## 📖 Tài Liệu & Thiết Kế Kiến Trúc

- **Tài liệu API giao diện Scribe:** Truy cập đường dẫn `/docs` sau khi sinh tài liệu.
  ```bash
  php artisan scribe:generate
  ```
- **Tài liệu API dạng Markdown:** Thư mục `docs/api/`.
- **Thiết kế Cơ sở dữ liệu:** `docs/DATABASE_DESIGN.md`.
- **Thiết kế Cấu trúc hệ thống:** `docs/STRUCTURE_DESIGN.md`.

---

## 🧪 Kiểm Thử & Tiêu Chuẩn Code

- **Chạy toàn bộ Automated Tests:**
  ```bash
  php artisan test
  ```
- **Kiểm tra chuẩn cú pháp (Laravel Pint):**
  ```bash
  ./vendor/bin/pint --test
  ```
- **Tự động format code theo chuẩn PSR-12:**
  ```bash
  ./vendor/bin/pint
  ```

---

## 🔒 Quy Tắc & Lưu Ý Kỹ Thuật

1. **Chuẩn hóa Timezone:** Toàn bộ dữ liệu ngày giờ lưu trữ trong Database bắt buộc sử dụng chuẩn **UTC**. Chuyển đổi sang múi giờ `Asia/Ho_Chi_Minh` (+07:00) được thực hiện tại tầng API Resource trước khi gửi về client.
2. **Pessimistic Locking:** Các thao tác Check-in / Đặt phòng bắt buộc chạy trong Transaction và sử dụng `lockForUpdate()` trên bảng `rooms` để triệt tiêu Race Condition khi nhiều lễ tân thao tác đồng thời.
3. **Snapshot Đơn Giá:** Bảng `booking_services` lưu lại đơn giá tại thời điểm phát sinh dịch vụ (`unit_price`), tuyệt đối không truy vấn ngược bảng gốc `services` để tính hóa đơn quá khứ.
4. **Chuẩn phản hồi JSON:** Mọi API Controller kế thừa Trait `App\Modules\Core\Traits\RespondsWithJson` để đồng nhất cấu trúc dữ liệu trả về (`status`, `message`, `data`, `errors`).

