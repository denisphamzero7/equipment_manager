# Equipment Manager - Claude Code Guidelines

## Project Overview
Hệ thống Quản lý Thiết bị (Equipment Manager):
- **Backend:** Laravel (PHP 8.x), RESTful APIs, MySQL.
- **Frontend:** Vue 3 (Vite, Pinia, Vue Router, Tailwind/CSS), Single Page Application.
- **Container Environment:** Docker container `equipment_manager-app-1` (Nginx + PHP-FPM).

---

## 📌 Quy tắc Bắt buộc khi Thay đổi Code

### 1. Khi chỉnh sửa Frontend (Vue/JS/CSS):
- Luôn chạy build tại thư mục `Frontend`:
  ```bash
  cd Frontend
  npm run build
  ```
- Đồng bộ bản build vào container Docker:
  ```bash
  docker cp Frontend/dist/. equipment_manager-app-1:/var/www/html/public/
  ```

### 2. Khi chỉnh sửa Backend (PHP/Laravel):
- Kiểm tra cú pháp PHP:
  ```bash
  php -l <file_path>
  ```
- Đồng bộ mã nguồn vào Docker container:
  ```bash
  docker cp Backend/<path> equipment_manager-app-1:/var/www/html/<path>
  ```
- Xóa cache Laravel trong container:
  ```bash
  docker exec equipment_manager-app-1 php artisan optimize:clear
  ```

### 3. Reload Nginx sau khi cập nhật:
```bash
docker exec equipment_manager-app-1 nginx -s reload
```

---

## 🔒 Quy tắc Git & An toàn
- Tuyệt đối không commit hoặc làm lộ các file nhạy cảm: `.env`, credentials, tokens.
- Tuân thủ quy chuẩn Conventional Commits (`feat:`, `fix:`, `refactor:`, `docs:`, `chore:`).
- Tham khảo tài liệu nghiệp vụ chi tiết tại `docs/` nếu cần hiểu sâu về workflow hệ thống:
  - `docs/AI_FEATURE_DEVELOPMENT_GUIDE.md`
  - `docs/BUSINESS_WORKFLOW_GUIDE.md`
  - `docs/SYSTEM_OPTIMIZATION_AND_BEST_PRACTICES.md`
