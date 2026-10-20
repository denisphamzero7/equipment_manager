import { describe, it, expect } from 'vitest'
import { EquipmentStatus, EquipmentStatusMeta, EquipmentStatusOptions } from '@/core/enums/EquipmentStatus'

describe('EquipmentStatus Enum & Meta', () => {
  it('defines all standard statuses', () => {
    expect(EquipmentStatus.GOOD).toBe('good')
    expect(EquipmentStatus.BROKEN).toBe('broken')
    expect(EquipmentStatus.MAINTENANCE).toBe('maintenance')
    expect(EquipmentStatus.LOST).toBe('lost')
    expect(EquipmentStatus.IN_STOCK).toBe('in_stock')
    expect(EquipmentStatus.DISPOSED).toBe('disposed')
  })

  it('maps metadata with Vietnamese labels and icons', () => {
    expect(EquipmentStatusMeta[EquipmentStatus.GOOD].label).toBe('Hoạt động tốt')
    expect(EquipmentStatusMeta[EquipmentStatus.BROKEN].label).toBe('Hư hỏng')
    expect(EquipmentStatusMeta[EquipmentStatus.MAINTENANCE].label).toBe('Đang bảo trì')
    expect(EquipmentStatusMeta[EquipmentStatus.GOOD].color).toBe('success')
    expect(EquipmentStatusMeta[EquipmentStatus.BROKEN].color).toBe('error')
  })

  it('generates options list for select inputs', () => {
    expect(EquipmentStatusOptions.length).toBe(6)
    const goodOption = EquipmentStatusOptions.find(o => o.value === 'good')
    expect(goodOption).toBeDefined()
    expect(goodOption.title).toBe('Hoạt động tốt')
  })
})
