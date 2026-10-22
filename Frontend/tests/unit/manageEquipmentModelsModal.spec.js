import { flushPromises, shallowMount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import ManageEquipmentModelsModal from '@/modules/equipment/components/ManageEquipmentModelsModal.vue'

vi.mock('@/modules/equipment/services/equipmentService', () => ({
  equipmentService: {
    getCategories: vi.fn(),
    getModels: vi.fn(),
  },
}))

import { equipmentService } from '@/modules/equipment/services/equipmentService'

describe('ManageEquipmentModelsModal', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    equipmentService.getCategories.mockResolvedValue([{ id: 1, name: 'Tivi' }])
    equipmentService.getModels.mockResolvedValue([])
  })

  it('loads models and categories when mounted already open', async () => {
    const wrapper = shallowMount(ManageEquipmentModelsModal, {
      props: { modelValue: true },
    })

    await flushPromises()

    expect(equipmentService.getModels).toHaveBeenCalledOnce()
    expect(equipmentService.getCategories).toHaveBeenCalledOnce()
    expect(wrapper.vm.categories).toEqual([{ id: 1, name: 'Tivi' }])
  })
})
