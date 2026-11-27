import { flushPromises, mount } from '@vue/test-utils'
import { reactive } from 'vue'
import { beforeEach, describe, expect, it, vi } from 'vitest'

const {
  getMock,
  toastErrorMock,
  mockRealtimeStore,
  mockRoute,
  mockRouterPush,
} = vi.hoisted(() => ({
  getMock: vi.fn(),
  toastErrorMock: vi.fn(),
  mockRealtimeStore: { lastEvent: null },
  mockRoute: { query: {} },
  mockRouterPush: vi.fn(),
}))

vi.mock('@/core/helpers/storage', () => ({
  StorageKeys: {
    ORGANIZATION_ID: 'organization_id',
    PERMISSIONS: 'permissions',
    ROLES: 'roles',
    USER: 'user',
    TOKEN: 'token',
  },
  storage: {
    get: vi.fn((key) => {
      if (key === 'organization_id') return '22'
      if (key === 'permissions' || key === 'roles') return []
      return null
    }),
    set: vi.fn(),
    remove: vi.fn(),
  },
}))

vi.mock('@/core/helpers/toast', () => ({
  toast: {
    success: vi.fn(),
    error: toastErrorMock,
    warning: vi.fn(),
    info: vi.fn(),
  },
}))

vi.mock('@/core/api/axiosClient', () => ({
  default: {
    get: getMock,
    post: vi.fn(),
    put: vi.fn(),
    delete: vi.fn(),
  },
}))

vi.mock('@/core/stores/realtimeStore', () => ({
  useRealtimeStore: () => mockRealtimeStore,
}))

vi.mock('vue-router', () => ({
  useRoute: () => mockRoute,
  useRouter: () => ({ push: mockRouterPush }),
}))

describe('Equipment Map View refresh and cache bypass', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    vi.resetModules()
    mockRealtimeStore.lastEvent = null
    mockRoute.query = {}
  })

  it('bypasses 30s client cache in equipmentService and dashboardService when forceRefresh=true', async () => {
    const { invalidateRequestCache } = await import('@/core/services/requestCache')
    const { equipmentService } = await import('@/modules/equipment/services/equipmentService')
    const { dashboardService } = await import('@/modules/dashboard/services/dashboardService')

    invalidateRequestCache('equipment-map', 'equipment-room-detail', 'dashboard-stats')

    getMock
      .mockResolvedValueOnce({ data: { data: [{ id: 1, room_number: '1265', equipment_status: 'unconfigured' }] } })
      .mockResolvedValueOnce({ data: { data: [{ id: 1, room_number: '1265', equipment_status: 'broken' }] } })
      .mockResolvedValueOnce({ data: { data: { id: 1, missing_equipments: [{ category_code: 'tv' }] } } })
      .mockResolvedValueOnce({ data: { data: { id: 1, missing_equipments: [] } } })
      .mockResolvedValueOnce({ data: { data: { total_equipments: 10 } } })
      .mockResolvedValueOnce({ data: { data: { total_equipments: 11 } } })

    const cachedFirst = await equipmentService.getMapView({})
    const cachedAgain = await equipmentService.getMapView({})
    expect(cachedFirst[0].equipment_status).toBe('unconfigured')
    expect(cachedAgain[0].equipment_status).toBe('unconfigured')
    expect(getMock).toHaveBeenCalledTimes(1)

    const forcedMap = await dashboardService.getMapOverview(true)
    expect(forcedMap[0].equipment_status).toBe('broken')
    expect(getMock).toHaveBeenCalledTimes(2)

    const cachedDetail = await equipmentService.getRoomDetail(1)
    expect(cachedDetail.missing_equipments).toHaveLength(1)
    expect(getMock).toHaveBeenCalledTimes(3)

    const forcedDetail = await equipmentService.getRoomDetail(1, true)
    expect(forcedDetail.missing_equipments).toEqual([])
    expect(getMock).toHaveBeenCalledTimes(4)

    await dashboardService.getStats('', false)
    await dashboardService.getStats('', false)
    expect(getMock).toHaveBeenCalledTimes(5)

    const forcedStats = await dashboardService.getStats('', true)
    expect(forcedStats.total_equipments).toBe(11)
    expect(getMock).toHaveBeenCalledTimes(6)
  })

  it('reloads map view with forceRefresh=true on button click, room edit save, and realtime event', async () => {
    const reactiveStore = reactive({ lastEvent: null })
    vi.doMock('@/core/stores/realtimeStore', () => ({
      useRealtimeStore: () => reactiveStore,
    }))

    const getMapViewMock = vi.fn()
      .mockResolvedValueOnce([
        { id: 30001, room_number: '1265', block: 'Block A', floor: 1, badge_color: 'slate', equipment_status: 'unconfigured', room_type: 'Tiêu chuẩn' },
      ])
      .mockResolvedValueOnce([
        { id: 30001, room_number: '1265', block: 'Block A', floor: 1, badge_color: 'red', equipment_status: 'broken', room_type: 'Căn Hộ Gia Đình' },
      ])
      .mockResolvedValueOnce([
        { id: 30001, room_number: '1265', block: 'Block A', floor: 1, badge_color: 'green', equipment_status: 'good', room_type: 'Căn Hộ Gia Đình' },
      ])
      .mockResolvedValueOnce([
        { id: 30001, room_number: '1265', block: 'Block A', floor: 1, badge_color: 'green', equipment_status: 'good', room_type: 'Căn Hộ Gia Đình' },
      ])

    const getRoomDetailMock = vi.fn()
      .mockResolvedValueOnce({ id: 30001, room_number: '1265', room_type: 'Căn Hộ Gia Đình', missing_equipments: [{ category_code: 'tv' }] })
      .mockResolvedValueOnce({ id: 30001, room_number: '1265', room_type: 'Căn Hộ Gia Đình', missing_equipments: [] })
      .mockResolvedValueOnce({ id: 30001, room_number: '1265', room_type: 'Căn Hộ Gia Đình', missing_equipments: [] })

    vi.doMock('@/modules/equipment/services/equipmentService', () => ({
      equipmentService: {
        getMapView: getMapViewMock,
        getRoomDetail: getRoomDetailMock,
      },
    }))

    vi.doMock('@/modules/room/services/roomService', () => ({
      roomService: {
        getRoomTypes: vi.fn().mockResolvedValue([{ id: 4, name: 'Căn Hộ Gia Đình' }]),
      },
    }))

    vi.doMock('@/modules/room/components/RoomFormModal.vue', () => ({
      __esModule: true,
      __isTeleport: false,
      __isKeepAlive: false,
      default: {
        __isTeleport: false,
        __isKeepAlive: false,
        name: 'RoomFormModal',
        props: ['modelValue', 'room', 'roomTypes', 'existingBlocks'],
        emits: ['update:modelValue', 'saved'],
        template: '<div class="room-form-modal-stub" />',
      },
    }))

    const { default: EquipmentMapView } = await import('@/modules/equipment/views/equipment-map/index.vue')

    const MapFloorGridStub = {
      name: 'MapFloorGrid',
      props: ['groupedRooms', 'hasActiveFilters', 'loading'],
      emits: ['select-room', 'select-block', 'reset-filters'],
      template: '<div class="map-floor-grid-stub" :data-rooms="JSON.stringify(groupedRooms)" />',
    }

    const MapRoomDetailModalStub = {
      name: 'MapRoomDetailModal',
      props: ['modelValue', 'roomSummary', 'roomDetail', 'loading'],
      emits: ['update:modelValue', 'open-edit-room', 'open-lightbox'],
      template: '<div class="map-room-detail-stub" />',
    }

    const wrapper = mount(EquipmentMapView, {
      global: {
        mocks: {
          $can: () => true,
        },
        stubs: {
          DefaultLayout: { template: '<div><slot /></div>' },
          AppPageHeader: { template: '<div><slot name="actions" /></div>' },
          AppButton: {
            emits: ['click'],
            template: '<button type="button" @click="$emit(\'click\', $event)"><slot /></button>',
          },
          MapStatHeader: { props: ['rooms', 'loading'], template: '<div />' },
          MapFilterToolbar: { template: '<div />' },
          MapFloorGrid: MapFloorGridStub,
          MapRoomDetailModal: MapRoomDetailModalStub,
          'v-dialog': { template: '<div><slot /></div>' },
          'v-icon': { template: '<span />' },
        },
      },
    })

    await flushPromises()
    expect(getMapViewMock).toHaveBeenCalledTimes(1)
    expect(getMapViewMock).toHaveBeenLastCalledWith({}, false)

    // 1. Clicking "Tải lại sơ đồ" bypasses client RAM cache
    const refreshButton = wrapper.findAll('button').find(btn => btn.text().includes('Tải lại sơ đồ'))
    await refreshButton.trigger('click')
    await flushPromises()
    expect(getMapViewMock).toHaveBeenCalledTimes(2)
    expect(getMapViewMock).toHaveBeenLastCalledWith({}, true)
    expect(wrapper.find('.map-floor-grid-stub').attributes('data-rooms')).toContain('Căn Hộ Gia Đình')

    // 2. Opening detail modal and saving room edits forces both map & detail refresh
    wrapper.findComponent(MapFloorGridStub).vm.$emit('select-room', { id: 30001, room_number: '1265' })
    await flushPromises()
    expect(getRoomDetailMock).toHaveBeenCalledTimes(1)
    expect(getRoomDetailMock).toHaveBeenLastCalledWith(30001, false)

    wrapper.findComponent(MapRoomDetailModalStub).vm.$emit('open-edit-room')
    await flushPromises()

    const formModal = wrapper.findComponent({ name: 'RoomFormModal' })
    expect(formModal.exists()).toBe(true)
    formModal.vm.$emit('saved', { id: 30001, room_type_id: 4, room_number: '1265' })
    await flushPromises()

    expect(getMapViewMock).toHaveBeenCalledTimes(3)
    expect(getMapViewMock).toHaveBeenLastCalledWith({}, true)
    expect(getRoomDetailMock).toHaveBeenCalledTimes(2)
    expect(getRoomDetailMock).toHaveBeenLastCalledWith(30001, true)
    expect(wrapper.find('.map-floor-grid-stub').attributes('data-rooms')).toContain('"badge_color":"green"')

    // 3. Realtime event refreshes both map and open detail modal with forceRefresh=true
    reactiveStore.lastEvent = { type: 'room_updated', room: { id: 30001 } }
    await flushPromises()
    expect(getMapViewMock).toHaveBeenCalledTimes(4)
    expect(getMapViewMock).toHaveBeenLastCalledWith({}, true)
    expect(getRoomDetailMock).toHaveBeenCalledTimes(3)
    expect(getRoomDetailMock).toHaveBeenLastCalledWith(30001, true)
  })
})
