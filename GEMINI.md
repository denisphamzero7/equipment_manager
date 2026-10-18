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
