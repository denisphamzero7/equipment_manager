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

    expect(updater.can('index', 'Room')).toBe(false)
    expect(updater.can('read', 'Room')).toBe(false)
    expect(updater.can('update', 'Room')).toBe(true)
    expect(viewer.can('index', 'Room')).toBe(true)
    expect(viewer.can('read', 'Room')).toBe(true)
  })
})
