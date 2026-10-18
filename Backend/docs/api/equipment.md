# API Quản lý Trang thiết bị (Equipment)

Module quản lý toàn bộ vòng đời trang thiết bị khách sạn/tòa nhà: Sơ đồ mặt bằng Map View, đối soát định mức phòng, quản lý cá thể theo Serial/QR, điều chuyển, báo hỏng, xuất/nhập Excel.

**Base path:** `/api/equipments`

**Xác thực:** Yêu cầu Header `Authorization: Bearer <token>`

---

## 1. Sơ đồ Khách sạn & Mặt bằng (Hotel Map View)

### 1.1. Danh sách Block / Khu vực
Lấy danh sách các Block/Khu vực hiện có để hiển thị bộ lọc sơ đồ mặt bằng (Olalani Hotel, Block A, B, C, Condos, Villa...).

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/equipments/map-view/blocks` |
| **Response** | Danh sách tên các Block. |

**Ví dụ Response:**
```json
{
  "success": true,
  "message": "Lấy danh sách Block thành công",
  "data": [
    "Olalani Hotel",
    "Block A",
    "Block B",
    "Block C",
    "Block D",
    "Condos 1",
    "Condos 2",
    "Penthouse-spa+Villa"
  ]
}
```

---

### 1.2. Dữ liệu Sơ đồ Mặt bằng (Map View)
Lấy danh sách các phòng theo Block và Tầng kèm trạng thái cảnh báo thiết bị (xanh, đỏ, vàng) để vẽ giao diện sơ đồ mặt bằng (Grid View).

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/equipments/map-view` |
| **Query** | `block` (string): Tên block (ví dụ: `Block A`), `floor` (int): Tầng cần xem (ví dụ: `3`). |
| **Response** | Danh sách phòng kèm tọa độ grid, số lượng thiết bị, và trạng thái màu sắc. |

**Ví dụ Response:**
```json
{
  "success": true,
  "message": "Lấy dữ liệu Map View thành công",
  "data": [
    {
      "id": 101,
      "room_number": "101",
      "name": "Phòng 101 Deluxe",
      "block": "Block A",
      "floor": 1,
      "grid_row": 1,
      "grid_col": 1,
      "status": "available",
      "equipment_status": "good",
      "badge_color": "green",
      "stats": {
        "total_equipments": 5,
        "broken_count": 0,
        "missing_count": 0
      }
    }
  ]
}
```

---

## 2. Chi tiết Thiết bị Phòng & Đối soát Định mức

### 2.1. Chi tiết thiết bị khi click chọn 1 phòng
Hiển thị toàn bộ TV, Remote, Box, Wifi của phòng; phân loại theo quy cách (32", 40"), tình trạng tốt/hỏng và đối soát thiết bị thiếu so với định mức tiêu chuẩn.

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/equipments/rooms/{roomId}` |
| **UrlParam** | `roomId` — ID phòng. |
| **Response** | Thông tin phòng, nhóm thiết bị thực tế, định mức chuẩn và danh sách thiếu hụt. |

**Ví dụ Response:**
```json
{
  "success": true,
  "message": "Lấy chi tiết thiết bị phòng thành công",
  "data": {
    "room_id": 101,
    "room_number": "101",
    "room_type": "VIP Suite",
    "compliance_status": "compliant",
    "categories": [
      {
        "category_code": "tv",
        "category_name": "Tivi (TV)",
        "items": [
          {
            "id": 1,
            "serial_number": "TV-LG40-001",
            "model_name": "TV LG 40 inch",
            "status": "good",
            "position_note": "Phòng khách"
          }
        ]
      }
    ],
    "missing_equipments": []
  }
}
```

---

## 3. Quản lý Thiết bị (CRUD & Phân trang)

### 3.1. Danh sách thiết bị (Phân trang & Lọc)
| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/equipments` |
| **Query** | `page` (int), `limit` (int), `search` (string: tìm theo serial/model), `status` (string: `good\|broken\|maintenance\|lost\|in_stock\|disposed`), `room_id` (int), `equipment_category_id` (int), `sort_by`, `sort_order`. |
| **Response** | Paginated collection thiết bị (`EquipmentResource`). |

---

### 3.2. Chi tiết một thiết bị
| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/equipments/{id}` |
| **UrlParam** | `id` — ID thiết bị (`room_equipments.id`). |
| **Response** | Object chi tiết thiết bị kèm thông tin model, category, phòng đang gán và lịch sử bảo trì. |

---

### 3.3. Thêm mới thiết bị
| | |
|---|---|
| **Method** | POST |
| **Path** | `/api/equipments` |
| **Body (JSON)** | `serial_number` (required, unique), `equipment_model_id` (required, int), `room_id` (optional, int - null nếu tồn kho), `status` (optional: `good\|broken\|maintenance\|lost\|in_stock\|disposed`), `position_note` (optional), `note` (optional), `installed_at` (optional, Y-m-d H:i:s). |
| **Response** | 201 Created, Object thiết bị mới tạo. |

**Ví dụ Request Body:**
```json
{
  "serial_number": "TV-SAM-32-9901",
  "equipment_model_id": 1,
  "room_id": 101,
  "status": "good",
  "position_note": "Phòng ngủ master",
  "note": "Mới nhập lô tháng 10"
}
```

---

### 3.4. Cập nhật thiết bị
| | |
|---|---|
| **Method** | PUT |
| **Path** | `/api/equipments/{id}` |
| **Body (JSON)** | `serial_number`, `equipment_model_id`, `room_id`, `status`, `position_note`, `note`, `installed_at`. |
| **Response** | Object thiết bị sau cập nhật. |

---

### 3.5. Xóa thiết bị
| | |
|---|---|
| **Method** | DELETE |
| **Path** | `/api/equipments/{id}` |
| **Response** | `{ "success": true, "message": "Xóa thiết bị thành công" }`. |

---

## 4. Nghiệp vụ Điều chuyển, Báo hỏng & Trạng thái

### 4.1. Điều chuyển thiết bị vào phòng / về kho
| | |
|---|---|
| **Method** | POST |
| **Path** | `/api/equipments/assign-to-room` |
| **Body (JSON)** | `equipment_ids` (array int - danh sách ID thiết bị), `room_id` (int\|null - truyền null nếu thu hồi về kho), `position_note` (string, optional). |
| **Response** | Thông báo kết quả điều chuyển. |

**Ví dụ Request Body:**
```json
{
  "equipment_ids": [1, 2, 3],
  "room_id": 105,
  "position_note": "Chuyển từ kho sang phòng 105"
}
```

---

### 4.2. Báo hỏng thiết bị & Tạo phiếu sửa chữa
| | |
|---|---|
| **Method** | POST |
| **Path** | `/api/equipments/{id}/report-broken` |
| **Body (JSON)** | `issue_description` (required, string - mô tả chi tiết lỗi), `cost` (optional, decimal - ước tính chi phí). |
| **Response** | Cập nhật thiết bị sang trạng thái `broken` và tạo mới bản ghi vào `equipment_maintenance_logs`. |

**Ví dụ Request Body:**
```json
{
  "issue_description": "Tivi chớp màn hình, không lên nguồn",
  "cost": 250000
}
```

---

### 4.3. Đổi trạng thái thiết bị
| | |
|---|---|
| **Method** | PATCH |
| **Path** | `/api/equipments/{id}/status` |
| **Body (JSON)** | `status` (required: `good\|broken\|maintenance\|lost\|in_stock\|disposed`). |

---

### 4.4. Cập nhật trạng thái hàng loạt
| | |
|---|---|
| **Method** | PATCH |
| **Path** | `/api/equipments/bulk/status` |
| **Body (JSON)** | `ids` (array int), `status` (string). |

---

### 4.5. Xóa hàng loạt
| | |
|---|---|
| **Method** | POST |
| **Path** | `/api/equipments/bulk/destroy` |
| **Body (JSON)** | `ids` (array int). |

---

### 4.6. Tải ảnh thiết bị / sự cố
| | |
|---|---|
| **Method** | POST |
| **Path** | `/api/equipments/{id}/upload-photo` |
| **Body (FormData)** | `photo` (file: jpeg, png, jpg, webp; max: 5MB), `collection` (optional: `photos` hoặc `damage_reports`). |

---

## 5. Thống kê & Xuất/Nhập Excel

### 5.1. Thống kê tổng quan thiết bị
| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/equipments/stats` |
| **Response** | Tổng số thiết bị, số lượng theo trạng thái, số lượng theo nhóm danh mục. |

---

### 5.2. Xuất dữ liệu ra Excel
| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/equipments/export` |
| **Query** | `status`, `equipment_category_id`, `room_id`. |
| **Response** | File download định dạng `.xlsx`. |

---

### 5.3. Nhập dữ liệu hàng loạt từ Excel
| | |
|---|---|
| **Method** | POST |
| **Path** | `/api/equipments/import` |
| **Body (FormData)** | `file` (file: xlsx, xls, csv; max: 10MB). |
| **Response** | Số lượng bản ghi đã nhập thành công và thông báo lỗi (nếu có). |
