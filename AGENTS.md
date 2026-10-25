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

## 🔒 2. Quy tắc An toàn & Bảo mật
- Luôn kiểm tra `.gitignore` để đảm bảo các file nhạy cảm (`.env`, `storage/`, `vendor/`, `node_modules/`, tokens) không bị lộ.
- Giữ vững tính toàn vẹn của code và các tài liệu liên quan trong dự án.

## 📚 3. Hướng dẫn phát triển tính năng bằng AI
- Trước khi thêm/sửa tính năng, đọc [docs/AI_FEATURE_DEVELOPMENT_GUIDE.md](docs/AI_FEATURE_DEVELOPMENT_GUIDE.md) để theo đúng kiến trúc, quy ước UI, hiệu năng và phạm vi thay đổi của dự án.
- Khi cấu hình môi trường, xử lý sự cố hiệu năng (tốc độ tải dữ liệu < 1s), tham khảo [docs/AI_CONFIGURATION_AND_PERFORMANCE_GUIDE.md](docs/AI_CONFIGURATION_AND_PERFORMANCE_GUIDE.md).
- Tham khảo các điểm tối ưu, tính năng tiêu biểu và luồng xử lý mẫu tại [docs/SYSTEM_OPTIMIZATION_AND_BEST_PRACTICES.md](docs/SYSTEM_OPTIMIZATION_AND_BEST_PRACTICES.md).
- Đặc tả chi tiết toàn bộ luồng nghiệp vụ hệ thống tại [docs/BUSINESS_WORKFLOW_GUIDE.md](docs/BUSINESS_WORKFLOW_GUIDE.md).
