# 📖 TÀI LIỆU ĐẶC TẢ LUỒNG NGHIỆP VỤ HỆ THỐNG (BUSINESS WORKFLOW GUIDE)
## HỆ THỐNG QUẢN LÝ TRANG THIẾT BỊ KHÁCH SẠN (HOTEL EQUIPMENT MANAGER)

> **Mục tiêu tài liệu:** Cung cấp bức tranh toàn diện, chi tiết và chuẩn mực về các quy trình nghiệp vụ (Business Processes), vai trò người dùng (Actors), vòng đời tài sản (Asset Lifecycle) và luồng xử lý dữ liệu trong hệ thống Quản lý Trang thiết bị Khách sạn.

---

## 📑 MỤC LỤC
1. [Tổng quan Hệ thống & Các Đối tượng Sử dụng (Actors)](#1-tổng-quan-hệ-thống--các-đối-tượng-sử-dụng-actors)
2. [Sơ đồ Thực thể Nghiệp vụ Cốt lõi (Domain Entity Relationship)](#2-sơ-đồ-thực-thể-nghiệp-vụ-cốt-lõi-domain-entity-relationship)
3. [Luồng Nghiệp vụ 1: Vòng đời Quản lý Thiết bị (Equipment Lifecycle)](#3-luồng-nghiệp-vụ-1-vòng-đời-quản-lý-thiết-bị-equipment-lifecycle)
4. [Luồng Nghiệp vụ 2: Quản lý Buồng phòng & Định mức Động (Room Norms & Compliance)](#4-luồng-nghiệp-vụ-2-quản-lý-buồng-phòng--định-mức-động-room-norms--compliance)
5. [Luồng Nghiệp vụ 3: Giám sát Trực quan Thời gian thực (Live Realtime Floor Map)](#5-luồng-nghiệp-vụ-3-giám-sát-trực-quan-thời-gian-thực-live-realtime-floor-map)
6. [Luồng Nghiệp vụ 4: Nhập/Xuất & Đối soát Dữ liệu Hàng loạt (Excel Transfer)](#6-luồng-nghiệp-vụ-4-nhậpxuất--đối-soát-dữ-liệu-hàng-loạt-excel-transfer)
7. [Luồng Nghiệp vụ 5: Quản trị Tài khoản & Phân quyền Đa Chi nhánh (Multi-tenant RBAC)](#7-luồng-nghiệp-vụ-5-quản-trị-tài-khoản--phân-quyền-đa-chi-nhánh-multi-tenant-rbac)
8. [Ma trận Chuyển đổi Trạng thái Thiết bị (State Transition Matrix)](#8-ma-trận-chuyển-đổi-trạng-thái-thiết-bị-state-transition-matrix)

---

## 1. TỔNG QUAN HỆ THỐNG & CÁC ĐỐI TƯỢNG SỬ DỤNG (ACTORS)

Hệ thống phục vụ công tác quản lý tài sản, buồng phòng và vận hành bảo trì trong chuỗi khách sạn/khu nghỉ dưỡng với các đối tượng chính:

```mermaid
flowchart TD
    Admin["👑 Ban Quản lý / Giám đốc Khách sạn\n(Theo dõi tổng quan, duyệt mua sắm, báo cáo)"]
    Housekeeping["🧹 Bộ phận Buồng phòng / Lễ tân\n(Kiểm kê phòng, phát hiện sự cố, báo hỏng tức thì)"]
    Technician["🔧 Kỹ thuật viên / Bảo trì (IT/Maintenance)\n(Tiếp nhận sửa chữa, thay thế, cập nhật chi phí)"]
    Warehouse["📦 Thủ kho / Quản lý Tài sản\n(Nhập kho, cấp phát, điều chuyển, thanh lý)"]

    Admin --> Sys[Hệ thống Equipment Manager]
    Housekeeping --> Sys
    Technician --> Sys
    Warehouse --> Sys
```

| Vai trò (Role) | Trách nhiệm chính | Màn hình thường trực |
| :--- | :--- | :--- |
| **Ban Quản lý (Admin / Super Admin)** | Xem chỉ số sức khỏe tài sản, giám sát tỷ lệ sẵn sàng phòng, định mức buồng phòng, phân quyền người dùng. | Bảng điều khiển (Dashboard), Phân quyền, Thống kê. |
| **Bộ phận Buồng phòng (Housekeeping)** | Kiểm tra phòng khi khách check-in/out, phát hiện thiết bị hỏng/thiếu, chụp ảnh bằng chứng và báo hỏng. | Sơ đồ mặt bằng (Map View), Định mức phòng, Chi tiết phòng. |
| **Kỹ thuật viên (Technician / IT)** | Nhận thông báo sự cố realtime, tiếp nhận sửa chữa, ghi nhận chi phí/linh kiện, bàn giao lại phòng. | Sơ đồ mặt bằng, Danh sách thiết bị, Lịch sử bảo trì. |
| **Thủ kho (Asset Manager)** | Quản lý trang thiết bị trong kho dự phòng, cấp phát thiết bị mới cho các phòng bị thiếu, xuất/nhập Excel. | Danh sách thiết bị, Nhập/Xuất Excel, Danh mục Model. |

---

## 2. SƠ ĐỒ THỰC THỂ NGHIỆP VỤ CỐT LÕI (DOMAIN ENTITY RELATIONSHIP)

```mermaid
erDiagram
    ORGANIZATION ||--o{ ROOM : contains
    ORGANIZATION ||--o{ USER : employs
    ROOM_TYPE ||--o{ ROOM : defines
    ROOM ||--o{ ROOM_EQUIPMENT : houses
    EQUIPMENT_CATEGORY ||--o{ EQUIPMENT_MODEL : categorizes
    EQUIPMENT_MODEL ||--o{ ROOM_EQUIPMENT : instances
    ROOM_EQUIPMENT ||--o{ MAINTENANCE_LOG : tracks
    ROOM_EQUIPMENT ||--o{ MEDIA : attaches

    ROOM {
        int id PK
        string room_number
        string block "Block A, B, C, Penthouse/Villa"
        int floor
        string status "ready, occupied, cleaning, maintenance"
        int bedroom_count
        int living_room_count
        string wifi_mode "dedicated, shared"
        int shared_wifi_room_id FK
    }

    ROOM_EQUIPMENT {
        int id PK
        int room_id FK "Null if in warehouse"
        int equipment_model_id FK
        string serial_number "Duy nhất"
        string status "good, broken, under_maintenance, in_stock, disposed"
        string position_note "Vị trí lắp đặt"
        datetime installed_at
    }

    MAINTENANCE_LOG {
        int id PK
        int room_equipment_id FK
        string issue_description
        string status "pending, in_progress, completed, cancelled"
        decimal cost "Chi phí sửa chữa"
        int reported_by FK
        int resolved_by FK
        datetime resolved_at
    }
```

---

## 3. LUỒNG NGHIỆP VỤ 1: VÒNG ĐỜI QUẢN LÝ THIẾT BỊ (EQUIPMENT LIFECYCLE)

Vòng đời thiết bị trải qua 5 giai đoạn: **Nhập kho $\rightarrow$ Cấp phát vào phòng $\rightarrow$ Vận hành & Báo hỏng $\rightarrow$ Bảo trì sửa chữa $\rightarrow$ Thu hồi / Thanh lý**.

```mermaid
sequenceDiagram
    autonumber
    actor Housekeeping as Buồng phòng / Lễ tân
    actor Tech as Kỹ thuật viên
    actor Warehouse as Thủ kho
    participant Sys as Hệ thống (Frontend & Backend)
    participant Reverb as Laravel Reverb (Realtime)

    Note over Warehouse,Sys: Giai đoạn 1: Nhập kho & Gán vào phòng
    Warehouse->>Sys: Nhập thiết bị mới (Model, Serial, Ảnh)
    Sys-->>Warehouse: Lưu trạng thái "Tồn kho" (in_stock)
    Warehouse->>Sys: Gán thiết bị vào Phòng 123 (Vị trí: Bàn đầu giường)
    Sys-->>Warehouse: Cập nhật room_id=123, trạng thái "Hoạt động tốt" (good)
    Sys->>Reverb: Phát sự kiện 'assigned'

    Note over Housekeeping,Sys: Giai đoạn 2: Phát hiện sự cố & Báo hỏng
    Housekeeping->>Sys: Mở Phòng 123 trên Sơ đồ mặt bằng -> Chọn "Báo hỏng" TV
    Housekeeping->>Sys: Tải ảnh hiện trường hỏng + Mô tả: "Màn hình vỡ sọc"
    Sys->>Sys: Chuyển trạng thái thiết bị sang "Báo hỏng" (broken)
    Sys->>Sys: Tự động tạo bản ghi MaintenanceLog (status: pending)
    Sys->>Reverb: Phát sự kiện 'broken' (room_id: 123)
    Reverb-->>Tech: Giao diện Kỹ thuật viên nhấp nháy đỏ báo động Phòng 123

    Note over Tech,Sys: Giai đoạn 3: Tiếp nhận & Bảo trì
    Tech->>Sys: Tiếp nhận sửa chữa (status: under_maintenance)
    Tech->>Sys: Cập nhật chi phí sửa chữa (150.000đ) + Ghi chú linh kiện thay
    Tech->>Sys: Hoàn thành sửa chữa -> Xác nhận hoạt động tốt
    Sys->>Sys: Chuyển trạng thái thiết bị sang "good"
    Sys->>Reverb: Phát sự kiện 'repaired' -> Phòng 123 xanh trở lại
```

---

## 4. LUỒNG NGHIỆP VỤ 2: QUẢN LÝ BUỒNG PHÒNG & ĐỊNH MỨC ĐỘNG (ROOM NORMS & COMPLIANCE)

Hệ thống giải quyết bài toán kiểm kê buồng phòng phức tạp bằng cơ chế **Định mức Động theo Cơ cấu Phòng**:

### 📐 Quy tắc Tính toán Định mức Tự động:
1. **Thiết bị Hiển thị & Giải trí:**
   $$\text{Định mức TV} = \text{Số phòng ngủ} + \text{Số phòng khách}$$
   $$\text{Định mức Điều khiển TV} = \text{Số lượng TV thực tế}$$
   $$\text{Định mức TV Box} = \text{Số lượng TV thực tế}$$
2. **Thiết bị Mạng & Viễn thông:**
   $$\text{Định mức Wifi Router} = \begin{cases} 0 & \text{nếu phòng chọn chế độ Dùng chung Wifi (Shared)} \\ 1 & \text{nếu phòng có Router riêng (Dedicated)} \end{cases}$$
   $$\text{Định mức Điện thoại bàn} = 1 \text{ máy / phòng (liên lạc nội bộ buồng phòng)}$$

### 🔄 Quy trình Đối soát & Phát hiện Sai lệch:

```mermaid
flowchart TD
    Start["Hệ thống nạp Cơ cấu Phòng\n(Số PN, Số PK, Wifi mode)"] --> CalcNorm["Tính toán Định mức Chuẩn\n(Expected Quantities)"]
    CalcNorm --> LoadActual["Đếm Thiết bị Thực tế trong phòng\n(Actual Installed Quantities)"]
    LoadActual --> Compare{"So sánh Thực tế với Định mức"}

    Compare -- "Thực tế < Định mức" --> Missing["⚠️ THIẾU THIẾT BỊ\n(Ví dụ: Phòng 2 PN chỉ có 1 TV)"]
    Compare -- "Thực tế > Định mức" --> Surplus["ℹ️ THỪA THIẾT BỊ\n(Ví dụ: Dư 1 Đầu thu TV Box)"]
    Compare -- "Có thiết bị bị hỏng" --> Broken["🚨 CÓ THIẾT BỊ HỎNG\n(Ví dụ: Remote hết pin / hỏng nút)"]
    Compare -- "Đầy đủ & Hoạt động tốt" --> Compliant["✅ ĐẠT CHUẨN (Compliant)\nPhòng sẵn sàng đón khách"]

    Missing --> AlertRoom["Đổi trạng thái phòng sang CẢNH BÁO"]
    Broken --> AlertRoom
    Compliant --> ReadyRoom["Đổi trạng thái phòng sang SẴN SÀNG"]
```

---

## 5. LUỒNG NGHIỆP VỤ 3: GIÁM SÁT TRỰC QUAN THỜI GIAN THỰC (LIVE REALTIME FLOOR MAP)

Sơ đồ mặt bằng tương tác giúp nhân viên Lễ tân và Quản lý theo dõi tình trạng toàn bộ khách sạn trong một màn hình duy nhất:

```mermaid
flowchart LR
    subgraph UI ["Giao diện Người dùng"]
        BlockFilter["Bộ lọc Block\n(Khu A, B, C, Villa)"]
        FloorNav["Điều hướng Tầng\n(Tầng 1 → Tầng 7)"]
        MatrixGrid["Ma trận Lưới Phòng\n(Grid Rows x Columns)"]
        RoomCard["Thẻ Phòng Màu sắc\n(Xanh / Đỏ / Vàng)"]
    end

    subgraph Modal ["Thao tác Chi tiết Phòng"]
        ViewEquip["Xem danh sách thiết bị thực tế"]
        QuickPhoto["Xem ảnh chụp sắc nét"]
        QuickAssign["Gán thiết bị nhanh từ kho"]
        EditConfig["Sửa cơ cấu phòng"]
    end

    BlockFilter --> MatrixGrid
    FloorNav --> MatrixGrid
    MatrixGrid --> RoomCard
    RoomCard -->|"Click chuột vào phòng"| Modal
```

- **Mã màu trạng thái phòng:**
  - 🟢 **Xanh lá (Ready/Good):** Tất cả thiết bị đầy đủ theo định mức và hoạt động hoàn hảo.
  - 🔴 **Đỏ (Has Broken Issues):** Có ít nhất một thiết bị đang bị báo hỏng cần xử lý gấp.
  - 🟡 **Vàng (Warning / Missing):** Thiếu thiết bị theo định mức hoặc đang chờ cấp bù từ kho.
  - ⚪ **Xám (In Maintenance / Cleaning):** Phòng đang dọn dẹp hoặc sửa chữa tổng thể.

---

## 6. LUỒNG NGHIỆP VỤ 4: NHẬP/XUẤT & ĐỐI SOÁT DỮ LIỆU HÀNG LOẠT (EXCEL TRANSFER)

Quy trình nhập liệu hàng loạt và kiểm kê tài sản định kỳ:

```mermaid
flowchart TD
    Step1["1. Người dùng bấm 'Tải file mẫu Excel (.xlsx)'"]
    Step2["2. Nhập dữ liệu theo template:\nSố phòng, Tên thiết bị, Mã Model, Số Serial, Tình trạng"]
    Step3["3. Tải file lên hệ thống qua Modal Import"]
    Step4["4. Backend Validation kiểm tra từng dòng:\n- Phòng có tồn tại không?\n- Serial có bị trùng lặp không?\n- Model có trong danh mục không?"]
    Step5{"Kết quả kiểm tra"}
    Step6["5. Nhập thành công toàn bộ\n(Tự động gán vào phòng và tính lại định mức)"]
    Step7["5. Báo cáo chi tiết dòng bị lỗi\n(Cho phép sửa và tải lại)"]

    Step1 --> Step2 --> Step3 --> Step4 --> Step5
    Step5 -- "Hợp lệ" --> Step6
    Step5 -- "Có lỗi dữ liệu" --> Step7
```

---

## 7. LUỒNG NGHIỆP VỤ 5: QUẢN TRỊ TÀI KHOẢN & PHÂN QUYỀN ĐA CHI NHÁNH (MULTI-TENANT RBAC)

Đảm bảo an toàn bảo mật khi một tập đoàn quản lý nhiều chi nhánh khách sạn:

```mermaid
sequenceDiagram
    autonumber
    actor User as Quản trị viên / Nhân viên
    participant Web as Trình duyệt (Vue 3 SPA)
    participant Guard as Middleware & Backend API
    participant DB as Database (Multi-tenant DB)

    User->>Web: Nhập tài khoản & Mật khẩu
    Web->>Guard: POST /api/auth/login
    Guard->>DB: Xác thực mật khẩu
    Guard-->>Web: Trả về Token Sanctum + Danh sách Tổ chức được truy cập
    
    Note over Web,Guard: Bước chọn tổ chức làm việc
    Web->>User: Hiển thị Dialog: Chọn Chi nhánh làm việc (Ví dụ: Khách sạn A)
    User->>Web: Chọn "Chi nhánh A" (Organization #1)
    Web->>Guard: POST /api/auth/switch-organization (org_id: 1)
    Guard-->>Web: Trả về Bộ Quyền (Abilities) tương ứng tại Chi nhánh A
    Web->>Web: CASL Ability khởi tạo quyền trên giao diện
    
    Note over Web,Guard: Mọi API tiếp theo
    Web->>Guard: Gửi Bearer Token + Header 'X-Organization-Id: 1'
    Guard->>Guard: Middleware 'set.permissions.team' đặt context tổ chức 1
    Guard->>DB: Truy vấn dữ liệu phạm vi Chi nhánh 1
    Guard-->>Web: Trả về dữ liệu phòng và thiết bị của Chi nhánh 1
```

---

## 8. MA TRẬN CHUYỂN ĐỔI TRẠNG THÁI THIẾT BỊ (STATE TRANSITION MATRIX)

Bảng quy định các trạng thái hợp lệ khi luân chuyển thiết bị:

| Trạng thái Hiện tại | Trạng thái Tiếp theo Hợp lệ | Thao tác kích hoạt | Ai có quyền thực hiện? |
| :--- | :--- | :--- | :--- |
| **Tồn kho (in_stock)** | Hoạt động tốt (`good`) | Gán thiết bị vào phòng cụ thể. | Quản lý kho, Quản trị viên |
| **Tồn kho (in_stock)** | Thanh lý (`disposed`) | Bỏ thiết bị ra khỏi danh sách tài sản. | Ban Quản lý |
| **Hoạt động tốt (good)** | Báo hỏng (`broken`) | Phát hiện hỏng hóc, gửi ảnh sự cố. | Buồng phòng, Kỹ thuật viên, Lễ tân |
| **Hoạt động tốt (good)** | Tồn kho (`in_stock`) | Tháo dỡ thiết bị khỏi phòng đem về kho. | Kỹ thuật viên, Quản lý kho |
| **Báo hỏng (broken)** | Đang sửa chữa (`under_maintenance`) | Kỹ thuật viên tiếp nhận xử lý phiếu. | Kỹ thuật viên bảo trì |
| **Đang sửa chữa (under_maintenance)** | Hoạt động tốt (`good`) | Sửa xong, nghiệm thu đạt chuẩn. | Kỹ thuật viên bảo trì |
| **Đang sửa chữa (under_maintenance)** | Thanh lý (`disposed`) | Hư hỏng nặng không thể sửa, xuất hủy. | Ban Quản lý, Kỹ thuật viên trưởng |

---

## 📌 KẾT LUẬN
Tài liệu luồng nghiệp vụ trên là kim chỉ nam cho các thành viên trong đội ngũ phát triển, kiểm thử (QA), và người dùng cuối. Bất kỳ tính năng mới nào được bổ sung đều phải tuân thủ và tích hợp đồng bộ vào các luồng nghiệp vụ đã được chuẩn hóa này.
