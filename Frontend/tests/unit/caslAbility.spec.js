import { describe, it, expect } from 'vitest'
import { defineRulesFor } from '@/core/casl/defineAbility'

describe('CASL Ability Definition', () => {
  it('grants full manage all access for Super Admin user', () => {
    const superAdmin = {
      id: 1,
      name: 'Super Admin',
      roles: ['Super Admin'],
    }
    const ability = defineRulesFor(superAdmin, ['*'])
    expect(ability.can('manage', 'all')).toBe(true)
    expect(ability.can('read', 'Equipment')).toBe(true)
    expect(ability.can('delete', 'Equipment')).toBe(true)
  })

  it('grants specific permissions for regular staff', () => {
    const staff = {
      id: 2,
      name: 'Nhân viên buồng phòng',
      roles: ['Staff'],
    }
    const permissions = ['equipments.index', 'equipments.report_broken']
    const ability = defineRulesFor(staff, permissions)

    expect(ability.can('manage', 'all')).toBe(false)
    expect(ability.can('read', 'Equipment')).toBe(true)
    expect(ability.can('report_broken', 'Equipment')).toBe(true)
    expect(ability.can('create', 'Equipment')).toBe(false)
    expect(ability.can('delete', 'Equipment')).toBe(false)
  })

  it('denies all actions for unauthenticated guest', () => {
    const ability = defineRulesFor(null, [])
    expect(ability.can('manage', 'all')).toBe(false)
    expect(ability.can('read', 'Equipment')).toBe(false)
  })

  it('requires the room index permission to access the room listing', () => {
    const updater = defineRulesFor({ id: 3, roles: ['Staff'] }, ['motel.update'])
    const viewer = defineRulesFor({ id: 4, roles: ['Staff'] }, ['motel.index'])
    const detailViewer = defineRulesFor({ id: 5, roles: ['Staff'] }, ['motel.show'])

    expect(updater.can('index', 'Room')).toBe(true)
    expect(updater.can('read', 'Room')).toBe(true)
    expect(updater.can('update', 'Room')).toBe(true)
    expect(viewer.can('index', 'Room')).toBe(true)
    expect(viewer.can('read', 'Room')).toBe(true)
    expect(detailViewer.can('index', 'Room')).toBe(false)
    expect(detailViewer.can('read', 'Room')).toBe(true)
  })

  it('maps room import and export permissions to their own actions', () => {
    const importer = defineRulesFor({ id: 6, roles: ['Staff'] }, ['motel.import'])
    const exporter = defineRulesFor({ id: 7, roles: ['Staff'] }, ['motel.export'])

    expect(importer.can('import', 'Room')).toBe(true)
    expect(importer.can('export', 'Room')).toBe(false)
    expect(exporter.can('export', 'Room')).toBe(true)
    expect(exporter.can('import', 'Room')).toBe(false)
  })

  it('maps user, role and permission CRUD to their management actions', () => {
    const manager = defineRulesFor(
      { id: 8, roles: ['Staff'] },
      [
        'users.index',
        'users.store',
        'users.update',
        'users.destroy',
        'roles.index',
        'roles.store',
        'permissions.tree',
        'permissions.store',
        'permissions.update',
        'permissions.destroy',
      ]
    )

    expect(manager.can('read', 'Users')).toBe(true)
    expect(manager.can('create', 'Users')).toBe(true)
    expect(manager.can('update', 'Users')).toBe(true)
    expect(manager.can('delete', 'Users')).toBe(true)
    expect(manager.can('read', 'Roles')).toBe(true)
    expect(manager.can('create', 'Roles')).toBe(true)
    expect(manager.can('update', 'Roles')).toBe(false)
    expect(manager.can('tree', 'Permissions')).toBe(true)
    expect(manager.can('create', 'Permissions')).toBe(true)
    expect(manager.can('delete', 'Permissions')).toBe(true)
  })
})
