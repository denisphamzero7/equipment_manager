<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import { useRouter } from 'vue-router'
import { equipmentService } from '../services/equipmentService'
import { useRealtimeStore } from '@/core/stores/realtimeStore'
import DefaultLayout from '@/core/layouts/DefaultLayout.vue'
import AppPageHeader from '@/core/components/AppPageHeader.vue'
import AppButton from '@/core/components/AppButton.vue'
import AppModal from '@/core/components/AppModal.vue'
import AppStatusBadge from '@/core/components/AppStatusBadge.vue'
import { resolveMediaUrl } from '@/core/helpers/formatters'

const router = useRouter()
const realtimeStore = useRealtimeStore()

// Master hotel block order matching hotel layout
const hotelBlockOrder = ['Block A', 'Block B', 'Block C', 'Block D', 'Condos 1', 'Condos 2', 'Penthouse-spa+Villa']

// Data state
const loading = ref(true)
const rawRooms = ref([])

// Filter states
const selectedBlock = ref('')
const selectedFloor = ref(null)
const selectedStatus = ref('')
const searchQuery = ref('')

// Detail room modal
const selectedRoomDetail = ref(null)
const roomDetailModalOpen = ref(false)
const roomDetailLoading = ref(false)

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
    ac: 'mdi-air-conditioner',
    air_conditioner: 'mdi-air-conditioner',
    refrigerator: 'mdi-fridge',
    kettle: 'mdi-kettle-outline',
    safe: 'mdi-safe',
    hairdryer: 'mdi-hair-dryer',
  }
  return map[code?.toLowerCase()] || 'mdi-devices'
}

onMounted(() => {
  loadMapViewData()
})

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

// Sắp xếp tầng: mặc định 'asc' (Tầng 1 -> 7 đồng bộ với Quản lý phòng)
const floorSortOrder = ref('asc')

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

// Group rooms by Block and Floor (hierarchical layout matching hotel blueprints)
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
      // Sort floors: mặc định 'asc' (Tầng 1 -> 7 đồng bộ với Quản lý phòng)
      const sortedFloors = Object.values(b.floors).sort((f1, f2) => {
        return floorSortOrder.value === 'desc' ? f2.floor - f1.floor : f1.floor - f2.floor
      })
      sortedFloors.forEach(f => {
        // Sort rooms by grid_col or room_number
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
  roomDetailLoading.value = true
  try {
    selectedRoomDetail.value = await equipmentService.getRoomDetail(room.id)
  } catch {
    selectedRoomDetail.value = null
  } finally {
    roomDetailLoading.value = false
  }
}

function getRoomColorClass(room) {
  if (room.badge_color === 'green' || room.equipment_status === 'good') {
    return 'border-emerald-300 bg-white hover:border-emerald-500 hover:shadow-emerald-100'
  }
  if (room.badge_color === 'yellow' || room.equipment_status === 'maintenance') {
    return 'border-amber-300 bg-amber-50/40 hover:border-amber-500 hover:shadow-amber-100'
  }
  if (room.badge_color === 'red' || room.equipment_status === 'broken' || room.equipment_status === 'missing') {
    return 'border-rose-300 bg-rose-50/40 hover:border-rose-500 hover:shadow-rose-100'
  }
  return 'border-slate-200 bg-white hover:border-slate-400'
}

function getBadgeIndicatorClass(room) {
  if (room.badge_color === 'green') return 'bg-emerald-500'
  if (room.badge_color === 'yellow') return 'bg-amber-500'
  if (room.badge_color === 'red') return 'bg-rose-500'
  return 'bg-slate-400'
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

    <!-- Upgraded Control & Filter Card (Synchronized with Room Management) -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs mb-6 overflow-hidden">
      <!-- Top Section: Block Quick-Filter Pills Bar -->
      <div class="px-4 pt-3.5 pb-3 border-b border-slate-100 bg-slate-50/60">
        <div class="flex items-center justify-between gap-2 mb-2">
          <span class="text-xs font-bold text-slate-500 uppercase tracking-wider flex items-center gap-1.5">
            <v-icon icon="mdi-domain" size="15" color="primary" />
            <span>Phân khu & Tòa nhà (Block):</span>
          </span>
          <button
            v-if="selectedBlock"
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
            :class="!selectedBlock
              ? 'bg-primary text-white border-primary shadow-xs'
              : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-100 hover:border-slate-300'"
            @click="selectBlock('')"
          >
            <span>Tất cả Block</span>
            <span
              class="px-1.5 py-0.2 rounded-full text-[10px] font-extrabold"
              :class="!selectedBlock ? 'bg-white/25 text-white' : 'bg-slate-100 text-slate-600'"
            >
              {{ blockCounts.total || 0 }}
            </span>
          </button>

          <button
            v-for="b in existingBlocks"
            :key="b"
            class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all duration-150 shrink-0 flex items-center gap-2 border cursor-pointer"
            :class="selectedBlock === b
              ? 'bg-primary text-white border-primary shadow-xs'
              : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-100 hover:border-slate-300'"
            @click="selectBlock(b)"
          >
            <span>{{ b }}</span>
            <span
              class="px-1.5 py-0.2 rounded-full text-[10px] font-extrabold"
              :class="selectedBlock === b ? 'bg-white/25 text-white' : 'bg-slate-100 text-slate-600'"
            >
              {{ blockCounts[b] || 0 }}
            </span>
          </button>
        </div>
      </div>

      <!-- Bottom Section: Search, Floor, Status & Clickable Legends -->
      <div class="p-4 space-y-3.5">
        <div class="flex flex-col lg:flex-row items-stretch lg:items-center justify-between gap-3.5">
          <!-- Left: Search Box -->
          <div class="flex-1 min-w-[240px]">
            <v-text-field
              v-model="searchQuery"
              placeholder="Tìm nhanh theo số phòng, tên phòng..."
              density="compact"
              variant="outlined"
              hide-details
              prepend-inner-icon="mdi-magnify"
              clearable
              bg-color="slate-50"
            />
          </div>

          <!-- Middle: Filters Group (Floor, Status) -->
          <div class="flex flex-wrap sm:flex-nowrap items-center gap-2.5">
            <!-- Floor Filter -->
            <div class="w-full sm:w-36 shrink-0">
              <v-select
                v-model="selectedFloor"
                :items="[{ value: null, title: 'Tất cả Tầng' }, ...availableFloors.map(f => ({ value: f, title: `Tầng ${f}` }))]"
                item-title="title"
                item-value="value"
                density="compact"
                variant="outlined"
                hide-details
                prepend-inner-icon="mdi-stairs"
              />
            </div>

            <!-- Floor Sort Direction Toggle Button -->
            <button
              type="button"
              class="px-2.5 py-2 text-xs font-semibold rounded-lg border border-slate-200 text-slate-700 bg-white hover:bg-slate-50 transition flex items-center gap-1.5 shrink-0 cursor-pointer shadow-xs"
              :title="floorSortOrder === 'asc' ? 'Đang sắp xếp Tầng 1 → 7 (Bấm để đảo 7 → 1)' : 'Đang sắp xếp Tầng 7 → 1 (Bấm để đảo 1 → 7)'"
              @click="floorSortOrder = floorSortOrder === 'asc' ? 'desc' : 'asc'"
            >
              <v-icon :icon="floorSortOrder === 'asc' ? 'mdi-sort-numeric-ascending' : 'mdi-sort-numeric-descending'" size="16" color="primary" />
              <span>Tầng {{ floorSortOrder === 'asc' ? '1 → 7' : '7 → 1' }}</span>
            </button>

            <!-- Status Filter -->
            <div class="w-full sm:w-52 shrink-0">
              <v-select
                v-model="selectedStatus"
                :items="statusFilterOptions"
                item-title="label"
                item-value="value"
                density="compact"
                variant="outlined"
                hide-details
                prepend-inner-icon="mdi-shield-check-outline"
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

          <!-- Counter badge -->
          <div class="text-xs text-slate-500 font-medium whitespace-nowrap self-end lg:self-center">
            Tìm thấy <strong class="text-slate-800 text-sm font-bold">{{ totalFilteredRooms }}</strong> / {{ rawRooms.length }} phòng
          </div>
        </div>

        <!-- Interactive Color Legend Badges (Click to quick-filter!) -->
        <div class="pt-2 border-t border-slate-100 flex flex-wrap items-center gap-2 sm:gap-3 text-xs">
          <span class="text-slate-400 font-semibold mr-1">Tình trạng:</span>
          <button
            v-for="leg in colorLegends"
            :key="leg.key"
            class="px-2.5 py-1 rounded-lg border text-xs font-medium transition-all duration-150 flex items-center gap-1.5 cursor-pointer"
            :class="selectedStatus === leg.key
              ? `${leg.border} ${leg.text} bg-white ring-2 ring-primary shadow-xs font-bold`
              : 'border-slate-200/80 bg-slate-50 text-slate-600 hover:bg-white hover:border-slate-300'"
            @click="selectStatus(leg.key)"
          >
            <span class="w-2.5 h-2.5 rounded-full flex-shrink-0" :class="leg.color" />
            <span>{{ leg.label }}</span>
          </button>
        </div>
      </div>
    </div>

    <!-- Map View Grouped by Block and Floor -->
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

    <!-- Grouped Blocks & Floors Grid -->
    <div v-else-if="groupedRooms.length > 0" class="space-y-6">
      <div
        v-for="blockGroup in groupedRooms"
        :key="blockGroup.name"
        class="space-y-4"
      >
        <!-- Block Header Title -->
        <div class="flex items-center justify-between pb-2 border-b border-slate-200">
          <div class="flex items-center gap-2">
            <span class="w-2.5 h-6 rounded-full bg-primary inline-block"></span>
            <h2 class="text-base sm:text-lg font-bold text-slate-800">{{ blockGroup.name }}</h2>
            <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-slate-100 text-slate-600">
              {{ blockGroup.totalRooms }} phòng
            </span>
          </div>
          <button
            v-if="selectedBlock !== blockGroup.name"
            class="text-xs text-primary font-semibold hover:underline flex items-center gap-1 cursor-pointer"
            @click="selectBlock(blockGroup.name)"
          >
            <span>Chỉ xem {{ blockGroup.name }}</span>
            <v-icon icon="mdi-chevron-right" size="14" />
          </button>
        </div>

        <!-- Floors in Block -->
        <div
          v-for="floorGroup in blockGroup.floors"
          :key="floorGroup.floor"
          class="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-xs space-y-3"
        >
          <!-- Floor Header -->
          <div class="flex items-center justify-between border-b border-slate-100 pb-2">
            <div class="flex items-center gap-2">
              <v-icon icon="mdi-stairs" color="primary" size="18" />
              <span class="font-bold text-slate-800 text-xs sm:text-sm">Tầng {{ floorGroup.floor }}</span>
              <span class="text-xs text-slate-400">({{ floorGroup.rooms.length }} phòng)</span>
            </div>
          </div>

          <!-- Rooms Grid for this floor -->
          <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 2xl:grid-cols-7 gap-2.5 sm:gap-3.5">
            <div
              v-for="room in floorGroup.rooms"
              :key="room.id"
              class="rounded-xl p-3 sm:p-3.5 border shadow-2xs hover:shadow-md transition-all cursor-pointer flex flex-col justify-between h-32 relative overflow-hidden active:scale-[0.98]"
              :class="getRoomColorClass(room)"
              @click="openRoomDetail(room)"
            >
              <!-- Top Status Bar Indicator -->
              <div class="flex items-center justify-between">
                <span class="font-black text-sm sm:text-base text-slate-900 tracking-tight">
                  {{ room.room_number }}
                </span>
                <span
                  class="w-2.5 h-2.5 rounded-full shadow-xs flex-shrink-0"
                  :class="getBadgeIndicatorClass(room)"
                  :title="room.equipment_status"
                />
              </div>

              <!-- Center Room Name / Floor -->
              <div class="my-auto py-1">
                <p class="text-[11px] sm:text-xs font-semibold text-slate-700 truncate" :title="room.name">
                  {{ room.name || `Phòng ${room.room_number}` }}
                </p>
                <p class="text-[10px] sm:text-[11px] text-slate-400 truncate mt-0.5">
                  Tầng {{ room.floor || '1' }} • {{ room.block || 'Chính' }}
                </p>
              </div>

              <!-- Bottom Devices Stats -->
              <div class="pt-1.5 border-t border-slate-100 flex items-center justify-between text-[10px] sm:text-[11px]">
                <span class="text-slate-500 font-medium">
                  {{ room.stats?.total_equipments || 0 }} TB
                </span>

                <div class="flex items-center gap-1">
                  <span
                    v-if="room.stats?.broken_count > 0"
                    class="px-1.5 py-0.2 rounded bg-rose-100 text-rose-700 font-bold"
                    title="Thiết bị hỏng"
                  >
                    {{ room.stats.broken_count }}!
                  </span>
                  <span
                    v-if="room.stats?.missing_count > 0"
                    class="px-1.5 py-0.2 rounded bg-amber-100 text-amber-800 font-bold"
                    title="Thiết bị thiếu định mức"
                  >
                    -{{ room.stats.missing_count }}
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Empty state -->
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

    <!-- Room Equipment Detail Modal -->
    <AppModal
      v-model="roomDetailModalOpen"
      :title="`Chi tiết Phòng ${selectedRoomDetail?.room_number || ''}`"
      :subtitle="selectedRoomDetail?.room_type ? `Loại phòng: ${selectedRoomDetail.room_type}` : 'Đối soát định mức trang thiết bị tiêu chuẩn'"
      max-width="640"
    >
      <div v-if="roomDetailLoading" class="text-center py-12">
        <v-progress-circular indeterminate color="primary" size="36" />
        <p class="text-xs text-slate-400 mt-3 font-medium">Đang tải đối soát thiết bị...</p>
      </div>

      <div v-else-if="selectedRoomDetail" class="space-y-6">
        <!-- Compliance Status Banner -->
        <div
          class="p-4 rounded-xl border flex items-center justify-between text-xs"
          :class="selectedRoomDetail.compliance_status === 'compliant' ? 'bg-emerald-50 border-emerald-200 text-emerald-800' : 'bg-rose-50 border-rose-200 text-rose-800'"
        >
          <div class="flex items-center gap-2.5">
            <v-icon
              :icon="selectedRoomDetail.compliance_status === 'compliant' ? 'mdi-check-decagram' : 'mdi-alert-decagram'"
              size="22"
              :color="selectedRoomDetail.compliance_status === 'compliant' ? 'success' : 'error'"
            />
            <div>
              <p class="font-bold">
                {{ selectedRoomDetail.compliance_status === 'compliant' ? 'Đạt chuẩn 100% định mức phòng' : 'Chưa đạt định mức tiêu chuẩn phòng' }}
              </p>
              <p class="text-[11px] opacity-80 mt-0.5">
                {{ selectedRoomDetail.compliance_status === 'compliant' ? 'Toàn bộ thiết bị đều đầy đủ và hoạt động tốt.' : 'Phát hiện thiếu hụt thiết bị so với cấu hình chuẩn của loại phòng này.' }}
              </p>
            </div>
          </div>
        </div>

        <!-- Missing Equipments Alert (if any) -->
        <div v-if="selectedRoomDetail.missing_equipments && selectedRoomDetail.missing_equipments.length" class="bg-amber-50 p-4 rounded-xl border border-amber-200">
          <h4 class="text-xs font-bold text-amber-900 uppercase tracking-wider mb-2 flex items-center gap-1.5">
            <v-icon icon="mdi-alert-circle" size="16" color="warning" />
            <span>Thiết bị còn thiếu theo định mức:</span>
          </h4>
          <ul class="space-y-1 text-xs text-amber-800 list-disc list-inside">
            <li v-for="(mis, i) in selectedRoomDetail.missing_equipments" :key="i">
              <strong>{{ mis.category_name || mis.name }}</strong>: Thiếu {{ mis.missing_count || 1 }} chiếc (Tiêu chuẩn: {{ mis.standard_quantity || 1 }}, Thực tế: {{ mis.actual_quantity || 0 }})
            </li>
          </ul>
        </div>

        <!-- Actual Equipments in Room -->
        <div>
          <h4 class="text-xs font-bold text-slate-800 uppercase tracking-wider mb-3">
            Thiết bị thực tế trong phòng
          </h4>

          <div v-if="selectedRoomDetail.categories && selectedRoomDetail.categories.length" class="space-y-4">
            <div
              v-for="cat in selectedRoomDetail.categories"
              :key="cat.category_code || cat.category_name"
              class="border border-slate-200 rounded-xl p-3.5"
            >
              <p class="text-xs font-bold text-slate-700 mb-2 flex items-center justify-between">
                <span>{{ cat.category_name }}</span>
                <span class="text-[11px] text-slate-400 font-normal">{{ cat.items?.length || 0 }} thiết bị</span>
              </p>

              <div class="space-y-2">
                <div
                  v-for="item in cat.items"
                  :key="item.id"
                  class="flex items-center justify-between gap-3 p-2.5 bg-slate-50 hover:bg-slate-100/80 transition-colors rounded-xl text-xs border border-slate-100"
                >
                  <!-- Equipment Thumbnail -->
                  <div
                    class="relative w-12 h-12 rounded-lg overflow-hidden shrink-0 border border-slate-200/80 bg-white flex items-center justify-center shadow-2xs group cursor-pointer"
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

                    <!-- Hover zoom overlay if has image -->
                    <div
                      v-if="item.photo_url || item.photos?.[0]?.url"
                      class="absolute inset-0 bg-slate-900/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center text-white"
                    >
                      <v-icon icon="mdi-magnify-plus" size="16" />
                    </div>
                  </div>

                  <!-- Equipment Info -->
                  <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-1.5">
                      <router-link
                        :to="`/equipments/${item.id}`"
                        class="font-bold text-slate-800 hover:text-primary transition-colors truncate"
                        title="Xem chi tiết thiết bị"
                      >
                        {{ item.model_name || 'Thiết bị' }}
                      </router-link>
                      <span v-if="item.photos?.length > 1" class="text-[10px] bg-slate-200/70 text-slate-600 px-1.5 py-0.2 rounded-full font-medium">
                        {{ item.photos.length }} ảnh
                      </span>
                    </div>
                    <p class="text-[11px] font-mono text-blue-600 font-medium truncate mt-0.5">
                      {{ item.serial_number || 'Chưa gắn Serial' }}
                    </p>
                    <p v-if="item.position_note" class="text-[10px] text-slate-400 truncate mt-0.5">
                      Vị trí: {{ item.position_note }}
                    </p>
                  </div>

                  <!-- Right: Status & Link -->
                  <div class="flex items-center gap-1.5 shrink-0">
                    <AppStatusBadge :status="item.status" />
                    <button
                      type="button"
                      class="p-1 rounded-lg text-slate-400 hover:text-primary hover:bg-white transition cursor-pointer"
                      title="Xem trang chi tiết thiết bị"
                      @click="router.push(`/equipments/${item.id}`)"
                    >
                      <v-icon icon="mdi-chevron-right" size="18" />
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div v-else class="text-center py-6 text-slate-400 text-xs">
            Phòng này chưa có bất kỳ thiết bị nào được gán.
          </div>
        </div>
      </div>

      <template #footer>
        <v-btn
          variant="tonal"
          color="secondary"
          @click="roomDetailModalOpen = false"
        >
          Đóng
        </v-btn>
        <AppButton
          v-if="$can('assign', 'Equipment')"
          color="primary"
          icon="mdi-plus"
          @click="router.push(`/equipments?action=create&room_id=${selectedRoomDetail?.room_id}`)"
        >
          Gán thêm thiết bị
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
  </DefaultLayout>
</template>
