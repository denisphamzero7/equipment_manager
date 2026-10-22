import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import RoomEquipmentSummary from '@/modules/room/components/RoomEquipmentSummary.vue'

describe('RoomEquipmentSummary', () => {
  it('shows equipment model quantities and one borrowed Wi-Fi entry', () => {
    const wrapper = mount(RoomEquipmentSummary, {
      props: {
        room: {
          wifi_mode: 'shared',
          equipment_summary: [
            { name: 'TV 32in', quantity: 1 },
            { name: 'TV 40in', quantity: 1 },
            { name: 'Remote', quantity: 2 },
          ],
        },
      },
    })

    expect(wrapper.text()).toContain('TV 32in1')
    expect(wrapper.text()).toContain('TV 40in1')
    expect(wrapper.text()).toContain('Remote2')
    expect(wrapper.text()).toContain('WiFi (dùng ké)1')
  })

  it('shows an empty state when no equipment is assigned and Wi-Fi is not shared', () => {
    const wrapper = mount(RoomEquipmentSummary, {
      props: { room: { wifi_mode: 'dedicated', equipment_summary: [] } },
    })

    expect(wrapper.text()).toContain('Chưa có thiết bị được gán')
  })
})
