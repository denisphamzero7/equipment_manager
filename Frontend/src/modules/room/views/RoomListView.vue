<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import DefaultLayout from '@/core/layouts/DefaultLayout.vue'
import AppPageHeader from '@/core/components/AppPageHeader.vue'
import AppDataTable from '@/core/components/AppDataTable.vue'
import AppButton from '@/core/components/AppButton.vue'
import AppConfirmDialog from '@/core/components/AppConfirmDialog.vue'
import RoomFormModal from '../components/RoomFormModal.vue'
import RoomBatchModal from '../components/RoomBatchModal.vue'
import ManageStructureModal from '../components/ManageStructureModal.vue'
import { roomService } from '../services/roomService'
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
const rooms = ref([])
const roomTypes = ref([])
const totalRooms = ref(0)
const selectedRoomIds = ref([])

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
  { key: 'equipments_count', title: 'Trang thiết bị', width: '130px' },
  { key: 'price', title: 'Giá niêm yết', width: '130px' },
  { key: 'status', title: 'Trạng thái', width: '140px' },
  { key: 'actions', title: 'Thao tác', align: 'end', width: '130px' },
]

// Extract distinct blocks and floors from rooms list
const existingBlocks = computed(() => {
  const set = new Set(rooms.value.map(r => r.block).filter(Boolean))
  return Array.from(set).sort()
})

const existingFloors = computed(() => {
  const set = new Set(rooms.value.map(r => r.floor).filter(f => f !== null && f !== undefined))
  return Array.from(set).sort((a, b) => a - b)
})

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

  // Sort blocks alphabetically, floors & rooms ascending
  return Object.values(blocksMap)
    .sort((b1, b2) => b1.name.localeCompare(b2.name, 'vi', { numeric: true }))
    .map(b => {
      const sortedFloors = Object.values(b.floors).sort((f1, f2) => f1.floor - f2.floor)
      sortedFloors.forEach(f => {
        f.rooms.sort((r1, r2) => r1.room_number - r2.room_number)
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
  rooms.value.forEach(r => {
    if (r.status === 'ready' || r.status === 'available') ready++
    else if (r.status === 'occupied') occupied++
    else if (r.status === 'not_ready' || r.status === 'cleaning' || r.status === 'dirty') notReady++
    else if (r.status === 'maintenance') maintenance++
  })
  return { total: rooms.value.length, ready, occupied, notReady, maintenance }
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
  }
}

async function fetchRooms(showLoading = true) {
  if (showLoading) loading.value = true
  try {
    const params = {
      limit: viewMode.value === 'table' ? pagination.perPage : 150,
      page: viewMode.value === 'table' ? pagination.currentPage : 1,
      ...filters,
    }
    const res = await roomService.getRooms(params)
    const items = Array.isArray(res) ? res : (res?.data || [])
    rooms.value = Array.isArray(items) ? items : []

    const total = res?.total ?? (Array.isArray(res) ? res.length : rooms.value.length)
    pagination.totalItems = total
    pagination.totalPages = res?.last_page || 1
    totalRooms.value = total
  } catch (err) {
    console.error('Fetch rooms error:', err)
    toast.error('Không thể tải danh sách phòng.')
  } finally {
    if (showLoading) loading.value = false
  }
}

function handleSearch() {
  pagination.currentPage = 1
  fetchRooms()
}

function resetFilters() {
  filters.search = ''
  filters.block = ''
  filters.floor = null
  filters.room_type_id = null
  filters.status = ''
  pagination.currentPage = 1
  fetchRooms()
}

function openCreateModal(defaultBlock = null, defaultFloor = null) {
  selectedRoomForEdit.value = defaultBlock ? { block: defaultBlock, floor: defaultFloor } : null
  formModalOpen.value = true
}

function openEditModal(room) {
  selectedRoomForEdit.value = room
  formModalOpen.value = true
}

function confirmDeleteRoom(room) {
  roomToDelete.value = room
  deleteDialogOpen.value = true
}

async function handleDeleteRoom() {
  if (!roomToDelete.value) return
  deleteLoading.value = true
  try {
    await roomService.deleteRoom(roomToDelete.value.id, true)
    toast.success(`Đã xóa phòng ${roomToDelete.value.room_number}. Thiết bị đã tự động thu hồi về kho an toàn.`)
    deleteDialogOpen.value = false
    roomToDelete.value = null
    fetchRooms()
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
    selectedRoomIds.value = []
    bulkDeleteDialogOpen.value = false
    fetchRooms()
  } catch (err) {
    const msg = err.response?.data?.message || err.message || 'Lỗi khi xóa nhiều phòng.'
    toast.error(msg)
  } finally {
    bulkDeleteLoading.value = false
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

      <!-- Filters & View Switcher Bar -->
      <div class="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-xs">
        <div class="flex flex-col lg:flex-row items-stretch lg:items-center justify-between gap-4">
          <!-- Filters row -->
          <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-3 flex-1">
            <!-- Search -->
            <v-text-field
              v-model="filters.search"
              placeholder="Tìm theo số phòng, tên..."
              density="compact"
              variant="outlined"
              hide-details
              prepend-inner-icon="mdi-magnify"
              clearable
              @update:model-value="handleSearch"
            />

            <!-- Filter Block -->
            <v-select
              v-model="filters.block"
              :items="['', ...existingBlocks]"
              item-title="title"
              placeholder="Tất cả Block"
              density="compact"
              variant="outlined"
              hide-details
              prepend-inner-icon="mdi-domain"
              clearable
              @update:model-value="handleSearch"
            >
              <template #selection="{ item }">
                {{ item.raw || 'Tất cả Block' }}
              </template>
              <template #item="{ item, props: itemProps }">
                <v-list-item v-bind="itemProps" :title="item.raw || 'Tất cả Block'" />
              </template>
            </v-select>

            <!-- Filter Floor -->
            <v-select
              v-model="filters.floor"
              :items="['', ...existingFloors]"
              placeholder="Tất cả Tầng"
              density="compact"
              variant="outlined"
              hide-details
              prepend-inner-icon="mdi-stairs"
              clearable
              @update:model-value="handleSearch"
            >
              <template #selection="{ item }">
                {{ item.raw ? `Tầng ${item.raw}` : 'Tất cả Tầng' }}
              </template>
              <template #item="{ item, props: itemProps }">
                <v-list-item v-bind="itemProps" :title="item.raw ? `Tầng ${item.raw}` : 'Tất cả Tầng'" />
              </template>
            </v-select>

            <!-- Filter Room Type -->
            <v-select
              v-model="filters.room_type_id"
              :items="[{ id: null, name: 'Tất cả loại phòng' }, ...roomTypes]"
              item-title="name"
              item-value="id"
              placeholder="Tất cả loại phòng"
              density="compact"
              variant="outlined"
              hide-details
              prepend-inner-icon="mdi-bed-outline"
              clearable
              @update:model-value="handleSearch"
            />

            <!-- Filter Status -->
            <v-select
              v-model="filters.status"
              :items="[{ value: '', label: 'Tất cả trạng thái' }, ...RoomStatusOptions]"
              item-title="label"
              item-value="value"
              placeholder="Tất cả trạng thái"
              density="compact"
              variant="outlined"
              hide-details
              prepend-inner-icon="mdi-shield-check-outline"
              clearable
              @update:model-value="handleSearch"
            />
          </div>

          <!-- View Mode Toggle Buttons -->
          <div class="flex items-center gap-2 self-end lg:self-center">
            <button
              class="px-3 py-1.5 text-xs font-semibold rounded-lg border transition-all duration-150 flex items-center gap-1.5"
              :class="viewMode === 'cards' ? 'bg-primary text-white border-primary shadow-xs' : 'bg-slate-50 text-slate-600 border-slate-200 hover:bg-slate-100'"
              @click="viewMode = 'cards'"
            >
              <v-icon icon="mdi-view-grid-outline" size="16" />
              Thẻ theo tầng
            </button>
            <button
              class="px-3 py-1.5 text-xs font-semibold rounded-lg border transition-all duration-150 flex items-center gap-1.5"
              :class="viewMode === 'table' ? 'bg-primary text-white border-primary shadow-xs' : 'bg-slate-50 text-slate-600 border-slate-200 hover:bg-slate-100'"
              @click="viewMode = 'table'"
            >
              <v-icon icon="mdi-table" size="16" />
              Dạng bảng
            </button>
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
        <div v-else-if="groupedRooms.length === 0" class="py-16 text-center bg-white rounded-2xl border border-slate-200/80 p-8">
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
                <div class="pt-2 border-t border-slate-200/60 flex items-center justify-between gap-1 mt-2">
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

          <!-- Thiết bị trong phòng -->
          <template #item.equipments_count="{ item }">
            <button
              class="inline-flex items-center gap-1 text-xs font-semibold px-2 py-0.5 rounded-full bg-blue-50 text-blue-700 hover:bg-blue-100 transition"
              @click="goToRoomEquipments(item.id)"
            >
              <v-icon icon="mdi-package-variant-closed" size="14" />
              {{ item.equipments_count ?? 0 }} thiết bị
            </button>
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
      @saved="fetchRooms"
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
