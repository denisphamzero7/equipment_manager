import { flushPromises, shallowMount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import EquipmentFormModal from '@/modules/equipment/components/EquipmentFormModal.vue'

vi.mock('@/modules/equipment/services/equipmentService', () => ({
  equipmentService: {
    getCategories: vi.fn(),
    getModels: vi.fn(),
  },
}))

import { equipmentService } from '@/modules/equipment/services/equipmentService'

describe('EquipmentFormModal', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    equipmentService.getCategories.mockResolvedValue([{ id: 1, name: 'Tivi & Màn hình' }])
    equipmentService.getModels.mockResolvedValue([{
      id: 10,
      name: 'TV 40in Samsung',
      brand: 'Samsung',
      category: { id: 1, name: 'Tivi & Màn hình' },
    }])
  })

  it('loads existing models and categories when mounted already open', async () => {
    const wrapper = shallowMount(EquipmentFormModal, {
      props: { modelValue: true },
    })

    await flushPromises()

    expect(equipmentService.getModels).toHaveBeenCalledOnce()
    expect(equipmentService.getCategories).toHaveBeenCalledOnce()
    expect(wrapper.vm.models).toHaveLength(1)
    expect(wrapper.vm.modelOptions).toEqual([{
      id: 10,
      title: 'TV 40in Samsung — Samsung (Tivi & Màn hình)',
    }])
    expect(wrapper.vm.categories).toEqual([{ id: 1, name: 'Tivi & Màn hình' }])
  })
})
