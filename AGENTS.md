# Project Rules & Guidelines (Quy tắc Dự án)

## 📌 1. Quy tắc Bắt buộc: Tự động Commit & Push lên Git sau mỗi thay đổi
- **Mục tiêu:** Đảm bảo toàn bộ tiến độ, mã nguồn, tài liệu và các cấu hình mới nhất luôn được lưu vết an toàn trên GitHub repository của dự án ngay sau khi hoàn thành công việc.
- **Quy trình thực hiện bắt buộc cho Agent:**
  1. **Kiểm tra trạng thái:** Chạy `git status` để xem các file vừa được tạo mới hoặc chỉnh sửa.
  2. **Stage thay đổi:** Chạy `git add <các file đã thay đổi>` (hoặc `git add .` nếu phù hợp). **Lưu ý:** Tuyệt đối không commit file `.env` hoặc file chứa secrets / credentials.
  3. **Tạo commit:** Commit với thông điệp rõ ràng, chuẩn định dạng Conventional Commits:
     - `feat: ...` khi thêm chức năng / module mới.
     - `fix: ...` khi sửa lỗi.
     - `docs: ...` khi cập nhật tài liệu / README / API docs.
     - `refactor: ...` khi tái cấu trúc code.
     - `chore: ...` cho các thay đổi cấu hình, dependencies, v.v.
  4. **Push lên GitHub:** Chạy `git push origin <tên-nhánh>` (mặc định là `main`).
  5. Báo cáo lại cho người dùng commit hash và tóm tắt những gì đã được đẩy lên remote repository.

---

## 🐳 2. Quy tắc Bắt buộc: Tự động Build & Cập nhật Docker sau mỗi lần sửa code thành công
- **Mục tiêu:** Đảm bảo container Docker (`equipment_manager-app-1`) luôn chạy mã nguồn và bản build Frontend/Backend mới nhất, người dùng chỉ cần F5 là thấy ngay kết quả.
- **Quy trình thực hiện bắt buộc cho Agent:**
  1. **Khi có thay đổi Frontend (Vue/JS/CSS):**
     - Chạy `npm run build` tại thư mục `Frontend` để tạo bản build mới (`Frontend/dist`).
     - Đồng bộ bản build vào container: `docker cp Frontend/dist/. equipment_manager-app-1:/var/www/html/public/`.
  2. **Khi có thay đổi Backend (PHP/Config/Migrations):**
     - Kiểm tra cú pháp PHP: `php -l <file>`.
     - Đồng bộ mã nguồn vào container: `docker cp Backend/<path> equipment_manager-app-1:/var/www/html/<path>`.
     - Xóa cache Laravel trong container: `docker exec equipment_manager-app-1 php artisan optimize:clear`.
  3. **Reload dịch vụ Web:**
     - Chạy `docker exec equipment_manager-app-1 nginx -s reload` để Nginx phục vụ ngay assets và mã nguồn mới.
  4. Báo cáo trạng thái đồng bộ Docker cho người dùng sau mỗi lượt sửa code.

---

## 🔒 3. Quy tắc An toàn & Bảo mật
- Luôn kiểm tra `.gitignore` để đảm bảo các file nhạy cảm (`.env`, `storage/`, `vendor/`, `node_modules/`, tokens) không bị lộ.
- Giữ vững tính toàn vẹn của code và các tài liệu liên quan trong dự án.

## 📚 3. Hướng dẫn phát triển tính năng bằng AI
- Trước khi thêm/sửa tính năng, đọc [docs/AI_FEATURE_DEVELOPMENT_GUIDE.md](docs/AI_FEATURE_DEVELOPMENT_GUIDE.md) để theo đúng kiến trúc, quy ước UI, hiệu năng và phạm vi thay đổi của dự án.
- Khi cấu hình môi trường, xử lý sự cố hiệu năng (tốc độ tải dữ liệu < 1s), tham khảo [docs/AI_CONFIGURATION_AND_PERFORMANCE_GUIDE.md](docs/AI_CONFIGURATION_AND_PERFORMANCE_GUIDE.md).
- Tham khảo các điểm tối ưu, tính năng tiêu biểu và luồng xử lý mẫu tại [docs/SYSTEM_OPTIMIZATION_AND_BEST_PRACTICES.md](docs/SYSTEM_OPTIMIZATION_AND_BEST_PRACTICES.md).
- Đặc tả chi tiết toàn bộ luồng nghiệp vụ hệ thống tại [docs/BUSINESS_WORKFLOW_GUIDE.md](docs/BUSINESS_WORKFLOW_GUIDE.md).
- Cẩm nang nghiệp vụ thực chiến cho người quản lý thiết bị tại [docs/USER_GUIDE_EQUIPMENT_MANAGER.md](docs/USER_GUIDE_EQUIPMENT_MANAGER.md).
