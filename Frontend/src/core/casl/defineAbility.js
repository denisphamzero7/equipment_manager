import { AbilityBuilder, createMongoAbility } from '@casl/ability'

/**
 * Định nghĩa Ability từ danh sách quyền (permissions) và vai trò (roles) của User.
 * Hỗ trợ phân quyền CASL linh hoạt cho toàn bộ module.
 */
export function defineRulesFor(user = null, permissions = []) {
  const { can, cannot, build } = new AbilityBuilder(createMongoAbility)

  if (!user) {
    // Khách vãng lai: chỉ được đọc các trang public
    cannot('manage', 'all')
    return build()
  }

  // 1. Kiểm tra vai trò Super Admin
  const isSuperAdmin = 
    user.is_super_admin === true ||
    user.roles?.some(r => (typeof r === 'string' ? r : r.name) === 'Super Admin') ||
    permissions.includes('*')

  if (isSuperAdmin) {
    // Super Admin có toàn quyền
    can('manage', 'all')
    return build()
  }

  // 2. Map từng quyền chi tiết từ Spatie Permissions sang CASL Actions/Subjects
  permissions.forEach(perm => {
    const permName = typeof perm === 'string' ? perm : perm.name

    if (!permName) return

    // Mẫu: equipments.index -> can('read', 'Equipment')
    if (permName === 'equipments.index' || permName === 'equipments.show') {
      can('read', 'Equipment')
    }
    if (permName === 'equipments.create') {
      can('create', 'Equipment')
    }
    if (permName === 'equipments.edit') {
      can('update', 'Equipment')
    }
    if (permName === 'equipments.delete') {
      can('delete', 'Equipment')
    }
    if (permName === 'equipments.assign') {
      can('assign', 'Equipment')
    }
    if (permName === 'equipments.report_broken') {
      can('report_broken', 'Equipment')
    }
    if (permName === 'equipments.map_view') {
      can('map_view', 'Equipment')
    }
    if (permName === 'equipments.stats') {
      can('stats', 'Equipment')
    }
    if (permName === 'equipments.export') {
      can('export', 'Equipment')
    }
    if (permName === 'equipments.import') {
      can('import', 'Equipment')
    }

    // Module Phòng (Rooms)
    if (permName.startsWith('rooms.')) {
      can('read', 'Room')
    }

    // Dashboard
    can('read', 'Dashboard')
  })

  return build()
}
