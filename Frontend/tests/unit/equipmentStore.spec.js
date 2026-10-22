import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'

vi.mock('@/modules/equipment/services/equipmentService', () => ({
  equipmentService: {
    getEquipments: vi.fn(),
    updateEquipment: vi.fn(),
    changeStatus: vi.fn(),
    bulkUpdateStatus: vi.fn(),
    deleteEquipment: vi.fn(),
    bulkDestroy: vi.fn(),
    assignToRoom: vi.fn(),
    reportBroken: vi.fn(),
  },
}))

vi.mock('@/core/helpers/toast', () => ({
  toast: {
    success: vi.fn(),
    warning: vi.fn(),
  },
}))

import { equipmentService } from '@/modules/equipment/services/equipmentService'
import { useEquipmentStore } from '@/modules/equipment/stores/equipmentStore'

describe('equipmentStore mutation updates', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('updates an equipment row from the mutation response without reloading the list', async () => {
    const store = useEquipmentStore()
    store.items = [{
      id: 8,
      serial_number: 'EQ-8',
      status: 'good',
      room_id: 12,
      room: { id: 12, block: 'Block A', floor: 5 },
    }]
    store.pagination.total = 1
    equipmentService.updateEquipment.mockResolvedValue({
      data: {
        id: 8,
        serial_number: 'EQ-8-UPDATED',
        status: 'good',
        room_id: 12,
        room: { id: 12, block: 'Block A', floor: 5 },
      },
    })

    await store.updateEquipment(8, { serial_number: 'EQ-8-UPDATED' })

    expect(store.items[0].serial_number).toBe('EQ-8-UPDATED')
    expect(equipmentService.getEquipments).not.toHaveBeenCalled()
  })

  it('applies status changes in place without fetching the equipment page', async () => {
    const store = useEquipmentStore()
    store.items = [{ id: 9, status: 'good' }]
    equipmentService.changeStatus.mockResolvedValue({ data: { id: 9, status: 'broken' } })

    await store.changeStatus(9, 'broken')

    expect(store.items[0].status).toBe('broken')
    expect(equipmentService.getEquipments).not.toHaveBeenCalled()
  })

  it('adds a created equipment response in place and merges a later full response', () => {
    const store = useEquipmentStore()
    store.addEquipment({ id: 10, serial_number: 'EQ-10', status: 'good' })
    store.addEquipment({ id: 10, serial_number: 'EQ-10', status: 'good', model: { name: 'Remote' } })

    expect(store.pagination.total).toBe(1)
    expect(store.items).toHaveLength(1)
    expect(store.items[0].model.name).toBe('Remote')
    expect(equipmentService.getEquipments).not.toHaveBeenCalled()
  })
})
