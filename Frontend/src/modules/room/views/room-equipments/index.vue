<script setup>
import { ref, reactive, computed, onMounted, watch, defineAsyncComponent } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { roomService } from '../../services/roomService'
import { equipmentService } from '@/modules/equipment/services/equipmentService'
import { useRealtimeStore } from '@/core/stores/realtimeStore'
import { toast } from '@/core/helpers/toast'
import { resolveMediaUrl } from '@/core/helpers/formatters'
import DefaultLayout from '@/core/layouts/DefaultLayout.vue'
import AppPageHeader from '@/core/components/AppPageHeader.vue'
import AppButton from '@/core/components/AppButton.vue'
import AppStatusBadge from '@/core/components/AppStatusBadge.vue'
import AppModal from '@/core/components/AppModal.vue'
import AppConfirmDialog from '@/core/components/AppConfirmDialog.vue'

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
const hotelBlockOrder = ['Block A', 'Block B', 'Block C', 'Block D', 'Condos 1', 'Condos 2', 'Penthouse-spa+Villa']

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
const selectedWarehouseEquipmentId = ref(null)
const warehousePositionNote = ref('')

// Sửa nhanh cơ cấu phòng
const editRoomModalOpen = ref(false)

onMounted(async () => {
  await Promise.all([
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

// Lightbox state
const lightboxOpen = ref(false)
const lightboxImage = ref('')
const lightboxTitle = ref('')
const lightboxSubtitle = ref('')

function openLightbox(item) {
  const url = item.photo_url || item.photos?.[0]?.url
  if (!url) return
  lightboxImage.value = resolveMediaUrl(url)
  lightboxTitle.value = item.model_name || 'Hình ảnh thiết bị'
  lightboxSubtitle.value = `Mã Serial: ${item.serial_number || 'Chưa gắn mã'} • ID #${item.id}`
  lightboxOpen.value = true
}

function getCategoryIcon(code) {
  const map = {
    tv: 'mdi-television',
    remote: 'mdi-remote',
    box: 'mdi-set-top-box',
    wifi: 'mdi-wifi',
    phone: 'mdi-phone-classic',
    ac: 'mdi-air-conditioner',
    air_conditioner: 'mdi-air-conditioner',
    refrigerator: 'mdi-fridge',
    kettle: 'mdi-kettle-outline',
    safe: 'mdi-safe',
    hairdryer: 'mdi-hair-dryer',
  }
  return map[code?.toLowerCase()] || 'mdi-devices'
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
  selectedWarehouseEquipmentId.value = null
  warehousePositionNote.value = ''
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

async function handleAssignFromWarehouse() {
  if (!selectedWarehouseEquipmentId.value || !selectedRoomId.value) return
  actionLoading.value = true
  try {
    await equipmentService.assignToRoom({
      equipment_id: selectedWarehouseEquipmentId.value,
      room_id: selectedRoomId.value,
      position_note: warehousePositionNote.value || 'Gán trực tiếp vào phòng',
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

      <!-- 2. Overview Stats Badges Cards (Đồng bộ với các trang khác) -->
      <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <!-- Tổng phòng -->
        <div class="bg-white border border-slate-200/80 rounded-2xl p-4 flex items-center gap-3.5 shadow-xs">
          <div class="w-11 h-11 rounded-xl bg-slate-100 flex items-center justify-center text-slate-700">
            <v-icon icon="mdi-door" size="24" />
          </div>
          <div>
            <div class="text-xs font-bold text-slate-500 uppercase tracking-wider">Tổng phòng đối soát</div>
            <div class="text-2xl font-black text-slate-800 mt-0.5">{{ auditStats.total }}</div>
          </div>
        </div>

        <!-- Đạt chuẩn định mức -->
        <div class="bg-white border border-emerald-200/80 rounded-2xl p-4 flex items-center gap-3.5 shadow-xs">
          <div class="w-11 h-11 rounded-xl bg-emerald-50 flex items-center justify-center text-emerald-600">
            <v-icon icon="mdi-check-decagram" size="24" />
          </div>
          <div>
            <div class="text-xs font-bold text-emerald-600 uppercase tracking-wider">Số phòng đủ chuẩn</div>
            <div class="text-2xl font-black text-emerald-700 mt-0.5">
              {{ auditStats.compliant }} <span class="text-xs font-normal text-slate-400">/ {{ auditStats.total }} phòng</span>
            </div>
          </div>
        </div>

        <!-- Chưa đạt định mức -->
        <div class="bg-white border border-rose-200/80 rounded-2xl p-4 flex items-center gap-3.5 shadow-xs">
          <div class="w-11 h-11 rounded-xl bg-rose-50 flex items-center justify-center text-rose-600">
            <v-icon icon="mdi-alert-decagram" size="24" />
          </div>
          <div>
            <div class="text-xs font-bold text-rose-600 uppercase tracking-wider">Phòng thiếu thiết bị</div>
            <div class="text-2xl font-black text-rose-700 mt-0.5">
              {{ auditStats.missing }} <span class="text-xs font-normal text-slate-400">phòng</span>
            </div>
          </div>
        </div>

        <!-- Tỷ lệ tuân thủ -->
        <div class="bg-white border border-blue-200/80 rounded-2xl p-4 flex items-center gap-3.5 shadow-xs">
          <div class="w-11 h-11 rounded-xl bg-blue-50 flex items-center justify-center text-blue-600">
            <v-icon icon="mdi-percent-outline" size="24" />
          </div>
          <div>
            <div class="text-xs font-bold text-blue-600 uppercase tracking-wider">Tỷ lệ phủ định mức</div>
            <div class="text-2xl font-black text-blue-700 mt-0.5">{{ auditStats.rate }}%</div>
          </div>
        </div>
      </div>

      <!-- 3. Modern Filter & Navigation Control Card (Chuẩn hóa đồng bộ hệ thống) -->
      <div class="bg-white border border-slate-200/80 rounded-2xl shadow-xs overflow-hidden">
        <!-- Top: Block Quick-Filter Pills Bar -->
        <div class="px-4 pt-3.5 pb-3 border-b border-slate-100 bg-slate-50/60">
          <div class="flex items-center justify-between gap-2 mb-2">
            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider flex items-center gap-1.5">
              <v-icon icon="mdi-domain" size="15" color="primary" />
              <span>Phân khu & Tòa nhà (Block):</span>
            </span>
            <button
              v-if="filters.block"
              class="text-xs text-primary font-semibold hover:underline flex items-center gap-1 cursor-pointer"
              @click="selectBlock('')"
            >
              <v-icon icon="mdi-eye-outline" size="14" />
              <span>Hiển thị tất cả Block</span>
            </button>
          </div>

          <!-- Horizontal Block Pills Bar -->
          <div class="flex items-center gap-2 overflow-x-auto pb-1 scrollbar-thin">
            <button
              type="button"
              class="px-3.5 py-1.5 rounded-xl text-xs font-semibold whitespace-nowrap transition-all duration-150 flex items-center gap-1.5 cursor-pointer shrink-0"
              :class="!filters.block ? 'bg-primary text-white shadow-xs' : 'bg-white text-slate-700 border border-slate-200 hover:border-slate-300 hover:bg-slate-50'"
              @click="selectBlock('')"
            >
              <span>Tất cả vị trí</span>
              <span class="text-[11px] px-1.5 py-0.2 rounded-full font-bold" :class="!filters.block ? 'bg-white/25 text-white' : 'bg-slate-100 text-slate-600'">
                {{ rooms.length }}
              </span>
            </button>
            <button
              v-for="b in existingBlocks"
              :key="b"
              type="button"
              class="px-3.5 py-1.5 rounded-xl text-xs font-semibold whitespace-nowrap transition-all duration-150 flex items-center gap-1.5 cursor-pointer shrink-0"
              :class="filters.block === b ? 'bg-primary text-white shadow-xs' : 'bg-white text-slate-700 border border-slate-200 hover:border-slate-300 hover:bg-slate-50'"
              @click="selectBlock(b)"
            >
              <v-icon icon="mdi-office-building-outline" size="13" />
              <span>{{ b }}</span>
              <span class="text-[11px] px-1.5 py-0.2 rounded-full font-bold" :class="filters.block === b ? 'bg-white/25 text-white' : 'bg-slate-100 text-slate-600'">
                {{ blockCounts[b] || 0 }}
              </span>
            </button>
          </div>
        </div>

        <!-- Bottom: Search & Detailed Filters Controls -->
        <div class="p-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 items-center">
          <!-- 1. Search Room -->
          <div class="lg:col-span-2">
            <v-text-field
              v-model="filters.search"
              placeholder="Tìm nhanh theo số phòng, tên phòng..."
              density="compact"
              variant="outlined"
              prepend-inner-icon="mdi-magnify"
              hide-details
              clearable
            />
          </div>

          <!-- 2. Floor Select -->
          <div>
            <v-select
              v-model="filters.floor"
              :items="floorOptions"
              item-value="value"
              item-title="title"
              placeholder="Tất cả Tầng"
              density="compact"
              variant="outlined"
              prepend-inner-icon="mdi-stairs"
              hide-details
              clearable
            />
          </div>

          <!-- 3. Compliance Status Select -->
          <div>
            <v-select
              v-model="filters.status"
              :items="complianceOptions"
              item-value="value"
              item-title="title"
              placeholder="Tất cả định mức"
              density="compact"
              variant="outlined"
              prepend-inner-icon="mdi-check-decagram-outline"
              hide-details
              clearable
            />
          </div>

          <!-- 4. Room Type Select -->
          <div>
            <v-select
              v-model="filters.room_type_id"
              :items="roomTypeOptions"
              item-value="value"
              item-title="title"
              placeholder="Tất cả loại phòng"
              density="compact"
              variant="outlined"
              prepend-inner-icon="mdi-home-outline"
              hide-details
              clearable
            />
          </div>
        </div>

        <!-- Filter Count & Reset Bar -->
        <div v-if="hasActiveFilters" class="px-4 py-2.5 bg-blue-50/40 border-t border-slate-100 flex items-center justify-between text-xs">
          <span class="text-slate-600">
            Đang lọc: Tìm thấy <strong>{{ filteredRooms.length }}</strong> trên tổng số <strong>{{ rooms.length }}</strong> phòng
          </span>
          <button
            type="button"
            class="text-primary font-bold hover:underline flex items-center gap-1 cursor-pointer"
            @click="resetFilters"
          >
            <v-icon icon="mdi-filter-remove-outline" size="14" />
            <span>Xóa bộ lọc</span>
          </button>
        </div>
      </div>

      <!-- 4. Main Content: Left Room Sidebar + Right Audit Detail -->
      <div class="grid grid-cols-1 lg:grid-cols-4 gap-6 items-start">
        <!-- Left: Room Selector Sidebar -->
        <div class="bg-white rounded-2xl p-4 sm:p-5 border border-slate-200/80 shadow-xs space-y-3.5 sticky top-20">
          <div class="flex items-center justify-between pb-2 border-b border-slate-100">
            <div>
              <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider flex items-center gap-1.5">
                <v-icon icon="mdi-format-list-bulleted" size="16" class="text-primary" />
                <span>Danh sách phòng</span>
              </h3>
              <p class="text-[11px] text-slate-400 mt-0.5">
                {{ filteredRooms.length }} phòng hiển thị
              </p>
            </div>
            <v-chip size="x-small" color="primary" variant="tonal" class="font-bold font-mono">
              {{ filteredRooms.length }}/{{ rooms.length }}
            </v-chip>
          </div>

          <!-- Room quick select dropdown for mobile / fast jump -->
          <div>
            <v-select
              v-model="selectedRoomId"
              :items="filteredRooms.map(r => ({ value: r.id, title: `Phòng ${r.room_number} (${r.block || 'Khu chính'})` }))"
              item-title="title"
              item-value="value"
              density="compact"
              variant="outlined"
              hide-details
              prepend-inner-icon="mdi-door"
              placeholder="Chọn nhanh phòng..."
              @update:model-value="loadRoomDetail"
            />
          </div>

          <!-- Vertical Room Cards List -->
          <div class="space-y-1.5 max-h-[600px] overflow-y-auto pr-1 scrollbar-thin">
            <div v-if="filteredRooms.length === 0" class="py-8 text-center text-slate-400 text-xs">
              <v-icon icon="mdi-filter-off-outline" size="32" class="text-slate-300 mb-1.5 block mx-auto" />
              Không tìm thấy phòng phù hợp.
              <button class="text-primary block mx-auto mt-2 font-bold hover:underline" @click="resetFilters">
                Đặt lại bộ lọc
              </button>
            </div>

            <button
              v-for="room in filteredRooms"
              :key="room.id"
              type="button"
              class="w-full text-left p-3 rounded-xl text-xs transition-all duration-150 flex items-center justify-between border cursor-pointer group"
              :class="selectedRoomId === room.id
                ? 'bg-blue-50/90 text-blue-900 border-primary shadow-xs ring-2 ring-primary/20 font-bold'
                : 'bg-white hover:bg-slate-50 text-slate-700 border-slate-200/90 hover:border-slate-300'"
              @click="selectedRoomId = room.id; loadRoomDetail(room.id)"
            >
              <div class="min-w-0 pr-2">
                <div class="flex items-center gap-1.5">
                  <v-icon icon="mdi-door" size="15" :class="selectedRoomId === room.id ? 'text-primary' : 'text-slate-400'" />
                  <span class="text-sm font-black truncate">Phòng {{ room.room_number }}</span>
                </div>
                <div class="text-[11px] text-slate-500 mt-0.5 truncate">
                  {{ room.block || 'Block A' }} • Tầng {{ room.floor ?? 1 }}
                </div>
              </div>

              <!-- Badge right indicator -->
              <div class="shrink-0 text-right">
                <span
                  v-if="room.badge_color === 'green'"
                  class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-200"
                >
                  ✓ Đạt chuẩn
                </span>
                <span
                  v-else-if="room.stats?.missing_count > 0"
                  class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-100 text-rose-800 border border-rose-200"
                >
                  Thiếu {{ room.stats.missing_count }}
                </span>
                <span
                  v-else-if="room.stats?.broken_count > 0"
                  class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-100 text-rose-800 border border-rose-200"
                >
                  {{ room.stats.broken_count }} hỏng
                </span>
                <span
                  v-else-if="room.stats?.maintenance_count > 0"
                  class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800 border border-amber-200"
                >
                  Bảo trì
                </span>
                <span
                  v-else
                  class="w-2.5 h-2.5 rounded-full inline-block"
                  :class="room.badge_color === 'green' ? 'bg-emerald-500' : (room.badge_color === 'red' ? 'bg-rose-500' : 'bg-amber-500')"
                />
              </div>
            </button>
          </div>
        </div>

        <!-- Right: Detailed Audit Card -->
        <div class="lg:col-span-3 space-y-6">
          <!-- Room Detail Skeleton Loader -->
          <div v-if="loading && !roomDetail" class="space-y-6">
            <div class="bg-white rounded-2xl p-6 border border-slate-200/80 shadow-xs flex flex-col sm:flex-row sm:items-center justify-between gap-4 animate-pulse">
              <div class="space-y-2">
                <div class="h-3 w-24 bg-slate-200 rounded"></div>
                <div class="h-7 w-48 bg-slate-200 rounded-lg"></div>
                <div class="h-3.5 w-32 bg-slate-100 rounded"></div>
              </div>
              <div class="h-10 w-48 bg-slate-200 rounded-xl"></div>
            </div>
            <div class="bg-white rounded-2xl p-6 border border-slate-200/80 shadow-xs space-y-4 animate-pulse">
              <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                <div class="h-5 w-48 bg-slate-200 rounded"></div>
                <div class="h-8 w-32 bg-slate-100 rounded-lg"></div>
              </div>
              <div class="space-y-3">
                <div v-for="n in 3" :key="n" class="h-16 bg-slate-50 border border-slate-100 rounded-xl"></div>
              </div>
            </div>
          </div>

          <div v-else-if="roomDetail" class="space-y-6">
            <div v-if="loading" class="flex items-center gap-2 rounded-xl border border-blue-200 bg-blue-50 px-3.5 py-2 text-xs text-blue-800">
              <v-progress-circular indeterminate color="primary" size="16" />
              <span>Đang đồng bộ thông tin phòng...</span>
            </div>

            <!-- Room Header & Compliance Badge -->
            <div class="bg-white rounded-2xl p-4 sm:p-6 border border-slate-200/80 shadow-xs flex flex-col sm:flex-row sm:items-center justify-between gap-4">
              <div>
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Đối soát phòng chi tiết</span>
                <h2 class="text-xl sm:text-2xl font-black text-slate-800 mt-0.5">
                  Phòng {{ roomDetail.room_number }}
                  <span v-if="roomDetail.room?.block" class="text-slate-400 text-lg font-normal">
                    ({{ roomDetail.room?.block }} • Tầng {{ roomDetail.room?.floor }})
                  </span>
                </h2>
                <p class="text-xs text-slate-500 mt-1">
                  Hạng phòng: <strong class="text-slate-800">{{ roomDetail.room_type || 'Tiêu chuẩn' }}</strong>
                </p>

                <!-- Space Composition & Wifi Badges -->
                <div class="flex items-center gap-1.5 mt-3 flex-wrap">
                  <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-blue-50 border border-blue-200 text-blue-700 text-xs font-bold">
                    <v-icon icon="mdi-bed" size="14" />
                    {{ roomDetail.bedroom_count ?? 1 }} Phòng ngủ
                    <template v-if="(roomDetail.living_room_count ?? 0) > 0">
                      • <v-icon icon="mdi-sofa" size="14" /> {{ roomDetail.living_room_count }} Phòng khách
                    </template>
                  </span>
                  <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-slate-100 border border-slate-200 text-slate-700 text-xs font-semibold">
                    <v-icon icon="mdi-television" size="14" class="text-blue-600" />
                    Định mức TV, Box & Remote: {{ Math.max(1, (roomDetail.bedroom_count ?? 1) + (roomDetail.living_room_count ?? 0)) }} bộ
                  </span>
                  <span v-if="roomDetail.wifi_mode === 'shared'" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-purple-50 border border-purple-200 text-purple-700 text-xs font-semibold">
                    <v-icon icon="mdi-wifi-arrow-left-right" size="14" />
                    Dùng ké Wifi {{ roomDetail.shared_wifi_note || (roomDetail.shared_wifi_room ? `P.${roomDetail.shared_wifi_room.room_number}` : 'phòng bên cạnh') }}
                  </span>
                  <span v-else-if="roomDetail.wifi_mode === 'none'" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-slate-100 border border-slate-200 text-slate-500 text-xs">
                    <v-icon icon="mdi-wifi-off" size="14" /> Không Wifi
                  </span>
                  <span v-else class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-700 text-xs font-semibold">
                    <v-icon icon="mdi-wifi" size="14" /> Router riêng ({{ roomDetail.wifi_router_count || 1 }} bộ)
                  </span>
                </div>
              </div>

              <div class="flex items-center gap-2 flex-wrap self-start sm:self-auto">
                <button
                  v-if="$can('update', 'Room')"
                  type="button"
                  class="px-3.5 py-2 rounded-xl text-xs font-semibold border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 flex items-center gap-1.5 transition shadow-2xs cursor-pointer"
                  title="Chỉnh sửa số phòng ngủ, phòng khách hoặc chế độ Wifi"
                  @click="openEditRoomModal"
                >
                  <v-icon icon="mdi-pencil-outline" size="15" color="primary" />
                  <span>Sửa cơ cấu phòng</span>
                </button>

                <div
                  class="px-3.5 py-2 rounded-xl text-xs font-black border flex items-center gap-1.5"
                  :class="roomDetail.compliance_status === 'compliant' ? 'bg-emerald-50 text-emerald-800 border-emerald-300' : 'bg-rose-50 text-rose-800 border-rose-300'"
                >
                  <v-icon
                    :icon="roomDetail.compliance_status === 'compliant' ? 'mdi-check-decagram' : 'mdi-alert-decagram'"
                    size="18"
                  />
                  <span>
                    {{ roomDetail.compliance_status === 'compliant' ? 'Đạt chuẩn 100%' : 'Chưa đạt định mức' }}
                  </span>
                </div>
              </div>
            </div>

            <!-- Missing items banner -->
            <div v-if="roomDetail.missing_equipments && roomDetail.missing_equipments.length" class="bg-rose-50 border border-rose-200 rounded-2xl p-4 sm:p-5 text-xs text-rose-900 shadow-xs">
              <div class="flex items-center justify-between gap-2 mb-3">
                <h4 class="font-black uppercase tracking-wider flex items-center gap-2 text-rose-800">
                  <v-icon icon="mdi-alert-circle" size="20" color="error" />
                  <span>Cảnh báo thiết bị còn thiếu so với định mức:</span>
                </h4>
                <AppButton
                  v-if="$can('assign', 'Equipment')"
                  size="small"
                  variant="primary"
                  color="error"
                  density="compact"
                  @click="openWarehouseModal"
                >
                  <v-icon start icon="mdi-package-down" size="14" />
                  Bù thiết bị từ kho
                </AppButton>
              </div>

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                <div
                  v-for="(mis, i) in roomDetail.missing_equipments"
                  :key="i"
                  class="bg-white/80 border border-rose-200 rounded-xl p-2.5 flex items-center justify-between gap-2"
                >
                  <div>
                    <span class="font-bold text-slate-800 text-xs">{{ mis.category_name || mis.name }}</span>
                    <p class="text-[11px] text-slate-500 mt-0.5">
                      Quy chuẩn: <strong>{{ mis.standard_quantity || 1 }}</strong> • Hiện có: <strong>{{ mis.actual_quantity || 0 }}</strong>
                    </p>
                  </div>
                  <span class="px-2 py-0.5 rounded-full font-black text-xs bg-rose-100 text-rose-800 border border-rose-200">
                    Thiếu {{ mis.missing_count || 1 }}
                  </span>
                </div>
              </div>
            </div>

            <!-- Actual Equipment Table by Category -->
            <div class="bg-white rounded-2xl p-4 sm:p-6 border border-slate-200/80 shadow-xs">
              <div class="flex items-center justify-between mb-5 pb-3 border-b border-slate-100">
                <div class="flex items-center gap-2">
                  <div class="w-8 h-8 rounded-xl bg-primary/10 text-primary flex items-center justify-center">
                    <v-icon icon="mdi-devices" size="18" />
                  </div>
                  <div>
                    <h3 class="text-base font-bold text-slate-800">
                      Danh mục Thiết bị Thực tế trong phòng
                    </h3>
                    <p class="text-[11px] text-slate-400">
                      Toàn bộ trang thiết bị đang được lắp đặt và phân bổ trong phòng này
                    </p>
                  </div>
                </div>

                <AppButton
                  v-if="$can('assign', 'Equipment')"
                  size="small"
                  variant="secondary"
                  @click="openWarehouseModal"
                >
                  <v-icon start icon="mdi-plus" size="14" />
                  Gán thêm thiết bị
                </AppButton>
              </div>

              <div v-if="roomDetail.categories && roomDetail.categories.length" class="space-y-4">
                <div
                  v-for="cat in roomDetail.categories"
                  :key="cat.category_code || cat.category_name"
                  class="border border-slate-200 rounded-2xl p-4 bg-slate-50/40"
                >
                  <div class="flex items-center justify-between mb-3">
                    <h4 class="text-xs font-black text-slate-700 uppercase tracking-wider flex items-center gap-1.5">
                      <v-icon :icon="cat.category_icon || getCategoryIcon(cat.category_code)" size="16" class="text-primary" />
                      <span>{{ cat.category_name }}</span>
                    </h4>
                    <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-white border border-slate-200 text-slate-600">
                      {{ cat.items?.length || 0 }} thiết bị
                    </span>
                  </div>

                  <div class="space-y-2">
                    <div
                      v-for="item in cat.items"
                      :key="item.id"
                      class="flex flex-col sm:flex-row sm:items-center justify-between p-3 bg-white hover:bg-slate-50 transition rounded-xl text-xs gap-3 border border-slate-200/80 shadow-2xs"
                    >
                      <!-- Left: Thumbnail & Equipment Info -->
                      <div class="flex items-center gap-3 min-w-0 flex-1">
                        <div
                          class="relative w-12 h-12 rounded-lg overflow-hidden shrink-0 border border-slate-200/80 bg-slate-100 flex items-center justify-center shadow-2xs group cursor-pointer"
                          :title="(item.photo_url || item.photos?.[0]?.url) ? 'Nhấp để xem ảnh lớn' : (item.model_name || 'Thiết bị')"
                          @click="openLightbox(item)"
                        >
                          <img
                            v-if="item.photo_url || item.photos?.[0]?.url"
                            :src="resolveMediaUrl(item.photo_url || item.photos?.[0]?.url)"
                            :alt="item.model_name || 'Thiết bị'"
                            class="w-full h-full object-cover transition-transform duration-200 group-hover:scale-110"
                            loading="lazy"
                          />
                          <div v-else class="flex flex-col items-center justify-center text-slate-400">
                            <v-icon :icon="cat.category_icon || getCategoryIcon(cat.category_code)" size="22" />
                          </div>

                          <div
                            v-if="item.photo_url || item.photos?.[0]?.url"
                            class="absolute inset-0 bg-slate-900/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center text-white"
                          >
                            <v-icon icon="mdi-magnify-plus" size="16" />
                          </div>
                        </div>

                        <div class="min-w-0 flex-1">
                          <div class="flex items-center gap-1.5">
                            <router-link
                              :to="`/equipments/${item.id}`"
                              class="font-bold text-slate-800 text-sm hover:text-primary transition-colors truncate"
                            >
                              {{ item.model_name || 'Thiết bị' }}
                            </router-link>
                            <span v-if="item.photos?.length > 1" class="text-[10px] bg-slate-200/70 text-slate-600 px-1.5 py-0.2 rounded-full font-medium">
                              {{ item.photos.length }} ảnh
                            </span>
                          </div>
                          <p class="font-mono text-blue-600 font-semibold mt-0.5 truncate">
                            {{ item.serial_number || 'Chưa gắn Serial' }}
                          </p>
                          <p v-if="item.position_note" class="text-[11px] text-slate-400 mt-0.5 truncate">
                            Vị trí: {{ item.position_note }}
                          </p>
                        </div>
                      </div>

                      <!-- Right: Status & Actions Toolbar -->
                      <div class="flex items-center gap-2 self-end sm:self-center shrink-0">
                        <AppStatusBadge :status="item.status" />

                        <div class="flex items-center gap-1 pl-2 border-l border-slate-200">
                          <!-- Xem chi tiết -->
                          <button
                            class="p-1.5 rounded-lg text-slate-500 hover:text-primary hover:bg-slate-100 transition cursor-pointer"
                            title="Xem chi tiết thiết bị"
                            @click="router.push(`/equipments/${item.id}`)"
                          >
                            <v-icon icon="mdi-eye-outline" size="16" />
                          </button>

                          <!-- Điều chuyển phòng -->
                          <button
                            v-if="$can('assign', 'Equipment')"
                            class="p-1.5 rounded-lg text-slate-500 hover:text-blue-600 hover:bg-slate-100 transition cursor-pointer"
                            title="Điều chuyển / Chuyển phòng"
                            @click="openAssignModal(item)"
                          >
                            <v-icon icon="mdi-transfer" size="16" />
                          </button>

                          <!-- Báo hỏng -->
                          <button
                            v-if="$can('report_broken', 'Equipment')"
                            class="p-1.5 rounded-lg text-slate-500 hover:text-amber-600 hover:bg-slate-100 transition cursor-pointer"
                            title="Báo hỏng thiết bị"
                            @click="openReportBrokenModal(item)"
                          >
                            <v-icon icon="mdi-wrench-outline" size="16" />
                          </button>

                          <!-- Thu hồi về kho -->
                          <button
                            v-if="$can('assign', 'Equipment')"
                            class="p-1.5 rounded-lg text-slate-500 hover:text-rose-600 hover:bg-rose-50 transition cursor-pointer"
                            title="Thu hồi về kho"
                            @click="confirmRetract(item)"
                          >
                            <v-icon icon="mdi-package-down" size="16" />
                          </button>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <div v-else class="text-center py-12 text-slate-400 text-xs">
                <v-icon icon="mdi-door-open" size="44" class="text-slate-300 mb-2 block mx-auto" />
                Chưa có thiết bị nào được gán vào phòng này.
                <div class="mt-3">
                  <AppButton variant="secondary" size="small" @click="openWarehouseModal">
                    <v-icon start icon="mdi-plus" size="14" />
                    Gán thiết bị đầu tiên
                  </AppButton>
                </div>
              </div>
            </div>
          </div>
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

    <!-- MODAL 5: Gán thiết bị từ kho vào phòng hiện tại -->
    <AppModal
      v-model="warehouseModalOpen"
      title="Gán Thiết Bị từ Kho vào Phòng"
      :subtitle="`Phòng đích: Phòng ${roomDetail?.room_number || selectedRoomId}`"
      max-width="540"
    >
      <div v-if="warehouseLoading" class="py-8 text-center text-slate-400 text-xs">
        <v-progress-circular indeterminate color="primary" size="30" />
        <p class="mt-2">Đang tải danh sách thiết bị trong kho...</p>
      </div>

      <div v-else class="space-y-4">
        <div>
          <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
            Chọn thiết bị còn trong kho (in_stock) *
          </label>
          <v-select
            v-model="selectedWarehouseEquipmentId"
            :items="warehouseEquipments.map(e => ({
              value: e.id,
              title: `${e.serial_number || 'Chưa serial'} — ${e.model?.name || e.name} (${e.category?.name || 'Khác'})`,
            }))"
            item-title="title"
            item-value="value"
            placeholder="Tìm và chọn thiết bị trong kho..."
            density="compact"
            variant="outlined"
            hide-details
            prepend-inner-icon="mdi-package-variant-closed"
          />
          <p v-if="warehouseEquipments.length === 0" class="text-xs text-amber-600 mt-1">
            Kho hiện không còn thiết bị nào ở trạng thái sẵn sàng.
          </p>
        </div>

        <div>
          <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
            Ghi chú vị trí trong phòng
          </label>
          <v-text-field
            v-model="warehousePositionNote"
            placeholder="Ví dụ: Kệ tivi phòng ngủ, bàn làm việc..."
            density="compact"
            variant="outlined"
            hide-details
            prepend-inner-icon="mdi-map-marker-outline"
          />

          <!-- Gợi ý vị trí không gian con nhanh -->
          <div v-if="suggestedPositions.length" class="flex items-center gap-1.5 flex-wrap mt-2">
            <span class="text-[11px] text-slate-400 font-medium">Gợi ý nhanh:</span>
            <button
              v-for="pos in suggestedPositions"
              :key="pos"
              type="button"
              class="px-2 py-0.5 rounded-full text-[11px] font-medium transition cursor-pointer"
              :class="warehousePositionNote === pos ? 'bg-primary text-white font-bold' : 'bg-slate-100 hover:bg-slate-200 text-slate-700'"
              @click="warehousePositionNote = pos"
            >
              {{ pos }}
            </button>
          </div>
        </div>
      </div>

      <template #footer>
        <AppButton variant="secondary" :disabled="actionLoading" @click="warehouseModalOpen = false">
          Hủy bỏ
        </AppButton>
        <AppButton
          variant="primary"
          :loading="actionLoading"
          :disabled="!selectedWarehouseEquipmentId"
          @click="handleAssignFromWarehouse"
        >
          <v-icon start icon="mdi-check" />
          Gán vào phòng
        </AppButton>
      </template>
    </AppModal>

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
