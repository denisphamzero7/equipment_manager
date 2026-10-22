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

  // 1. Kiểm tra vai trò Super Admin / Admin
  const isSuperAdmin = 
    user.is_super_admin === true ||
    user.roles?.some(r => {
      const name = typeof r === 'string' ? r : r.name
      return name === 'Super Admin' || name === 'Admin'
    }) ||
    user.assignments?.some(a => {
      const name = a.role_name || a.name
      return name === 'Super Admin' || name === 'Admin'
    }) ||
    permissions.includes('*') ||
    permissions.includes('all')

  if (isSuperAdmin) {
    // Super Admin & Admin có toàn quyền quản trị
    can('manage', 'all')
    return build()
  }

  // 2. Map từng quyền chi tiết từ Spatie Permissions sang CASL Actions/Subjects
  permissions.forEach(perm => {
    const permName = typeof perm === 'string' ? perm : perm.name

    if (!permName) return

    // Module Trang thiết bị (Equipments)
    if (permName === 'equipments.index' || permName === 'equipments.show') {
      can('read', 'Equipment')
    }
    if (permName === 'equipments.create' || permName === 'equipments.store') {
      can('create', 'Equipment')
    }
    if (permName === 'equipments.edit' || permName === 'equipments.update') {
      can('update', 'Equipment')
    }
    if (permName === 'equipments.delete' || permName === 'equipments.destroy') {
      can('delete', 'Equipment')
    }
    if (permName === 'equipments.assign' || permName === 'equipments.assignToRoom') {
      can('assign', 'Equipment')
    }
    if (permName === 'equipments.report_broken' || permName === 'equipments.reportBroken') {
      can('report_broken', 'Equipment')
    }
    if (permName === 'equipments.map_view' || permName === 'equipments.mapView') {
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

    // Module Phòng (Rooms / Motel)
    if (permName.startsWith('rooms.') || permName.startsWith('motel.')) {
      if (permName === 'motel.index' || permName === 'rooms.index') {
        can('read', 'Room')
        can('index', 'Room')
      }
      if (permName === 'motel.show' || permName === 'rooms.show') {
        can('read', 'Room')
      }
      if (permName === 'motel.update' || permName === 'rooms.edit' || permName === 'rooms.update') {
        can('read', 'Room')
        can('index', 'Room')
        can('update', 'Room')
      }
      if (permName === 'motel.store' || permName === 'rooms.create' || permName === 'rooms.store') {
        can('create', 'Room')
      }
      if (permName === 'motel.destroy' || permName === 'rooms.delete' || permName === 'rooms.destroy') {
        can('delete', 'Room')
      }
    }

    // Dashboard
    can('read', 'Dashboard')
  })

  return build()
}
