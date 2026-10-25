<script setup>
import { ref, reactive, computed, onMounted, watch, defineAsyncComponent } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { roomService } from '../../services/roomService'
import { equipmentService } from '@/modules/equipment/services/equipmentService'
import { useRealtimeStore } from '@/core/stores/realtimeStore'
import { toast } from '@/core/helpers/toast'
import { HotelBlockOrder } from '@/core/enums'
import { resolveMediaUrl } from '@/core/helpers/formatters'
import DefaultLayout from '@/core/layouts/DefaultLayout.vue'
import AppPageHeader from '@/core/components/AppPageHeader.vue'
import AppButton from '@/core/components/AppButton.vue'
import AppConfirmDialog from '@/core/components/AppConfirmDialog.vue'

// Domain Child Components
import RoomAuditStats from '../../components/RoomAuditStats.vue'
import RoomAuditFilterBar from '../../components/RoomAuditFilterBar.vue'
import RoomAuditSidebarList from '../../components/RoomAuditSidebarList.vue'
import RoomAuditDetailCard from '../../components/RoomAuditDetailCard.vue'
import RoomAssignWarehouseModal from '../../components/RoomAssignWarehouseModal.vue'

// Reused Async Action Modals
const AssignRoomModal = defineAsyncComponent(() => import('@/modules/equipment/components/AssignRoomModal.vue'))
const ReportBrokenModal = defineAsyncComponent(() => import('@/modules/equipment/components/ReportBrokenModal.vue'))
const EquipmentFormModal = defineAsyncComponent(() => import('@/modules/equipment/components/EquipmentFormModal.vue'))
const RoomFormModal = defineAsyncComponent(() => import('../../components/RoomFormModal.vue'))

const route = useRoute()
const router = useRouter()
const realtimeStore = useRealtimeStore()

const loading = ref(false)
const actionLoading = ref(false)
const rooms = ref([])
const selectedRoomId = ref(null)
const roomDetail = ref(null)
let roomDetailRequestId = 0

// Standard hotel block order
const hotelBlockOrder = HotelBlockOrder

// Mobile active view tab: 'detail' | 'list'
const mobileActiveTab = ref('detail')

// Filter States
const filters = reactive({
  search: '',
  block: '',
  floor: null,
  status: '', // '' | 'compliant' | 'missing' | 'maintenance'
  room_type_id: null,
})

// Room types for filter & edit
const roomTypesList = ref([])

// Action Modals State
const assignModalOpen = ref(false)
const selectedEquipmentForAssign = ref(null)

const reportBrokenModalOpen = ref(false)
const selectedEquipmentForReport = ref(null)

const retractDialogOpen = ref(false)
const equipmentToRetract = ref(null)

const createModalOpen = ref(false)

const warehouseModalOpen = ref(false)
const warehouseLoading = ref(false)
const warehouseEquipments = ref([])

// Sửa nhanh cơ cấu phòng
const editRoomModalOpen = ref(false)

// Lightbox state
const lightboxOpen = ref(false)
const lightboxImage = ref('')
const lightboxTitle = ref('')
const lightboxSubtitle = ref('')

onMounted(async () => {
  await Promise.allSettled([
    loadRooms(),
    fetchRoomTypes(),
  ])
})

async function fetchRoomTypes() {
  try {
    const types = await roomService.getRoomTypes()
    roomTypesList.value = Array.isArray(types) ? types : []
  } catch (err) {
    console.error('Failed to load room types:', err)
  }
}

// Blocks extraction & sorting
const existingBlocks = computed(() => {
  const set = new Set(rooms.value.map(r => r.block).filter(Boolean))
  hotelBlockOrder.forEach(b => set.add(b))
  return Array.from(set).sort((a, b) => {
    const idxA = hotelBlockOrder.indexOf(a)
    const idxB = hotelBlockOrder.indexOf(b)
    if (idxA !== -1 && idxB !== -1) return idxA - idxB
    if (idxA !== -1) return -1
    if (idxB !== -1) return 1
    return a.localeCompare(b, 'vi', { numeric: true })
  })
})

const blockCounts = computed(() => {
  const map = {}
  rooms.value.forEach(r => {
    const b = r.block || 'Block A'
    map[b] = (map[b] || 0) + 1
  })
  return map
})

const floorOptions = computed(() => {
  let list = rooms.value
  if (filters.block) {
    list = list.filter(r => r.block === filters.block)
  }
  const floors = Array.from(new Set(list.map(r => r.floor).filter(f => f !== null && f !== undefined)))
    .sort((a, b) => Number(a) - Number(b))
  return [
    { value: null, title: 'Tất cả Tầng' },
    ...floors.map(f => ({ value: f, title: `Tầng ${f}` })),
  ]
})

const complianceOptions = [
  { value: '', title: 'Tất cả định mức' },
  { value: 'compliant', title: '🟢 Đạt chuẩn 100% (Đủ thiết bị)' },
  { value: 'missing', title: '🔴 Chưa đạt chuẩn (Thiếu thiết bị)' },
  { value: 'maintenance', title: '🟡 Có thiết bị bảo trì' },
]

const roomTypeOptions = computed(() => {
  const types = roomTypesList.value.length > 0
    ? roomTypesList.value
    : Array.from(new Set(rooms.value.map(r => r.room_type).filter(Boolean))).map((t, idx) => ({ id: idx + 1, name: t }))
  return [
    { value: null, title: 'Tất cả loại phòng' },
    ...types.map(t => ({ value: t.id, title: t.name })),
  ]
})

// Overview stats
const auditStats = computed(() => {
  const total = rooms.value.length
  const compliant = rooms.value.filter(r => r.badge_color === 'green').length
  const missing = rooms.value.filter(r => r.badge_color === 'red' || (r.stats?.missing_count > 0)).length
  const maintenance = rooms.value.filter(r => r.badge_color === 'yellow' || (r.stats?.maintenance_count > 0)).length
  const rawRate = total > 0 ? (compliant / total) * 100 : 0
  const rate = rawRate === 0 || rawRate === 100 ? rawRate.toFixed(0) : rawRate.toFixed(1)
  return { total, compliant, missing, maintenance, rate }
})

// Filtered rooms list
const filteredRooms = computed(() => {
  return rooms.value.filter(r => {
    if (filters.search) {
      const kw = filters.search.trim().toLowerCase()
      const matchNum = String(r.room_number || '').toLowerCase().includes(kw)
      const matchName = String(r.name || '').toLowerCase().includes(kw)
      if (!matchNum && !matchName) return false
    }
    if (filters.block && r.block !== filters.block) {
      return false
    }
    if (filters.floor !== null && filters.floor !== undefined && r.floor !== filters.floor) {
      return false
    }
    if (filters.status) {
      if (filters.status === 'compliant' && r.badge_color !== 'green') return false
      if (filters.status === 'missing' && (r.badge_color !== 'red' && !(r.stats?.missing_count > 0))) return false
      if (filters.status === 'maintenance' && (r.badge_color !== 'yellow' && !(r.stats?.maintenance_count > 0))) return false
    }
    if (filters.room_type_id) {
      if (r.room_type_id !== filters.room_type_id) return false
    }
    return true
  })
})

const hasActiveFilters = computed(() => {
  return !!(filters.search || filters.block || filters.floor !== null || filters.status || filters.room_type_id)
})

function selectBlock(b) {
  filters.block = b
  filters.floor = null
}

function resetFilters() {
  filters.search = ''
  filters.block = ''
  filters.floor = null
  filters.status = ''
  filters.room_type_id = null
}

// Auto select first room if current selected is filtered out
watch(filteredRooms, (newList) => {
  if (newList.length > 0) {
    const exists = newList.some(r => r.id === selectedRoomId.value)
    if (!exists) {
      selectedRoomId.value = newList[0].id
      loadRoomDetail(newList[0].id)
    }
  }
})

async function openEditRoomModal() {
  if (!roomDetail.value) return
  if (roomTypesList.value.length === 0) {
    await fetchRoomTypes()
  }
  editRoomModalOpen.value = true
}

async function handleRoomSaved(savedRoom) {
  if (savedRoom?.id) {
    rooms.value = rooms.value.map(room =>
      Number(room.id) === Number(savedRoom.id) ? { ...room, ...savedRoom } : room
    )
  } else {
    await loadRooms({ silent: true })
  }
  if (selectedRoomId.value) {
    await loadRoomDetail(selectedRoomId.value, { silent: true })
  }
}

// Tự động đối soát lại phòng khi có thiết bị thay đổi Real-time
watch(
  () => realtimeStore.lastEvent,
  async (event) => {
    if (!event) return
    if (selectedRoomId.value) {
      await loadRoomDetail(selectedRoomId.value, { silent: true })
    }
    void loadRooms({ silent: true })
  }
)

async function loadRooms({ silent = false } = {}) {
  try {
    const data = await equipmentService.getMapView()
    rooms.value = data || []
    
    // Nếu có query param room_id từ URL thì ưu tiên chọn phòng đó
    const queryRoomId = Number(route.query.room_id)
    if (!selectedRoomId.value) {
      if (queryRoomId && rooms.value.some(r => r.id === queryRoomId)) {
        selectedRoomId.value = queryRoomId
      } else if (rooms.value.length > 0) {
        selectedRoomId.value = rooms.value[0].id
      }

      if (selectedRoomId.value) {
        await loadRoomDetail(selectedRoomId.value, { silent })
      }
    }
  } catch (e) {
    console.error('Failed to load rooms:', e)
    if (!silent) toast.error(e.response?.data?.message || 'Không thể tải danh sách phòng.')
  }
}

function syncRoomStatsInPlace(id, detail) {
  if (!detail) return
  const r = rooms.value.find(item => Number(item.id) === Number(id))
  if (!r) return

  r.badge_color = detail.compliance_status === 'compliant'
    ? 'green'
    : (detail.missing_equipments?.length > 0 ? 'red' : 'yellow')

  if (!r.stats) r.stats = {}
  
  const missingCount = detail.missing_equipments?.reduce((acc, m) => acc + (m.missing_count || 1), 0) || 0
  r.stats.missing_count = missingCount
  
  const allItems = detail.categories?.flatMap(c => c.items || []) || []
  r.stats.total_equipments = allItems.length
  r.stats.broken_count = allItems.filter(i => i.status === 'broken').length
  r.stats.maintenance_count = allItems.filter(i => i.status === 'maintenance').length
}

async function loadRoomDetail(id, { silent = false } = {}) {
  if (!id) return
  const requestId = ++roomDetailRequestId
  if (!silent) {
    if (Number(roomDetail.value?.id) !== Number(id)) roomDetail.value = null
    loading.value = true
  }
  try {
    const detail = await roomService.getRoomEquipmentDetail(id)
    if (requestId === roomDetailRequestId) {
      roomDetail.value = detail
      syncRoomStatsInPlace(id, detail)
    }
  } catch (err) {
    console.error('Failed to load room detail:', err)
    if (requestId === roomDetailRequestId && !silent) {
      toast.error(err.response?.data?.message || 'Không thể tải chi tiết thiết bị của phòng.')
    }
  } finally {
    if (requestId === roomDetailRequestId && !silent) loading.value = false
  }
}

function openLightbox(item) {
  const url = item.photo_url || item.photos?.[0]?.url
  if (!url) return
  lightboxImage.value = resolveMediaUrl(url)
  lightboxTitle.value = item.model_name || 'Hình ảnh thiết bị'
  lightboxSubtitle.value = `Mã Serial: ${item.serial_number || 'Chưa gắn mã'} • ID #${item.id}`
  lightboxOpen.value = true
}

// 1. Thao tác Điều chuyển
function openAssignModal(item) {
  selectedEquipmentForAssign.value = {
    ...item,
    room_id: selectedRoomId.value,
  }
  assignModalOpen.value = true
}

async function handleAssignSubmit(payload) {
  actionLoading.value = true
  try {
    await equipmentService.assignToRoom(payload)
    toast.success('Điều chuyển thiết bị thành công!')
    assignModalOpen.value = false
    await loadRoomDetail(selectedRoomId.value, { silent: true })
    void loadRooms({ silent: true })
  } catch (err) {
    toast.error(err.response?.data?.message || err.message || 'Điều chuyển thất bại.')
  } finally {
    actionLoading.value = false
  }
}

// 2. Thao tác Báo hỏng
function openReportBrokenModal(item) {
  selectedEquipmentForReport.value = item
  reportBrokenModalOpen.value = true
}

async function handleReportBrokenSubmit({ id, formData }) {
  actionLoading.value = true
  try {
    await equipmentService.reportBroken(id, formData)
    toast.success('Báo hỏng thiết bị thành công! Đã gửi thông báo bảo trì.')
    reportBrokenModalOpen.value = false
    await loadRoomDetail(selectedRoomId.value, { silent: true })
    void loadRooms({ silent: true })
  } catch (err) {
    toast.error(err.response?.data?.message || err.message || 'Báo hỏng thất bại.')
  } finally {
    actionLoading.value = false
  }
}

// 3. Thao tác Thu hồi về kho
function confirmRetract(item) {
  equipmentToRetract.value = item
  retractDialogOpen.value = true
}

async function handleRetract() {
  if (!equipmentToRetract.value) return
  actionLoading.value = true
  try {
    await equipmentService.assignToRoom({
      equipment_id: equipmentToRetract.value.id,
      room_id: null,
      position_note: 'Thu hồi về kho từ phòng ' + (roomDetail.value?.room_number || ''),
    })
    toast.success(`Đã thu hồi thiết bị #${equipmentToRetract.value.id} về kho thành công!`)
    retractDialogOpen.value = false
    equipmentToRetract.value = null
    await loadRoomDetail(selectedRoomId.value, { silent: true })
    void loadRooms({ silent: true })
  } catch (err) {
    toast.error(err.response?.data?.message || err.message || 'Thu hồi thất bại.')
  } finally {
    actionLoading.value = false
  }
}

// 4. Thao tác Gán thiết bị từ kho vào phòng
async function openWarehouseModal() {
  warehouseModalOpen.value = true
  warehouseLoading.value = true
  try {
    const res = await equipmentService.getEquipments({ status: 'in_stock', limit: 100 })
    warehouseEquipments.value = res.data || res || []
  } catch (err) {
    console.error('Failed to load in-stock equipments:', err)
    toast.error(err.response?.data?.message || 'Không thể tải danh sách thiết bị trong kho.')
  } finally {
    warehouseLoading.value = false
  }
}

const suggestedPositions = computed(() => {
  const list = []
  const living = parseInt(roomDetail.value?.living_room_count, 10) || 0
  const bedroom = parseInt(roomDetail.value?.bedroom_count, 10) || 1

  if (living > 0) {
    list.push('Phòng khách')
  }
  if (bedroom === 1) {
    list.push('Phòng ngủ')
  } else {
    for (let i = 1; i <= bedroom; i++) {
      list.push(`Phòng ngủ ${i}`)
    }
  }
  list.push('Bàn làm việc')
  list.push('Lối vào / Ban công')
  return list
})

async function handleAssignFromWarehouse({ equipment_id, position_note }) {
  if (!equipment_id || !selectedRoomId.value) return
  actionLoading.value = true
  try {
    await equipmentService.assignToRoom({
      equipment_id,
      room_id: selectedRoomId.value,
      position_note,
    })
    toast.success('Đã gán thiết bị từ kho vào phòng thành công!')
    warehouseModalOpen.value = false
    await loadRoomDetail(selectedRoomId.value, { silent: true })
    void loadRooms({ silent: true })
  } catch (err) {
    toast.error(err.response?.data?.message || err.message || 'Gán thiết bị thất bại.')
  } finally {
    actionLoading.value = false
  }
}

// 5. Thao tác Tạo mới thiết bị gắn trực tiếp vào phòng
async function handleCreateSubmit({ data }) {
  actionLoading.value = true
  try {
    await equipmentService.createEquipment({
      ...data,
      room_id: selectedRoomId.value,
    })
    toast.success('Đã tạo thiết bị mới và gán vào phòng thành công!')
    createModalOpen.value = false
    await loadRoomDetail(selectedRoomId.value, { silent: true })
    void loadRooms({ silent: true })
  } catch (err) {
    toast.error(err.response?.data?.message || err.message || 'Thêm thiết bị thất bại.')
  } finally {
    actionLoading.value = false
  }
}
</script>

<template>
  <DefaultLayout>
    <div class="p-4 md:p-6 space-y-6 max-w-7xl mx-auto">
      <!-- 1. Page Header -->
      <AppPageHeader
        title="Đối soát Định mức Thiết bị Phòng"
        subtitle="Kiểm tra mức độ đáp ứng định mức trang thiết bị tiêu chuẩn cho từng phòng khách sạn"
        icon="mdi-clipboard-check-outline"
        :breadcrumbs="[
          { title: 'Trang chủ', to: '/dashboard' },
          { title: 'Quản lý phòng & tầng', to: '/rooms' },
          { title: 'Đối soát định mức' },
        ]"
      >
        <template #actions>
          <div class="flex items-center gap-2 flex-wrap">
            <router-link to="/equipments/map-view">
              <AppButton variant="secondary">
                <v-icon start icon="mdi-floor-plan" size="18" />
                Sơ đồ Map View
              </AppButton>
            </router-link>
            <AppButton
              v-if="$can('assign', 'Equipment')"
              variant="primary"
              @click="openWarehouseModal"
            >
              <v-icon start icon="mdi-package-down" size="18" />
              Gán thiết bị từ kho
            </AppButton>
            <AppButton
              v-if="$can('create', 'Equipment')"
              variant="secondary"
              @click="createModalOpen = true"
            >
              <v-icon start icon="mdi-plus" size="18" />
              Tạo thiết bị mới
            </AppButton>
          </div>
        </template>
      </AppPageHeader>

      <!-- 2. Overview Stats Badges Cards (Child Component) -->
      <RoomAuditStats :stats="auditStats" />

      <!-- 3. Modern Filter & Navigation Control Card (Child Component) -->
      <RoomAuditFilterBar
        :filters="filters"
        :total-rooms="rooms.length"
        :filtered-rooms-count="filteredRooms.length"
        :existing-blocks="existingBlocks"
        :block-counts="blockCounts"
        :floor-options="floorOptions"
        :compliance-options="complianceOptions"
        :room-type-options="roomTypeOptions"
        :has-active-filters="hasActiveFilters"
        @select-block="selectBlock"
        @reset-filters="resetFilters"
      />

      <!-- Mobile View Mode Segmented Tab (Chỉ hiển thị trên Mobile / Tablet < lg) -->
      <div class="lg:hidden flex items-center p-1 bg-slate-200/70 rounded-xl text-xs font-bold shadow-2xs">
        <button
          type="button"
          class="flex-1 py-2 px-3 rounded-lg flex items-center justify-center gap-1.5 transition-all cursor-pointer"
          :class="mobileActiveTab === 'detail' ? 'bg-white text-primary shadow-xs font-black' : 'text-slate-600 hover:text-slate-900'"
          @click="mobileActiveTab = 'detail'"
        >
          <v-icon icon="mdi-clipboard-text-outline" size="16" />
          <span>Chi tiết đối soát {{ roomDetail?.room_number ? `(P.${roomDetail.room_number})` : '' }}</span>
        </button>

        <button
          type="button"
          class="flex-1 py-2 px-3 rounded-lg flex items-center justify-center gap-1.5 transition-all cursor-pointer"
          :class="mobileActiveTab === 'list' ? 'bg-white text-primary shadow-xs font-black' : 'text-slate-600 hover:text-slate-900'"
          @click="mobileActiveTab = 'list'"
        >
          <v-icon icon="mdi-door" size="16" />
          <span>Danh sách phòng ({{ filteredRooms.length }})</span>
        </button>
      </div>

      <!-- 4. Main Content: Left Room Sidebar + Right Audit Detail -->
      <div class="grid grid-cols-1 lg:grid-cols-4 gap-6 items-start">
        <!-- Left: Room Selector Sidebar (Child Component) -->
        <div
          class="lg:col-span-1"
          :class="mobileActiveTab === 'list' ? 'block' : 'hidden lg:block'"
        >
          <RoomAuditSidebarList
            :rooms="filteredRooms"
            :total-rooms="rooms.length"
            :selected-room-id="selectedRoomId"
            @select-room="(id) => { selectedRoomId = id; loadRoomDetail(id); mobileActiveTab = 'detail' }"
            @reset-filters="resetFilters"
          />
        </div>

        <!-- Right: Detailed Audit Card (Child Component) -->
        <div
          class="lg:col-span-3"
          :class="mobileActiveTab === 'detail' ? 'block' : 'hidden lg:block'"
        >
          <RoomAuditDetailCard
            :room-detail="roomDetail"
            :loading="loading"
            @open-edit-room="openEditRoomModal"
            @open-warehouse-modal="openWarehouseModal"
            @open-assign-modal="openAssignModal"
            @open-report-broken-modal="openReportBrokenModal"
            @confirm-retract="confirmRetract"
            @open-lightbox="openLightbox"
          />
        </div>
      </div>
    </div>

    <!-- REUSED MODAL 1: Điều chuyển phòng -->
    <AssignRoomModal
      v-if="assignModalOpen"
      v-model="assignModalOpen"
      :equipment="selectedEquipmentForAssign"
      :loading="actionLoading"
      @submit="handleAssignSubmit"
    />

    <!-- REUSED MODAL 2: Báo hỏng -->
    <ReportBrokenModal
      v-if="reportBrokenModalOpen"
      v-model="reportBrokenModalOpen"
      :equipment="selectedEquipmentForReport"
      :loading="actionLoading"
      @submit="handleReportBrokenSubmit"
    />

    <!-- REUSED MODAL 3: Tạo thiết bị mới cho phòng -->
    <EquipmentFormModal
      v-if="createModalOpen"
      v-model="createModalOpen"
      :loading="actionLoading"
      @submit="handleCreateSubmit"
    />

    <!-- REUSED MODAL 4: Xác nhận thu hồi về kho -->
    <AppConfirmDialog
      v-model="retractDialogOpen"
      title="Thu hồi thiết bị về kho"
      :message="`Bạn có chắc chắn muốn thu hồi thiết bị #${equipmentToRetract?.id} (${equipmentToRetract?.serial_number || equipmentToRetract?.model_name}) về kho không? Trạng thái sẽ chuyển thành 'Trong kho' (in_stock).`"
      confirm-text="Xác nhận thu hồi"
      confirm-color="warning"
      :loading="actionLoading"
      @confirm="handleRetract"
    />

    <!-- MODAL 5: Gán thiết bị từ kho vào phòng hiện tại (Child Component) -->
    <RoomAssignWarehouseModal
      v-model="warehouseModalOpen"
      :room-number="roomDetail?.room_number || selectedRoomId || ''"
      :room-id="selectedRoomId"
      :warehouse-equipments="warehouseEquipments"
      :warehouse-loading="warehouseLoading"
      :action-loading="actionLoading"
      :suggested-positions="suggestedPositions"
      @submit="handleAssignFromWarehouse"
    />

    <!-- Image Preview Lightbox Dialog -->
    <v-dialog v-model="lightboxOpen" max-width="700">
      <div class="bg-slate-900 rounded-2xl overflow-hidden p-4 relative">
        <button
          type="button"
          class="absolute top-3 right-3 z-10 w-8 h-8 rounded-full bg-white/20 hover:bg-white/40 text-white flex items-center justify-center transition-colors cursor-pointer"
          @click="lightboxOpen = false"
        >
          <v-icon icon="mdi-close" size="18" />
        </button>
        <div class="text-center mb-3 pr-8">
          <p class="text-sm font-bold text-white">{{ lightboxTitle }}</p>
          <p class="text-xs text-slate-400 font-mono mt-0.5">{{ lightboxSubtitle }}</p>
        </div>
        <div class="flex items-center justify-center max-h-[70vh]">
          <img :src="lightboxImage" alt="Preview" class="max-w-full max-h-[65vh] object-contain rounded-lg" />
        </div>
      </div>
    </v-dialog>

    <!-- Modal Chỉnh sửa thông tin & cơ cấu phòng -->
    <RoomFormModal
      v-if="editRoomModalOpen"
      v-model="editRoomModalOpen"
      :room="roomDetail ? { ...roomDetail, ...(roomDetail.room || {}) } : null"
      :room-types="roomTypesList"
      :existing-blocks="existingBlocks"
      @saved="handleRoomSaved"
    />
  </DefaultLayout>
</template>
