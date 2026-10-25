/**
 * Danh sách Vai trò (Roles) trong hệ thống
 */
export const UserRole = Object.freeze({
  SUPER_ADMIN: 'Super Admin',
  ADMIN: 'Admin',
  MANAGER: 'Quản lý',
  STAFF: 'Nhân viên',
  TECHNICIAN: 'Kỹ thuật viên',
})

/**
 * Các vai trò có đặc quyền Quản trị tối cao (Super Admin)
 */
export const SuperAdminRoles = Object.freeze([
  UserRole.SUPER_ADMIN,
  UserRole.ADMIN,
  'Super Admin',
  'Admin',
])

export function isSuperAdminRole(roleName) {
  if (!roleName) return false
  return SuperAdminRoles.includes(roleName)
}
