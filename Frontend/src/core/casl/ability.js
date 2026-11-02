import { createMongoAbility } from '@casl/ability'
import { defineRulesFor } from './defineAbility'
import { storage, StorageKeys } from '@/core/helpers/storage'

/**
 * [1. MỤC ĐÍCH]
 * Khởi tạo đối tượng CASL Ability toàn cục phục vụ kiểm tra quyền hạn giao diện:
 * - Ẩn/hiện các nút thao tác (Thêm, Sửa, Xóa, Cấp bù kho, Báo hỏng...).
 * - Kiểm tra quyền truy cập route trong Navigation Guards.
 */

// Đọc quyền ban đầu từ LocalStorage khi ứng dụng khởi động
const initialUser = storage.get(StorageKeys.USER, null)
const initialPerms = storage.get(StorageKeys.PERMISSIONS, [])

export const ability = defineRulesFor(initialUser, initialPerms)

/**
 * [1. MỤC ĐÍCH]
 * Cập nhật lại các quy tắc (rules) cho đối tượng Ability thời gian thực:
 * Được gọi khi đăng nhập thành công, chuyển đổi cơ sở khách sạn, hoặc đăng xuất.
 *
 * [2. LUỒNG XỬ LÝ (FLOW)]
 * 1. Gọi `defineRulesFor(user, permissions)` để sinh mảng rules mới.
 * 2. Cập nhật vào instance ability qua `ability.update(newAbility.rules)`.
 * 3. Toàn bộ các component Vue đang sử dụng `v-can` hoặc `$ability.can()` sẽ tự động phản hồi lại trạng thái mới.
 *
 * [3. NGUỒN GỌI (CALLERS)]
 * - `useAuthStore::login()`, `useAuthStore::selectOrganization()`, `useAuthStore::logout()`
 *
 * [4. THAM SỐ (PARAMETERS)]
 * @param {Object|null} user Thực thể user hiện tại
 * @param {Array<string>} [permissions=[]] Danh sách quyền dạng chuỗi
 */
export function updateAbility(user, permissions = []) {
  const newAbility = defineRulesFor(user, permissions)
  ability.update(newAbility.rules)
}
