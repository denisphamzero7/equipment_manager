<script setup>
import { ref, onMounted, computed, watch, defineAsyncComponent } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { equipmentService } from '../../services/equipmentService'
import { useRealtimeStore } from '@/core/stores/realtimeStore'
import { resolveMediaUrl } from '@/core/helpers/formatters'
import { roomService } from '@/modules/room/services/roomService'
import { toast } from '@/core/helpers/toast'
import DefaultLayout from '@/core/layouts/DefaultLayout.vue'
import AppPageHeader from '@/core/components/AppPageHeader.vue'
import AppButton from '@/core/components/AppButton.vue'

// Domain Presentational Components
import MapFilterToolbar from '../../components/MapFilterToolbar.vue'
import MapFloorGrid from '../../components/MapFloorGrid.vue'
import MapRoomDetailModal from '../../components/MapRoomDetailModal.vue'

// Reused Async Component
const RoomFormModal = defineAsyncComponent(() => import('@/modules/room/components/RoomFormModal.vue'))

const router = useRouter()
const route = useRoute()
const realtimeStore = useRealtimeStore()

// Master hotel block order matching hotel layout
const hotelBlockOrder = ['Block A', 'Block B', 'Block C', 'Block D', 'Condos 1', 'Condos 2', 'Penthouse-spa+Villa']

// Data state
const loading = ref(true)
const rawRooms = ref([])
const roomTypesList = ref([])

// Filter states
const selectedBlock = ref('')
const selectedFloor = ref(null)
const selectedStatus = ref('')
const searchQuery = ref('')
const floorSortOrder = ref('asc')

// Detail room modal state
const selectedRoomDetail = ref(null)
const selectedRoomSummary = ref(null)
const roomDetailModalOpen = ref(false)
const roomDetailLoading = ref(false)
let roomDetailRequestId = 0

// Edit room modal directly from Map View
const editRoomModalOpen = ref(false)
const roomForEdit = ref(null)

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

onMounted(async () => {
  if (route.query.block) {
    selectedBlock.value = String(route.query.block)
  }
  if (route.query.floor) {
    selectedFloor.value = Number(route.query.floor)
  }
  if (route.query.status) {
    selectedStatus.value = String(route.query.status)
  }
  loadMapViewData()
})

async function openEditRoomModal() {
  if (!selectedRoomDetail.value) return
  try {
    roomTypesList.value = await roomService.getRoomTypes()
  } catch (error) {
    console.error('Failed to load room types for editing:', error)
    return
  }
  roomForEdit.value = {
    ...selectedRoomDetail.value,
    ...(selectedRoomDetail.value.room || {}),
  }
  editRoomModalOpen.value = true
}

async function handleRoomSaved(savedRoom) {
  if (!savedRoom?.id) {
    await loadMapViewData(true)
    return
  }

  rawRooms.value = rawRooms.value.map(room =>
    Number(room.id) === Number(savedRoom.id) ? { ...room, ...savedRoom } : room
  )
  if (selectedRoomDetail.value?.id) {
    await openRoomDetail(rawRooms.value.find(room => Number(room.id) === Number(savedRoom.id)) || savedRoom)
  }
}

// Tự động cập nhật sơ đồ phòng Real-time khi có sự kiện từ WebSocket
watch(
  () => realtimeStore.lastEvent,
  (event) => {
    if (!event) return
    loadMapViewData(true)
  }
)

async function loadMapViewData(silent = false) {
  if (!silent) {
    loading.value = true
  }
  try {
    const data = await equipmentService.getMapView({})
    rawRooms.value = Array.isArray(data) ? data : []
  } catch (e) {
    console.error('Failed to load map view rooms:', e)
    toast.error(e.response?.data?.message || 'Không thể tải sơ đồ phòng.')
  } finally {
    if (!silent) {
      loading.value = false
    }
  }
}

// Compute distinct blocks ordered by hotelBlockOrder
const existingBlocks = computed(() => {
  const set = new Set(rawRooms.value.map(r => r.block).filter(Boolean))
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

// Room counts per block for pill badges
const blockCounts = computed(() => {
  const counts = { total: rawRooms.value.length }
  rawRooms.value.forEach(r => {
    const b = r.block || 'Khu vực chung'
    counts[b] = (counts[b] || 0) + 1
  })
  return counts
})

// Floors available based on selected block
const availableFloors = computed(() => {
  const target = selectedBlock.value
    ? rawRooms.value.filter(r => r.block === selectedBlock.value)
    : rawRooms.value
  const set = new Set(target.map(r => r.floor).filter(f => f !== null && f !== undefined))
  return Array.from(set).sort((a, b) => a - b)
})

const hasActiveFilters = computed(() => {
  return !!(searchQuery.value || selectedBlock.value || (selectedFloor.value !== null && selectedFloor.value !== '') || selectedStatus.value || floorSortOrder.value !== 'asc')
})

function selectBlock(b) {
  selectedBlock.value = b
  selectedFloor.value = null
}

function selectStatus(statusKey) {
  if (selectedStatus.value === statusKey) {
    selectedStatus.value = ''
  } else {
    selectedStatus.value = statusKey
  }
}

function resetFilters() {
  searchQuery.value = ''
  selectedBlock.value = ''
  selectedFloor.value = null
  selectedStatus.value = ''
  floorSortOrder.value = 'asc'
}

// Status options for select dropdown
const statusFilterOptions = [
  { value: '', label: 'Tất cả tình trạng thiết bị' },
  { value: 'good', label: 'Hoạt động tốt & Đầy đủ' },
  { value: 'maintenance', label: 'Đang bảo trì thiết bị' },
  { value: 'broken', label: 'Có thiết bị hỏng / Thiếu' },
  { value: 'unconfigured', label: 'Chưa cấu hình thiết bị' },
]

// Status color legends
const colorLegends = [
  { key: 'good', label: 'Hoạt động tốt & Đầy đủ', color: 'bg-emerald-500', text: 'text-emerald-700', border: 'border-emerald-200' },
  { key: 'maintenance', label: 'Đang bảo trì thiết bị', color: 'bg-amber-500', text: 'text-amber-700', border: 'border-amber-200' },
  { key: 'broken', label: 'Có thiết bị hỏng / Thiếu', color: 'bg-rose-500', text: 'text-rose-700', border: 'border-rose-200' },
  { key: 'unconfigured', label: 'Chưa cấu hình thiết bị', color: 'bg-slate-400', text: 'text-slate-600', border: 'border-slate-200' },
]

// Group rooms by Block and Floor
const groupedRooms = computed(() => {
  const filtered = rawRooms.value.filter(room => {
    // 1. Search filter
    if (searchQuery.value) {
      const q = searchQuery.value.toLowerCase().trim()
      const matchNum = String(room.room_number).toLowerCase().includes(q)
      const matchName = room.name?.toLowerCase().includes(q)
      if (!matchNum && !matchName) return false
    }

    // 2. Block filter
    if (selectedBlock.value && room.block !== selectedBlock.value) {
      return false
    }

    // 3. Floor filter
    if (selectedFloor.value !== null && selectedFloor.value !== '' && room.floor !== Number(selectedFloor.value)) {
      return false
    }

    // 4. Equipment Status filter
    if (selectedStatus.value) {
      const isGood = room.badge_color === 'green' || room.equipment_status === 'good'
      const isMaint = room.badge_color === 'yellow' || room.equipment_status === 'maintenance'
      const isBroken = room.badge_color === 'red' || room.equipment_status === 'broken' || room.equipment_status === 'missing'
      const isUnconf = !room.badge_color || room.equipment_status === 'unconfigured'

      if (selectedStatus.value === 'good' && !isGood) return false
      if (selectedStatus.value === 'maintenance' && !isMaint) return false
      if (selectedStatus.value === 'broken' && !isBroken) return false
      if (selectedStatus.value === 'unconfigured' && !isUnconf) return false
    }

    return true
  })

  // Group into Block -> Floor -> Rooms
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
      const sortedFloors = Object.values(b.floors).sort((f1, f2) => {
        return floorSortOrder.value === 'desc' ? f2.floor - f1.floor : f1.floor - f2.floor
      })
      sortedFloors.forEach(f => {
        f.rooms.sort((r1, r2) => {
          if (r1.grid_col && r2.grid_col && r1.grid_col !== r2.grid_col) {
            return r1.grid_col - r2.grid_col
          }
          return String(r1.room_number).localeCompare(String(r2.room_number), 'vi', { numeric: true })
        })
      })
      return {
        name: b.name,
        floors: sortedFloors,
        totalRooms: sortedFloors.reduce((sum, f) => sum + f.rooms.length, 0),
      }
    })
})

const totalFilteredRooms = computed(() => {
  return groupedRooms.value.reduce((sum, b) => sum + b.totalRooms, 0)
})

async function openRoomDetail(room) {
  roomDetailModalOpen.value = true
  selectedRoomSummary.value = room
  selectedRoomDetail.value = null
  roomDetailLoading.value = true
  const requestId = ++roomDetailRequestId
  try {
    const detail = await equipmentService.getRoomDetail(room.id)
    if (requestId === roomDetailRequestId) {
      selectedRoomDetail.value = detail
    }
  } catch (error) {
    if (requestId === roomDetailRequestId) {
      toast.error(error.response?.data?.message || 'Không thể tải chi tiết thiết bị của phòng.')
    }
  } finally {
    if (requestId === roomDetailRequestId) {
      roomDetailLoading.value = false
    }
  }
}
</script>

<template>
  <DefaultLayout>
    <AppPageHeader
      title="Sơ đồ Mặt bằng & Tình trạng Thiết bị"
      subtitle="Theo dõi trực quan sơ đồ các phòng, phát hiện phòng có thiết bị hỏng hoặc thiếu định mức"
      :breadcrumbs="[
        { title: 'Trang chủ', to: '/dashboard' },
        { title: 'Sơ đồ mặt bằng' },
      ]"
    >
      <template #actions>
        <AppButton
          variant="secondary"
          icon="mdi-door"
          @click="router.push({ path: '/rooms', query: selectedBlock ? { block: selectedBlock } : {} })"
        >
          Quản lý phòng
        </AppButton>
        <AppButton
          variant="tonal"
          color="secondary"
          icon="mdi-format-list-bulleted"
          @click="router.push('/equipments')"
        >
          Dạng danh sách
        </AppButton>
        <AppButton
          icon="mdi-refresh"
          color="primary"
          @click="loadMapViewData(false)"
        >
          Tải lại sơ đồ
        </AppButton>
      </template>
    </AppPageHeader>

    <!-- 1. Upgraded Filter & Control Card (Child Component) -->
    <MapFilterToolbar
      v-model:search-query="searchQuery"
      v-model:selected-block="selectedBlock"
      v-model:selected-floor="selectedFloor"
      v-model:selected-status="selectedStatus"
      v-model:floor-sort-order="floorSortOrder"
      :existing-blocks="existingBlocks"
      :block-counts="blockCounts"
      :available-floors="availableFloors"
      :status-filter-options="statusFilterOptions"
      :color-legends="colorLegends"
      :has-active-filters="hasActiveFilters"
      :total-filtered-rooms="totalFilteredRooms"
      :total-raw-rooms="rawRooms.length"
      @select-block="selectBlock"
      @select-status="selectStatus"
      @reset-filters="resetFilters"
    />

    <!-- 2. Loading Skeleton -->
    <div v-if="loading" class="space-y-6">
      <div v-for="n in 2" :key="n" class="space-y-4">
        <div class="h-6 w-36 bg-slate-200 rounded animate-pulse" />
        <div class="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-xs space-y-3">
          <div class="h-4 w-24 bg-slate-200 rounded animate-pulse" />
          <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 2xl:grid-cols-7 gap-2.5 sm:gap-3.5">
            <div v-for="c in 6" :key="c" class="h-32 bg-slate-100 rounded-xl animate-pulse" />
          </div>
        </div>
      </div>
    </div>

    <!-- 3. Grouped Blocks & Floors Grid (Child Component) -->
    <MapFloorGrid
      v-else-if="groupedRooms.length > 0"
      :grouped-rooms="groupedRooms"
      :selected-block="selectedBlock"
      @select-block="selectBlock"
      @select-room="openRoomDetail"
    />

    <!-- 4. Empty state -->
    <div v-else class="text-center py-16 bg-white rounded-2xl border border-slate-200 p-8 shadow-xs">
      <v-icon icon="mdi-map-marker-off-outline" size="56" class="text-slate-300 mb-2 block mx-auto" />
      <h3 class="text-base font-bold text-slate-700">Không tìm thấy phòng nào</h3>
      <p class="text-xs text-slate-400 mt-1 max-w-sm mx-auto">
        Không có phòng nào phù hợp với bộ lọc hiện tại. Hãy thử chọn Block hoặc Tầng khác.
      </p>
      <div class="mt-4">
        <AppButton variant="secondary" @click="resetFilters">
          <v-icon start icon="mdi-refresh" />
          Đặt lại bộ lọc
        </AppButton>
      </div>
    </div>

    <!-- 5. Room Equipment Detail Modal (Child Component) -->
    <MapRoomDetailModal
      v-model="roomDetailModalOpen"
      :room-detail="selectedRoomDetail"
      :room-summary="selectedRoomSummary"
      :loading="roomDetailLoading"
      @open-edit-room="openEditRoomModal"
      @open-lightbox="openLightbox"
      @assign-more="roomId => router.push(`/equipments?action=create&room_id=${roomId}`)"
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

    <!-- Modal chỉnh sửa phòng / cơ cấu nhanh từ Map View -->
    <RoomFormModal
      v-model="editRoomModalOpen"
      :room="roomForEdit"
      :room-types="roomTypesList"
      :existing-blocks="existingBlocks"
      @saved="handleRoomSaved"
    />
  </DefaultLayout>
</template>
