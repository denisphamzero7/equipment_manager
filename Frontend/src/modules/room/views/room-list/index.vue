<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import DefaultLayout from '@/core/layouts/DefaultLayout.vue'
import AppPageHeader from '@/core/components/AppPageHeader.vue'
import AppDataTable from '@/core/components/AppDataTable.vue'
import AppButton from '@/core/components/AppButton.vue'
import AppConfirmDialog from '@/core/components/AppConfirmDialog.vue'
import AppExcelTransfer from '@/core/components/AppExcelTransfer.vue'
import RoomFormModal from '../../components/RoomFormModal.vue'
import RoomBatchModal from '../../components/RoomBatchModal.vue'
import ManageStructureModal from '../../components/ManageStructureModal.vue'
import RoomEquipmentSummary from '../../components/RoomEquipmentSummary.vue'
import { roomService } from '../../services/roomService'
import { RoomStatusOptions, RoomStatusMeta } from '@/core/enums/RoomStatus'
import { formatCurrency } from '@/core/helpers/formatters'
import { toast } from '@/core/helpers/toast'
import { useRealtimeStore } from '@/core/stores/realtimeStore'

const router = useRouter()
const realtimeStore = useRealtimeStore()

// View mode: 'cards' (Thẻ theo tầng) | 'table' (Dạng bảng)
const viewMode = ref('cards')

// Data states
const loading = ref(false)
const roomsLoadError = ref('')
const rooms = ref([])
const roomSummary = ref([])
const roomSummaryLoaded = ref(false)
const roomTypes = ref([])
const totalRooms = ref(0)
const selectedRoomIds = ref([])
let roomsRequestSequence = 0

// Modals
const formModalOpen = ref(false)
const selectedRoomForEdit = ref(null)

const batchModalOpen = ref(false)
const structureModalOpen = ref(false)

const deleteDialogOpen = ref(false)
const roomToDelete = ref(null)
const deleteLoading = ref(false)

// Bulk delete dialog
const bulkDeleteDialogOpen = ref(false)
const bulkDeleteLoading = ref(false)

// Filters
const filters = reactive({
  search: '',
  block: '',
  floor: null,
  room_type_id: null,
  status: '',
})

// Pagination for Table View
const pagination = reactive({
  currentPage: 1,
  perPage: 15,
  totalItems: 0,
  totalPages: 1,
})

const tableHeaders = [
  { key: 'room_number', title: 'Số phòng', width: '100px' },
  { key: 'name', title: 'Tên phòng' },
  { key: 'block', title: 'Khu vực / Block', width: '130px' },
  { key: 'floor', title: 'Tầng', width: '80px' },
  { key: 'room_type', title: 'Hạng phòng', width: '140px' },
  { key: 'composition', title: 'Cơ cấu & Wifi', width: '150px' },
  { key: 'equipments_count', title: 'Trang thiết bị', width: '130px' },
  { key: 'price', title: 'Giá niêm yết', width: '130px' },
  { key: 'status', title: 'Trạng thái', width: '140px' },
  { key: 'actions', title: 'Thao tác', align: 'end', width: '130px' },
]

// Extract distinct blocks and floors from rooms list with standard hotel ordering
const hotelBlockOrder = ['Block A', 'Block B', 'Block C', 'Block D', 'Condos 1', 'Condos 2', 'Penthouse-spa+Villa']

const existingBlocks = computed(() => {
  const set = new Set(roomSummary.value.map(r => r.block).filter(Boolean))
  // Merge standard blocks with any custom ones
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

// Count rooms in each block for the quick-filter pills
const blockCounts = computed(() => {
  const counts = { total: roomSummary.value.length }
  roomSummary.value.forEach(r => {
    const b = r.block || 'Khu vực chung'
    counts[b] = (counts[b] || 0) + 1
  })
  return counts
})

const existingFloors = computed(() => {
  const targetRooms = filters.block
    ? roomSummary.value.filter(r => r.block === filters.block)
    : roomSummary.value
  const set = new Set(targetRooms.map(r => r.floor).filter(f => f !== null && f !== undefined))
  return Array.from(set).sort((a, b) => a - b)
})

// Sắp xếp tầng: mặc định 'asc' (Tầng 1 -> 7)
const floorSortOrder = ref('asc')

const hasActiveFilters = computed(() => {
  return !!(filters.search || filters.block || (filters.floor !== null && filters.floor !== '') || filters.room_type_id || filters.status || floorSortOrder.value !== 'asc')
})

function selectBlock(b) {
  filters.block = b
  if (viewMode.value === 'table') {
    handleSearch()
  }
}

// Group rooms by Block and then Floor for Grid Cards view
const groupedRooms = computed(() => {
  const filtered = rooms.value.filter(room => {
    if (filters.search) {
      const q = filters.search.toLowerCase()
      const matchNum = String(room.room_number).includes(q)
      const matchName = room.name?.toLowerCase().includes(q)
      if (!matchNum && !matchName) return false
    }
    if (filters.block && room.block !== filters.block) return false
    if (filters.floor !== null && filters.floor !== '' && room.floor !== Number(filters.floor)) return false
    if (filters.room_type_id && room.room_type_id !== Number(filters.room_type_id)) return false
    if (filters.status && room.status !== filters.status) return false
    return true
  })

  // Map into hierarchical structure: Block -> Floor -> Rooms
  const blocksMap = {}

  filtered.forEach(room => {
    const blockKey = room.block || 'Khu vực chung'
    const floorKey = room.floor ?? 1

    if (!blocksMap[blockKey]) {
      blocksMap[blockKey] = {
        name: blockKey,
        floors: {},
      }
    }

    if (!blocksMap[blockKey].floors[floorKey]) {
      blocksMap[blockKey].floors[floorKey] = {
        floor: floorKey,
        rooms: [],
      }
    }

    blocksMap[blockKey].floors[floorKey].rooms.push(room)
  })

  // Sort blocks according to standard hotel layout, floors & rooms ascending
  return Object.values(blocksMap)
    .sort((b1, b2) => {
      const idxA = hotelBlockOrder.indexOf(b1.name)
      const idxB = hotelBlockOrder.indexOf(b2.name)
      if (idxA !== -1 && idxB !== -1) return idxA - idxB
      if (idxA !== -1) return -1
      if (idxB !== -1) return 1
      return b1.name.localeCompare(b2.name, 'vi', { numeric: true })
    })
    .map(b => {
      // Sort floors: mặc định 'asc' (Tầng 1 -> 7)
      const sortedFloors = Object.values(b.floors).sort((f1, f2) => {
        return floorSortOrder.value === 'desc' ? f2.floor - f1.floor : f1.floor - f2.floor
      })
      sortedFloors.forEach(f => {
        f.rooms.sort((r1, r2) => String(r1.room_number).localeCompare(String(r2.room_number), 'vi', { numeric: true }))
      })
      return {
        name: b.name,
        floors: sortedFloors,
        totalRooms: sortedFloors.reduce((sum, f) => sum + f.rooms.length, 0),
      }
    })
})

// Statistics counter
const stats = computed(() => {
  let ready = 0, occupied = 0, notReady = 0, maintenance = 0
  const summaryRooms = roomSummaryLoaded.value ? roomSummary.value : rooms.value
  summaryRooms.forEach(r => {
    if (r.status === 'ready' || r.status === 'available') ready++
    else if (r.status === 'occupied') occupied++
    else if (r.status === 'not_ready' || r.status === 'cleaning' || r.status === 'dirty') notReady++
    else if (r.status === 'maintenance') maintenance++
  })
  return { total: summaryRooms.length, ready, occupied, notReady, maintenance }
})

onMounted(async () => {
  await Promise.all([fetchRoomTypes(), fetchRooms()])
})

// Watch real-time changes
watch(
  () => realtimeStore.lastEvent,
  (ev) => {
    if (!ev) return
    if (ev.type && ev.type.startsWith('room_')) {
      const room = ev.room
      if (room?.id) {
        if (ev.type.endsWith('_deleted')) {
          updateRoomSummary(room, true)
        } else {
          updateRoomSummary(room)
        }
      }
      fetchRooms(false)
    } else if (ev.type === 'created' && ev.equipment?.room_id) {
      fetchRooms(false)
    } else if (ev.type === 'deleted' && ev.room_id) {
      fetchRooms(false)
    } else if (ev.type === 'assigned') {
      fetchRooms(false)
    }
  }
)

watch(viewMode, () => {
  pagination.currentPage = 1
  fetchRooms()
})

function handlePageChange(page) {
  pagination.currentPage = page
  fetchRooms()
}

async function fetchRoomTypes() {
  try {
    const types = await roomService.getRoomTypes()
    roomTypes.value = Array.isArray(types) ? types : []
  } catch (err) {
    console.error('Fetch room types error:', err)
    toast.error(err.response?.data?.message || 'Không thể tải danh sách loại phòng.')
  }
}

async function fetchRooms(showLoading = true) {
  const requestSequence = ++roomsRequestSequence
  loading.value = showLoading
  try {
    const params = {
      limit: viewMode.value === 'table' ? pagination.perPage : 500,
      page: viewMode.value === 'table' ? pagination.currentPage : 1,
      ...(viewMode.value === 'table' ? filters : {}),
    }
    const res = await roomService.getRooms(params)
    const items = Array.isArray(res) ? res : (res?.data || [])
    if (requestSequence !== roomsRequestSequence) return
    rooms.value = Array.isArray(items) ? items : []
    if (viewMode.value === 'cards') {
      roomSummary.value = [...rooms.value]
      roomSummaryLoaded.value = true
    }
    roomsLoadError.value = ''

    const total = res?.total ?? (Array.isArray(res) ? res.length : rooms.value.length)
    pagination.totalItems = total
    pagination.totalPages = res?.last_page || 1
    totalRooms.value = total
  } catch (err) {
    if (requestSequence !== roomsRequestSequence) return
    console.error('Fetch rooms error:', err)
    roomsLoadError.value = err.response?.data?.message || err.message || 'Không thể tải danh sách phòng.'
  } finally {
    if (requestSequence === roomsRequestSequence) loading.value = false
  }
}

let searchDebounceTimer = null
function handleSearchInput(val) {
  if (viewMode.value === 'table') {
    if (searchDebounceTimer) clearTimeout(searchDebounceTimer)
    if (!val) {
      pagination.currentPage = 1
      fetchRooms()
      return
    }
    searchDebounceTimer = setTimeout(() => {
      pagination.currentPage = 1
      fetchRooms()
    }, 350)
  }
}

function handleSearch() {
  if (viewMode.value === 'table') {
    pagination.currentPage = 1
    fetchRooms()
  }
}

function resetFilters() {
  filters.search = ''
  filters.block = ''
  filters.floor = null
  filters.room_type_id = null
  filters.status = ''
  floorSortOrder.value = 'asc'
  pagination.currentPage = 1
  if (viewMode.value === 'table') {
    fetchRooms()
  }
}

function openCreateModal(defaultBlock = null, defaultFloor = null) {
  selectedRoomForEdit.value = defaultBlock ? { block: defaultBlock, floor: defaultFloor } : null
  formModalOpen.value = true
}

function openEditModal(room) {
  selectedRoomForEdit.value = room
  formModalOpen.value = true
}

function handleRoomSaved(savedRoom) {
  if (!savedRoom?.id) {
    fetchRooms(false)
    return
  }

  updateRoomSummary(savedRoom)
  const roomIndex = rooms.value.findIndex(room => Number(room.id) === Number(savedRoom.id))
  if (roomIndex !== -1) {
    const updatedRoom = { ...rooms.value[roomIndex], ...savedRoom }
    if (viewMode.value === 'table' && !matchesTableFilters(updatedRoom)) {
      removeRoomsFromView([savedRoom.id])
      return
    }
    rooms.value.splice(roomIndex, 1, updatedRoom)
    return
  }

  if (viewMode.value === 'table' && !matchesTableFilters(savedRoom)) return
  pagination.totalItems += 1
  totalRooms.value += 1
  if (viewMode.value === 'cards' || pagination.currentPage === 1) {
    rooms.value.unshift(savedRoom)
  }
}

function matchesTableFilters(room) {
  if (filters.block && room.block !== filters.block) return false
  if (filters.floor !== null && filters.floor !== '' && Number(room.floor) !== Number(filters.floor)) return false
  if (filters.room_type_id && Number(room.room_type_id) !== Number(filters.room_type_id)) return false
  if (filters.status && room.status !== filters.status) return false
  if (filters.search) {
    const query = filters.search.toLowerCase()
    if (!String(room.room_number || '').toLowerCase().includes(query) &&
        !String(room.name || '').toLowerCase().includes(query)) return false
  }
  return true
}

function confirmDeleteRoom(room) {
  roomToDelete.value = room
  deleteDialogOpen.value = true
}

async function handleDeleteRoom() {
  if (!roomToDelete.value) return
  deleteLoading.value = true
  try {
    const roomId = roomToDelete.value.id
    await roomService.deleteRoom(roomId, true)
    toast.success(`Đã xóa phòng ${roomToDelete.value.room_number}. Thiết bị đã tự động thu hồi về kho an toàn.`)
    deleteDialogOpen.value = false
    roomToDelete.value = null
    removeRoomsFromView([roomId], true)
  } catch (err) {
    const msg = err.response?.data?.message || err.message || 'Lỗi khi xóa phòng.'
    toast.error(msg)
  } finally {
    deleteLoading.value = false
  }
}

function confirmBulkDelete() {
  if (!selectedRoomIds.value.length) {
    toast.error('Vui lòng chọn ít nhất một phòng để xóa.')
    return
  }
  bulkDeleteDialogOpen.value = true
}

async function handleBulkDelete() {
  bulkDeleteLoading.value = true
  try {
    const res = await roomService.bulkDeleteRooms(selectedRoomIds.value, true)
    toast.success(res.message || `Đã xóa ${selectedRoomIds.value.length} phòng thành công!`)
    removeRoomsFromView(selectedRoomIds.value, true)
    selectedRoomIds.value = []
    bulkDeleteDialogOpen.value = false
  } catch (err) {
    const msg = err.response?.data?.message || err.message || 'Lỗi khi xóa nhiều phòng.'
    toast.error(msg)
  } finally {
    bulkDeleteLoading.value = false
  }
}

function updateRoomSummary(room, remove = false) {
  if (!room?.id) return
  const index = roomSummary.value.findIndex(item => Number(item.id) === Number(room.id))
  if (remove) {
    if (index !== -1) roomSummary.value.splice(index, 1)
    return
  }
  if (index === -1) {
    roomSummary.value.unshift(room)
  } else {
    roomSummary.value.splice(index, 1, { ...roomSummary.value[index], ...room })
  }
}

function removeRoomsFromView(ids, removeFromSummary = false) {
  const idSet = new Set(ids.map(Number))
  const previousLength = rooms.value.length
  rooms.value = rooms.value.filter(room => !idSet.has(Number(room.id)))
  if (removeFromSummary) {
    roomSummary.value = roomSummary.value.filter(room => !idSet.has(Number(room.id)))
  }
  const removed = previousLength - rooms.value.length
  pagination.totalItems = Math.max(0, pagination.totalItems - removed)
  totalRooms.value = Math.max(0, totalRooms.value - removed)
  selectedRoomIds.value = selectedRoomIds.value.filter(id => !idSet.has(Number(id)))

  if (viewMode.value === 'table' && rooms.value.length === 0 && pagination.currentPage > 1) {
    pagination.currentPage -= 1
    fetchRooms()
  }
}

function goToRoomEquipments(roomId) {
  router.push({ path: '/rooms/equipments', query: { room_id: roomId } })
}

function getStatusBadge(status) {
  return RoomStatusMeta[status] || {
    label: status || 'Chưa rõ',
    badgeClass: 'bg-slate-100 text-slate-700 border-slate-200',
    icon: 'mdi-help-circle-outline',
  }
}
</script>

<template>
  <DefaultLayout>
    <div class="p-4 md:p-6 space-y-6 max-w-7xl mx-auto">
      <!-- Page Header -->
      <AppPageHeader
        title="Quản Lý Phòng, Tầng & Block"
        subtitle="Quản trị cơ cấu khách sạn, tạo nhanh hàng loạt phòng và đồng bộ vị trí thiết bị"
        icon="mdi-home-city-outline"
      >
        <template #actions>
          <div class="flex flex-wrap items-center gap-2">
            <AppExcelTransfer
              v-if="$can('import', 'Room') || $can('export', 'Room')"
              title="Phòng"
              description="Nhập danh sách phòng theo mẫu hoặc xuất dữ liệu để kiểm kê."
              :exporter="roomService.exportExcel"
              :importer="roomService.importExcel"
              :filters="filters"
              :allow-import="$can('import', 'Room')"
              :allow-export="$can('export', 'Room')"
              @imported="fetchRooms()"
            />
            <!-- Thêm phòng đơn -->
            <AppButton variant="primary" @click="openCreateModal()">
              <v-icon start icon="mdi-plus" />
              Thêm phòng
            </AppButton>

            <!-- Tạo nhanh hàng loạt -->
            <AppButton variant="secondary" class="bg-indigo-50 text-indigo-700 hover:bg-indigo-100 border-indigo-200" @click="batchModalOpen = true">
              <v-icon start icon="mdi-flash" color="indigo" />
              Tạo phòng nhanh
            </AppButton>

            <!-- Quản lý Block -->
            <AppButton variant="secondary" @click="structureModalOpen = true">
              <v-icon start icon="mdi-domain" />
              Quản lý Block
            </AppButton>

            <!-- Link Map View -->
            <router-link to="/equipments/map-view">
              <AppButton variant="secondary">
                <v-icon start icon="mdi-floor-plan" />
                Sơ đồ Map View
              </AppButton>
            </router-link>
          </div>
        </template>
      </AppPageHeader>

      <div
        v-if="roomsLoadError"
        role="alert"
        class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800"
      >
        <span>{{ roomsLoadError }}</span>
        <AppButton variant="secondary" :disabled="loading" @click="fetchRooms()">
          <v-icon start icon="mdi-refresh" />
          Thử tải lại
        </AppButton>
      </div>

      <!-- Stat Badges Cards -->
      <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
        <div class="bg-white border border-slate-200/80 rounded-xl p-3.5 flex items-center gap-3 shadow-xs">
          <div class="w-10 h-10 rounded-lg bg-slate-100 flex items-center justify-center text-slate-700">
            <v-icon icon="mdi-door" size="22" />
          </div>
          <div>
            <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Tổng phòng</div>
            <div v-if="loading" class="h-6 w-12 bg-slate-200 rounded animate-pulse my-0.5" />
            <div v-else class="text-xl font-bold text-slate-800">{{ stats.total }}</div>
          </div>
        </div>

        <div class="bg-white border border-emerald-100 rounded-xl p-3.5 flex items-center gap-3 shadow-xs">
          <div class="w-10 h-10 rounded-lg bg-emerald-50 flex items-center justify-center text-emerald-600">
            <v-icon icon="mdi-door-open" size="22" />
          </div>
          <div>
            <div class="text-xs font-semibold text-emerald-600 uppercase tracking-wider">Sẵn sàng</div>
            <div v-if="loading" class="h-6 w-12 bg-slate-200 rounded animate-pulse my-0.5" />
            <div v-else class="text-xl font-bold text-emerald-700">{{ stats.ready }}</div>
          </div>
        </div>

        <div class="bg-white border border-rose-100 rounded-xl p-3.5 flex items-center gap-3 shadow-xs">
          <div class="w-10 h-10 rounded-lg bg-rose-50 flex items-center justify-center text-rose-600">
            <v-icon icon="mdi-account-check" size="22" />
          </div>
          <div>
            <div class="text-xs font-semibold text-rose-600 uppercase tracking-wider">Đang có khách</div>
            <div v-if="loading" class="h-6 w-12 bg-slate-200 rounded animate-pulse my-0.5" />
            <div v-else class="text-xl font-bold text-rose-700">{{ stats.occupied }}</div>
          </div>
        </div>

        <div class="bg-white border border-amber-100 rounded-xl p-3.5 flex items-center gap-3 shadow-xs">
          <div class="w-10 h-10 rounded-lg bg-amber-50 flex items-center justify-center text-amber-600">
            <v-icon icon="mdi-broom" size="22" />
          </div>
          <div>
            <div class="text-xs font-semibold text-amber-600 uppercase tracking-wider">Chờ dọn</div>
            <div v-if="loading" class="h-6 w-12 bg-slate-200 rounded animate-pulse my-0.5" />
            <div v-else class="text-xl font-bold text-amber-700">{{ stats.notReady }}</div>
          </div>
        </div>

        <div class="bg-white border border-slate-200/80 rounded-xl p-3.5 flex items-center gap-3 shadow-xs">
          <div class="w-10 h-10 rounded-lg bg-slate-100 flex items-center justify-center text-slate-600">
            <v-icon icon="mdi-hammer-wrench" size="22" />
          </div>
          <div>
            <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Bảo trì</div>
            <div v-if="loading" class="h-6 w-12 bg-slate-200 rounded animate-pulse my-0.5" />
            <div v-else class="text-xl font-bold text-slate-700">{{ stats.maintenance }}</div>
          </div>
        </div>
      </div>

      <!-- Modern Filter & Navigation Control Card -->
      <div class="bg-white border border-slate-200/80 rounded-2xl shadow-xs overflow-hidden">
        <!-- Top Section: Block Quick-Filter Pills (Horizontal Scroll) -->
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
              <span>Hiển thị tất cả 7 Block</span>
            </button>
          </div>

          <!-- Horizontal Block Pills Bar -->
          <div class="flex items-center gap-2 overflow-x-auto pb-1 scrollbar-thin">
            <button
              class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all duration-150 shrink-0 flex items-center gap-2 border cursor-pointer"
              :class="!filters.block
                ? 'bg-primary text-white border-primary shadow-xs'
                : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-100 hover:border-slate-300'"
              @click="selectBlock('')"
            >
              <span>Tất cả Block</span>
              <span
                class="px-1.5 py-0.2 rounded-full text-[10px] font-extrabold"
                :class="!filters.block ? 'bg-white/25 text-white' : 'bg-slate-100 text-slate-600'"
              >
                {{ blockCounts.total || 0 }}
              </span>
            </button>

            <button
              v-for="b in existingBlocks"
              :key="b"
              class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all duration-150 shrink-0 flex items-center gap-2 border cursor-pointer"
              :class="filters.block === b
                ? 'bg-primary text-white border-primary shadow-xs'
                : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-100 hover:border-slate-300'"
              @click="selectBlock(b)"
            >
              <span>{{ b }}</span>
              <span
                class="px-1.5 py-0.2 rounded-full text-[10px] font-extrabold"
                :class="filters.block === b ? 'bg-white/25 text-white' : 'bg-slate-100 text-slate-600'"
              >
                {{ blockCounts[b] || 0 }}
              </span>
            </button>
          </div>
        </div>

        <!-- Bottom Section: Search, Filters & View Toggle -->
        <div class="p-4">
          <div class="flex flex-col xl:flex-row items-stretch xl:items-center justify-between gap-3.5">
            <!-- Search Input (Spacious) -->
            <div class="flex-1 min-w-[260px]">
              <v-text-field
                v-model="filters.search"
                placeholder="Tìm nhanh theo số phòng, tên phòng..."
                density="compact"
                variant="outlined"
                hide-details
                prepend-inner-icon="mdi-magnify"
                clearable
                bg-color="slate-50"
                @update:model-value="handleSearchInput"
              />
            </div>

            <!-- Filters Group (Fixed Comfortable Widths - No Wrapping) -->
            <div class="flex flex-wrap sm:flex-nowrap items-center gap-2.5">
              <!-- Filter Floor -->
              <div class="w-full sm:w-36 shrink-0">
                <v-select
                  v-model="filters.floor"
                  :items="[{ value: null, title: 'Tất cả Tầng' }, ...existingFloors.map(f => ({ value: f, title: `Tầng ${f}` }))]"
                  item-title="title"
                  item-value="value"
                  density="compact"
                  variant="outlined"
                  hide-details
                  prepend-inner-icon="mdi-stairs"
                  @update:model-value="handleSearch"
                />
              </div>

              <!-- Floor Sort Direction Toggle Button (Only in Cards view) -->
              <button
                v-if="viewMode === 'cards'"
                type="button"
                class="px-2.5 py-2 text-xs font-semibold rounded-lg border border-slate-200 text-slate-700 bg-white hover:bg-slate-50 transition flex items-center gap-1.5 shrink-0 cursor-pointer shadow-xs"
                :title="floorSortOrder === 'asc' ? 'Đang sắp xếp Tầng 1 → 7 (Bấm để đảo 7 → 1)' : 'Đang sắp xếp Tầng 7 → 1 (Bấm để đảo 1 → 7)'"
                @click="floorSortOrder = floorSortOrder === 'asc' ? 'desc' : 'asc'"
              >
                <v-icon :icon="floorSortOrder === 'asc' ? 'mdi-sort-numeric-ascending' : 'mdi-sort-numeric-descending'" size="16" color="primary" />
                <span>Tầng {{ floorSortOrder === 'asc' ? '1 → 7' : '7 → 1' }}</span>
              </button>

              <!-- Filter Status -->
              <div class="w-full sm:w-48 shrink-0">
                <v-select
                  v-model="filters.status"
                  :items="[{ value: '', label: 'Tất cả trạng thái' }, ...RoomStatusOptions]"
                  item-title="label"
                  item-value="value"
                  density="compact"
                  variant="outlined"
                  hide-details
                  prepend-inner-icon="mdi-shield-check-outline"
                  @update:model-value="handleSearch"
                />
              </div>

              <!-- Filter Room Type -->
              <div class="w-full sm:w-44 shrink-0">
                <v-select
                  v-model="filters.room_type_id"
                  :items="[{ id: null, name: 'Tất cả loại phòng' }, ...roomTypes]"
                  item-title="name"
                  item-value="id"
                  density="compact"
                  variant="outlined"
                  hide-details
                  prepend-inner-icon="mdi-bed-outline"
                  @update:model-value="handleSearch"
                />
              </div>

              <!-- Reset Button -->
              <button
                v-if="hasActiveFilters"
                class="px-3 py-2 text-xs font-semibold rounded-lg border border-slate-200 text-slate-600 bg-slate-50 hover:bg-slate-100 hover:text-slate-900 transition flex items-center gap-1 shrink-0 cursor-pointer"
                title="Xóa tất cả bộ lọc đang áp dụng"
                @click="resetFilters"
              >
                <v-icon icon="mdi-filter-off-outline" size="16" />
                <span>Đặt lại</span>
              </button>
            </div>

            <!-- View Switcher -->
            <div class="flex items-center gap-1 p-1 bg-slate-100 rounded-xl border border-slate-200/80 shrink-0 self-end xl:self-center">
              <button
                class="px-3 py-1.5 text-xs font-bold rounded-lg transition-all duration-150 flex items-center gap-1.5 cursor-pointer"
                :class="viewMode === 'cards' ? 'bg-white text-primary shadow-xs' : 'text-slate-600 hover:text-slate-900'"
                @click="viewMode = 'cards'"
              >
                <v-icon icon="mdi-view-grid-outline" size="16" />
                <span>Thẻ theo tầng</span>
              </button>
              <button
                class="px-3 py-1.5 text-xs font-bold rounded-lg transition-all duration-150 flex items-center gap-1.5 cursor-pointer"
                :class="viewMode === 'table' ? 'bg-white text-primary shadow-xs' : 'text-slate-600 hover:text-slate-900'"
                @click="viewMode = 'table'"
              >
                <v-icon icon="mdi-table" size="16" />
                <span>Dạng bảng</span>
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- VIEW 1: Dạng Thẻ theo Khu vực & Tầng (Cards View) -->
      <div v-if="viewMode === 'cards'" class="space-y-6">
        <!-- Loading Skeleton (Thẻ theo tầng đồng bộ phong cách với danh sách thiết bị) -->
        <div v-if="loading" class="space-y-6">
          <div v-for="blockIndex in 2" :key="blockIndex" class="space-y-4">
            <!-- Block Title Skeleton -->
            <div class="flex items-center justify-between pb-2 border-b border-slate-200 animate-pulse">
              <div class="flex items-center gap-2">
                <span class="w-2.5 h-6 rounded-full bg-slate-300 inline-block"></span>
                <div class="h-6 w-32 bg-slate-200 rounded-md"></div>
                <div class="h-5 w-16 bg-slate-100 rounded-full"></div>
              </div>
              <div class="h-8 w-36 bg-slate-100 rounded-lg"></div>
            </div>

            <!-- Floor Container Skeleton -->
            <div class="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-xs space-y-3">
              <!-- Floor Header Skeleton -->
              <div class="flex items-center justify-between border-b border-slate-100 pb-2.5 animate-pulse">
                <div class="flex items-center gap-2">
                  <div class="w-5 h-5 bg-slate-200 rounded"></div>
                  <div class="h-4 w-20 bg-slate-200 rounded"></div>
                  <div class="h-3 w-16 bg-slate-100 rounded"></div>
                </div>
                <div class="h-4 w-28 bg-slate-100 rounded"></div>
              </div>

              <!-- Rooms Grid Skeleton -->
              <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 2xl:grid-cols-5 gap-3.5">
                <div
                  v-for="cardIndex in 5"
                  :key="cardIndex"
                  class="bg-slate-50/70 border border-slate-200 rounded-xl p-3.5 space-y-3 animate-pulse relative"
                >
                  <!-- Top Row: Room number & status badge skeleton -->
                  <div class="flex items-center justify-between gap-1.5">
                    <div class="h-6 w-14 bg-slate-200 rounded-md"></div>
                    <div class="h-5 w-20 bg-slate-200 rounded-full"></div>
                  </div>
                  <!-- Room name skeleton -->
                  <div class="h-3 w-24 bg-slate-200/60 rounded"></div>
                  <!-- Middle: Type & Price skeleton -->
                  <div class="space-y-1.5 py-1">
                    <div class="flex justify-between items-center">
                      <div class="h-3 w-10 bg-slate-200/60 rounded"></div>
                      <div class="h-3 w-20 bg-slate-200 rounded"></div>
                    </div>
                    <div class="flex justify-between items-center">
                      <div class="h-3 w-8 bg-slate-200/60 rounded"></div>
                      <div class="h-3 w-16 bg-slate-200 rounded"></div>
                    </div>
                  </div>
                  <!-- Bottom: Equipment & actions skeleton -->
                  <div class="pt-2 border-t border-slate-200/60 flex items-center justify-between gap-1">
                    <div class="h-6 w-16 bg-slate-200 rounded-md"></div>
                    <div class="flex items-center gap-1">
                      <div class="w-6 h-6 bg-slate-200 rounded"></div>
                      <div class="w-6 h-6 bg-slate-200 rounded"></div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Empty State -->
        <div v-else-if="!roomsLoadError && groupedRooms.length === 0" class="py-16 text-center bg-white rounded-2xl border border-slate-200/80 p-8">
          <div class="w-16 h-16 rounded-full bg-slate-100 text-slate-400 flex items-center justify-center mx-auto mb-3">
            <v-icon icon="mdi-door-closed" size="32" />
          </div>
          <h3 class="text-base font-bold text-slate-700">Chưa tìm thấy phòng nào</h3>
          <p class="text-xs text-slate-500 mt-1 max-w-sm mx-auto">
            Không có phòng nào phù hợp với bộ lọc hiện tại, hoặc hệ thống chưa có dữ liệu phòng.
          </p>
          <div class="mt-4 flex items-center justify-center gap-2">
            <AppButton variant="secondary" @click="resetFilters">Đặt lại bộ lọc</AppButton>
            <AppButton variant="primary" @click="openCreateModal()">
              <v-icon start icon="mdi-plus" />
              Tạo phòng đầu tiên
            </AppButton>
          </div>
        </div>

        <!-- Blocks Container -->
        <div v-for="blockGroup in groupedRooms" :key="blockGroup.name" class="space-y-4">
          <!-- Block Title -->
          <div class="flex items-center justify-between pb-2 border-b border-slate-200">
            <div class="flex items-center gap-2">
              <span class="w-2.5 h-6 rounded-full bg-primary inline-block"></span>
              <h2 class="text-lg font-bold text-slate-800">{{ blockGroup.name }}</h2>
              <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-slate-100 text-slate-600">
                {{ blockGroup.totalRooms }} phòng
              </span>
            </div>
            <AppButton variant="secondary" size="small" @click="openCreateModal(blockGroup.name)">
              <v-icon start icon="mdi-plus" size="16" />
              Thêm phòng vào {{ blockGroup.name }}
            </AppButton>
          </div>

          <!-- Floors in Block -->
          <div v-for="floorGroup in blockGroup.floors" :key="floorGroup.floor" class="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-xs space-y-3">
            <!-- Floor Header -->
            <div class="flex items-center justify-between border-b border-slate-100 pb-2.5">
              <div class="flex items-center gap-2">
                <v-icon icon="mdi-stairs" color="primary" size="20" />
                <span class="font-bold text-slate-800 text-sm">Tầng {{ floorGroup.floor }}</span>
                <span class="text-xs text-slate-400">({{ floorGroup.rooms.length }} phòng)</span>
              </div>
              <button
                class="text-xs font-semibold text-primary hover:text-primary-dark transition flex items-center gap-1"
                @click="openCreateModal(blockGroup.name, floorGroup.floor)"
              >
                <v-icon icon="mdi-plus-circle-outline" size="16" />
                Thêm phòng tầng này
              </button>
            </div>

            <!-- Rooms Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 2xl:grid-cols-5 gap-3.5">
              <div
                v-for="room in floorGroup.rooms"
                :key="room.id"
                class="group bg-slate-50/70 hover:bg-white border border-slate-200 rounded-xl p-3.5 transition-all duration-200 hover:shadow-md hover:border-slate-300 relative flex flex-col justify-between overflow-hidden"
              >
                <!-- Top Row: Room Number & Status Badge -->
                <div>
                  <div class="flex items-center justify-between gap-1.5 mb-1">
                    <span class="text-xl font-black text-slate-900 tracking-tight">
                      {{ room.room_number }}
                    </span>
                    <span
                      class="px-2 py-0.5 rounded-full text-[11px] font-bold border inline-flex items-center gap-1 shrink-0 whitespace-nowrap"
                      :class="getStatusBadge(room.status).badgeClass"
                    >
                      <v-icon :icon="getStatusBadge(room.status).icon" size="12" />
                      <span>{{ getStatusBadge(room.status).label }}</span>
                    </span>
                  </div>

                  <!-- Room Name (only show if not default "Phòng <number>") -->
                  <div
                    v-if="room.name && room.name !== `Phòng ${room.room_number}`"
                    class="text-xs text-slate-500 font-medium truncate mb-1"
                    :title="room.name"
                  >
                    {{ room.name }}
                  </div>

                  <!-- Room Layout & Wifi Mode Badges -->
                  <div class="flex items-center gap-1 flex-wrap my-1.5">
                    <span
                      class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded bg-blue-50 border border-blue-200/80 text-blue-700 text-[10px] font-bold"
                      :title="`${room.bedroom_count || 1} Phòng ngủ • ${room.living_room_count || 0} Phòng khách`"
                    >
                      <v-icon icon="mdi-bed" size="11" />
                      {{ room.bedroom_count || 1 }} PN
                      <template v-if="(room.living_room_count || 0) > 0">
                        • <v-icon icon="mdi-sofa" size="11" /> {{ room.living_room_count }} PK
                      </template>
                    </span>

                    <span
                      v-if="room.wifi_mode === 'shared'"
                      class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded bg-purple-50 border border-purple-200 text-purple-700 text-[10px] font-semibold"
                      :title="room.shared_wifi_note || (room.shared_wifi_room ? `Dùng ké Wifi P.${room.shared_wifi_room.room_number}` : 'Dùng ké Wifi phòng bên cạnh')"
                    >
                      <v-icon icon="mdi-wifi-arrow-left-right" size="11" />
                      {{ room.shared_wifi_room ? `Ké P.${room.shared_wifi_room.room_number}` : 'Ké Wifi' }}
                    </span>
                    <span
                      v-else-if="room.wifi_mode === 'none'"
                      class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded bg-slate-100 border border-slate-200 text-slate-500 text-[10px]"
                      title="Không trang bị Wifi"
                    >
                      <v-icon icon="mdi-wifi-off" size="11" /> Không Wifi
                    </span>
                    <span
                      v-else-if="room.wifi_router_count && room.wifi_router_count > 1"
                      class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded bg-blue-50 border border-blue-200 text-blue-700 text-[10px] font-semibold"
                      :title="`${room.wifi_router_count} Router Wifi riêng`"
                    >
                      <v-icon icon="mdi-wifi" size="11" /> {{ room.wifi_router_count }} Wifi
                    </span>
                  </div>
                </div>

                <!-- Middle Info: Type & Price -->
                <div class="space-y-1 my-2 text-xs">
                  <div class="flex items-center justify-between text-slate-600 gap-2">
                    <span class="text-slate-400 shrink-0">Hạng:</span>
                    <span class="font-semibold text-slate-700 truncate text-right">
                      {{ room.roomType?.name || room.room_type?.name || 'Tiêu chuẩn' }}
                    </span>
                  </div>
                  <div v-if="room.price" class="flex items-center justify-between text-slate-600 gap-2">
                    <span class="text-slate-400 shrink-0">Giá:</span>
                    <span class="font-bold text-emerald-600 truncate text-right">
                      {{ formatCurrency(room.price) }}
                    </span>
                  </div>
                </div>

                <!-- Equipment Badge & Actions -->
                <div class="pt-2 border-t border-slate-200/60 mt-2">
                  <div class="flex items-center justify-between gap-1">
                    <button
                      class="inline-flex items-center gap-1 text-xs font-semibold px-2 py-1 rounded-md bg-white border border-slate-200 text-slate-700 hover:bg-slate-100 hover:text-primary transition shrink-0"
                      title="Xem thiết bị gắn trong phòng"
                      @click="goToRoomEquipments(room.id)"
                    >
                      <v-icon icon="mdi-package-variant-closed" size="14" color="primary" />
                      <span>{{ room.equipments_count ?? 0 }} TB</span>
                    </button>

                    <div class="flex items-center gap-0.5 shrink-0">
                      <button
                        class="p-1 rounded text-slate-400 hover:text-primary hover:bg-slate-100 transition"
                        title="Chỉnh sửa"
                        @click="openEditModal(room)"
                      >
                        <v-icon icon="mdi-pencil-outline" size="16" />
                      </button>
                      <button
                        class="p-1 rounded text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition"
                        title="Xóa phòng"
                        @click="confirmDeleteRoom(room)"
                      >
                        <v-icon icon="mdi-trash-can-outline" size="16" />
                      </button>
                    </div>
                  </div>
                  <RoomEquipmentSummary :room="room" compact />
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- VIEW 2: Dạng Bảng Dữ Liệu (Table View) -->
      <div v-else-if="viewMode === 'table'" class="bg-white border border-slate-200/80 rounded-2xl shadow-xs overflow-hidden">
        <!-- Bulk Action Header if Selected -->
        <div v-if="selectedRoomIds.length > 0" class="p-3 bg-indigo-50 border-b border-indigo-100 flex items-center justify-between">
          <div class="text-xs font-semibold text-indigo-900 flex items-center gap-2">
            <v-icon icon="mdi-checkbox-marked" color="indigo" size="18" />
            Đã chọn {{ selectedRoomIds.length }} phòng
          </div>
          <AppButton variant="danger" size="small" @click="confirmBulkDelete">
            <v-icon start icon="mdi-trash-can" size="16" />
            Xóa các phòng đã chọn
          </AppButton>
        </div>

        <AppDataTable
          v-model="selectedRoomIds"
          :headers="tableHeaders"
          :items="rooms"
          :loading="loading"
          :total-items="pagination.totalItems"
          :page="pagination.currentPage"
          :items-per-page="pagination.perPage"
          item-value="id"
          show-select
          @update:page="handlePageChange"
          @click:row="item => goToRoomEquipments(item.id)"
        >
          <!-- Số phòng -->
          <template #item.room_number="{ item }">
            <span class="font-extrabold text-slate-900 text-sm">
              {{ item.room_number }}
            </span>
          </template>

          <!-- Tên phòng -->
          <template #item.name="{ item }">
            <span class="font-medium text-slate-800">
              {{ item.name || `Phòng ${item.room_number}` }}
            </span>
          </template>

          <!-- Block -->
          <template #item.block="{ item }">
            <span class="inline-flex items-center gap-1 text-xs font-semibold px-2 py-0.5 rounded bg-slate-100 text-slate-700">
              <v-icon icon="mdi-domain" size="12" />
              {{ item.block || 'Mặc định' }}
            </span>
          </template>

          <!-- Floor -->
          <template #item.floor="{ item }">
            <span class="text-xs font-bold text-slate-600">
              Tầng {{ item.floor }}
            </span>
          </template>

          <!-- Hạng phòng -->
          <template #item.room_type="{ item }">
            <span class="text-xs font-medium text-slate-700">
              {{ item.roomType?.name || item.room_type?.name || 'Tiêu chuẩn' }}
            </span>
          </template>

          <!-- Cơ cấu phòng & Wifi -->
          <template #item.composition="{ item }">
            <div class="flex flex-col gap-0.5 text-xs">
              <span class="inline-flex items-center gap-1 font-semibold text-slate-800">
                <v-icon icon="mdi-bed" size="13" class="text-blue-600" />
                {{ item.bedroom_count ?? 1 }} PN
                <template v-if="(item.living_room_count ?? 0) > 0">
                  • <v-icon icon="mdi-sofa" size="13" class="text-indigo-600" /> {{ item.living_room_count }} PK
                </template>
              </span>
              <span v-if="item.wifi_mode === 'shared'" class="text-[11px] text-purple-700 flex items-center gap-0.5">
                <v-icon icon="mdi-wifi-arrow-left-right" size="12" />
                {{ item.shared_wifi_room ? `Ké P.${item.shared_wifi_room.room_number}` : 'Ké Wifi' }}
              </span>
              <span v-else-if="item.wifi_mode === 'none'" class="text-[11px] text-slate-400 flex items-center gap-0.5">
                <v-icon icon="mdi-wifi-off" size="12" /> Không Wifi
              </span>
              <span v-else class="text-[11px] text-emerald-600 flex items-center gap-0.5">
                <v-icon icon="mdi-wifi" size="12" /> {{ (item.wifi_router_count && item.wifi_router_count > 1) ? `${item.wifi_router_count} Router Wifi` : 'Router riêng' }}
              </span>
            </div>
          </template>

          <!-- Thiết bị trong phòng -->
          <template #item.equipments_count="{ item }">
            <div class="min-w-40">
              <button
                class="inline-flex items-center gap-1 text-xs font-semibold px-2 py-0.5 rounded-full bg-blue-50 text-blue-700 hover:bg-blue-100 transition"
                @click="goToRoomEquipments(item.id)"
              >
                <v-icon icon="mdi-package-variant-closed" size="14" />
                {{ item.equipments_count ?? 0 }} thiết bị
              </button>
              <RoomEquipmentSummary :room="item" />
            </div>
          </template>

          <!-- Giá -->
          <template #item.price="{ item }">
            <span class="text-xs font-semibold text-emerald-600">
              {{ item.price ? formatCurrency(item.price) : '0 đ' }}
            </span>
          </template>

          <!-- Trạng thái -->
          <template #item.status="{ item }">
            <span
              class="px-2 py-0.5 rounded-full text-xs font-bold border inline-flex items-center gap-1"
              :class="getStatusBadge(item.status).badgeClass"
            >
              <v-icon :icon="getStatusBadge(item.status).icon" size="12" />
              {{ getStatusBadge(item.status).label }}
            </span>
          </template>

          <!-- Actions -->
          <template #item.actions="{ item }">
            <div class="flex items-center justify-end gap-1">
              <button
                class="p-1 rounded text-slate-400 hover:text-primary hover:bg-slate-100 transition"
                title="Chỉnh sửa"
                @click="openEditModal(item)"
              >
                <v-icon icon="mdi-pencil-outline" size="18" />
              </button>
              <button
                class="p-1 rounded text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition"
                title="Xóa phòng"
                @click="confirmDeleteRoom(item)"
              >
                <v-icon icon="mdi-trash-can-outline" size="18" />
              </button>
            </div>
          </template>
        </AppDataTable>
      </div>
    </div>

    <!-- Modals -->
    <!-- Thêm / Sửa phòng đơn -->
    <RoomFormModal
      v-model="formModalOpen"
      :room="selectedRoomForEdit"
      :room-types="roomTypes"
      :existing-blocks="existingBlocks"
      @saved="handleRoomSaved"
    />

    <!-- Tạo phòng nhanh hàng loạt -->
    <RoomBatchModal
      v-model="batchModalOpen"
      :room-types="roomTypes"
      :existing-blocks="existingBlocks"
      @generated="fetchRooms"
    />

    <!-- Quản lý Block -->
    <ManageStructureModal
      v-model="structureModalOpen"
      :existing-blocks="existingBlocks"
      @updated="fetchRooms"
    />

    <!-- Dialog Xóa phòng đơn -->
    <AppConfirmDialog
      v-model="deleteDialogOpen"
      title="Xác nhận Xóa Phòng"
      type="danger"
      confirm-text="Xóa phòng & Thu hồi thiết bị"
      :loading="deleteLoading"
      @confirm="handleDeleteRoom"
    >
      <div class="space-y-3">
        <p class="text-sm text-slate-700">
          Bạn có chắc chắn muốn xóa phòng <strong>{{ roomToDelete?.room_number }}</strong> ({{ roomToDelete?.block }} - Tầng {{ roomToDelete?.floor }})?
        </p>
        <div class="p-3 bg-amber-50 border border-amber-200 rounded-xl flex items-start gap-2.5">
          <v-icon icon="mdi-shield-check" color="amber-darken-3" class="mt-0.5" />
          <div class="text-xs text-amber-900 leading-relaxed">
            <strong class="font-bold">Bảo toàn thiết bị:</strong> Phòng này hiện có <strong>{{ roomToDelete?.equipments_count ?? 0 }} thiết bị</strong>. Toàn bộ thiết bị sẽ tự động được thu hồi về kho (trạng thái: <em>Tồn kho</em>), không bị xóa mất dữ liệu.
          </div>
        </div>
      </div>
    </AppConfirmDialog>

    <!-- Dialog Xóa nhiều phòng -->
    <AppConfirmDialog
      v-model="bulkDeleteDialogOpen"
      title="Xác nhận Xóa Nhiều Phòng"
      type="danger"
      confirm-text="Xóa các phòng đã chọn"
      :loading="bulkDeleteLoading"
      @confirm="handleBulkDelete"
    >
      <div class="space-y-3">
        <p class="text-sm text-slate-700">
          Bạn có chắc chắn muốn xóa <strong>{{ selectedRoomIds.length }} phòng</strong> đã chọn?
        </p>
        <div class="p-3 bg-amber-50 border border-amber-200 rounded-xl flex items-start gap-2.5">
          <v-icon icon="mdi-shield-check" color="amber-darken-3" class="mt-0.5" />
          <div class="text-xs text-amber-900 leading-relaxed">
            Hệ thống sẽ tự động thu hồi toàn bộ các thiết bị trong các phòng này về kho lưu trữ an toàn.
          </div>
        </div>
      </div>
    </AppConfirmDialog>
  </DefaultLayout>
</template>
