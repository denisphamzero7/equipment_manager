import { createMongoAbility } from '@casl/ability'
import { defineRulesFor } from './defineAbility'
import { storage, StorageKeys } from '@/core/helpers/storage'

// Khởi tạo ability với thông tin user từ storage (nếu đã đăng nhập trước đó)
const initialUser = storage.get(StorageKeys.USER, null)
const initialPerms = storage.get(StorageKeys.PERMISSIONS, [])

export const ability = defineRulesFor(initialUser, initialPerms)

/**
 * Cập nhật lại rules cho Ability khi đăng nhập, chuyển tổ chức hoặc đổi vai trò.
 */
export function updateAbility(user, permissions = []) {
  const newAbility = defineRulesFor(user, permissions)
  ability.update(newAbility.rules)
}
