<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { dashboardService } from '../../services/dashboardService'
import { equipmentService } from '@/modules/equipment/services/equipmentService'
import { useRealtimeStore } from '@/core/stores/realtimeStore'
import DefaultLayout from '@/core/layouts/DefaultLayout.vue'
import AppPageHeader from '@/core/components/AppPageHeader.vue'
import AppStatCard from '@/core/components/AppStatCard.vue'
import AppButton from '@/core/components/AppButton.vue'
import AppModal from '@/core/components/AppModal.vue'
import AppStatusBadge from '@/core/components/AppStatusBadge.vue'
import EquipmentOverviewChart from '../../components/EquipmentOverviewChart.vue'
import RoomAlertList from '../../components/RoomAlertList.vue'
import { toast } from '@/core/helpers/toast'
import { HotelBlockOrder } from '@/core/enums'
import { resolveMediaUrl } from '@/core/helpers/formatters'

const router = useRouter()
const realtimeStore = useRealtimeStore()
const loading = ref(true)

const selectedBlock = ref('') // '' = Tất cả block

const stats = ref({
  total_equipments: 0,
  by_status: {},
  by_category: [],
  rooms_with_issues_count: 0,
})
const mapRooms = ref([])

// Thứ tự block chuẩn đồng bộ từ Enum tập trung
const hotelBlockOrder = HotelBlockOrder

// Modal xem chi tiết thiết bị phòng ngay tại Dashboard
const roomDetailModalOpen = ref(false)
const roomDetailLoading = ref(false)
const selectedRoomDetail = ref(null)
const selectedRoomSummary = ref(null)
let dashboardRequestId = 0
let roomDetailRequestId = 0

// Lightbox xem ảnh lớn
const lightboxOpen = ref(false)
const lightboxImage = ref('')
const lightboxTitle = ref('')

async function loadDashboardData(silent = false) {
  if (!silent) {
    loading.value = true
  }
  const requestId = ++dashboardRequestId
  try {
    const [statsResult, roomsResult] = await Promise.allSettled([
      dashboardService.getStats(selectedBlock.value),
      mapRooms.value.length > 0 ? Promise.resolve(mapRooms.value) : dashboardService.getMapOverview(),
    ])

    if (requestId === dashboardRequestId) {
      if (statsResult.status === 'fulfilled' && statsResult.value) {
        const data = statsResult.value
        stats.value = {
          total_equipments: data.total_equipments ?? data.total ?? 0,
          by_status: data.by_status || data.summary || {},
          by_category: data.by_category || [],
          rooms_with_issues_count: data.rooms_with_issues_count || 0,
          by_block: data.by_block || {},
          ...data,
        }
      } else if (statsResult.status === 'rejected') {
        console.error('Failed to load dashboard stats:', statsResult.reason)
      }

      if (roomsResult.status === 'fulfilled' && roomsResult.value) {
        mapRooms.value = Array.isArray(roomsResult.value) ? roomsResult.value : (roomsResult.value?.data || [])
      } else if (roomsResult.status === 'rejected') {
        console.error('Failed to load dashboard map rooms:', roomsResult.reason)
      }

      // Chỉ báo lỗi nếu cả 2 request cùng thất bại
      if (statsResult.status === 'rejected' && roomsResult.status === 'rejected') {
        const msg = statsResult.reason?.response?.data?.message || roomsResult.reason?.response?.data?.message
        toast.error(msg || 'Không thể tải dữ liệu bảng điều khiển.')
      }
    }
  } catch (error) {
    if (requestId === dashboardRequestId) {
      console.error('Unexpected error loading dashboard:', error)
    }
  } finally {
    if (!silent && requestId === dashboardRequestId) {
      loading.value = false
    }
  }
}

// Lọc các phòng theo block đang chọn
const filteredRooms = computed(() => {
  if (!selectedBlock.value) return mapRooms.value
  return mapRooms.value.filter(r => r.block === selectedBlock.value)
})

// Đếm số lượng phòng trong từng Block để hiển thị trên Pills
const blockCounts = computed(() => {
  if (mapRooms.value.length > 0) {
    const counts = { total: mapRooms.value.length }
    mapRooms.value.forEach(r => {
      const b = r.block || 'Khu vực chung'
      counts[b] = (counts[b] || 0) + 1
    })
    return counts
  }
  if (stats.value?.by_block) {
    return {
      total: stats.value.total_equipments || stats.value.all_total || 0,
      ...stats.value.by_block,
    }
  }
  return { total: 0 }
})

// Danh sách các block thực tế có trong hệ thống
const existingBlocks = computed(() => {
  const presentSet = new Set(
    mapRooms.value.length > 0
      ? mapRooms.value.map(r => r.block).filter(Boolean)
      : Object.keys(stats.value?.by_block || {})
  )
  const ordered = hotelBlockOrder.filter(b => presentSet.has(b))
  Array.from(presentSet).forEach(b => {
    if (!ordered.includes(b)) ordered.push(b)
  })
  return ordered.length > 0 ? ordered : hotelBlockOrder
})

function selectBlock(b) {
  selectedBlock.value = b
  loadDashboardData(false)
}

// Điều hướng nhanh khi click vào các thẻ KPI
function navigateToEquipments(statusFilter = '') {
  const query = {}
  if (statusFilter) query.status = statusFilter
  if (selectedBlock.value) query.block = selectedBlock.value
  router.push({ path: '/equipments', query })
}

// Mở modal xem chi tiết thiết bị của phòng
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
      console.error('Failed to load room equipment detail:', error)
      toast.error(error.response?.data?.message || 'Không thể tải chi tiết thiết bị của phòng.')
    }
  } finally {
    if (requestId === roomDetailRequestId) {
      roomDetailLoading.value = false
    }
  }
}

function openLightbox(item) {
  const url = item.photo_url || item.photos?.[0]?.url
  if (!url) return
  lightboxImage.value = resolveMediaUrl(url)
  lightboxTitle.value = `${item.model_name || 'Thiết bị'} (${item.serial_number || 'N/A'})`
  lightboxOpen.value = true
}

const categoryIconMap = {
  tv: 'mdi-television',
  remote: 'mdi-remote',
  box: 'mdi-set-top-box',
  wifi: 'mdi-wifi',
  ac: 'mdi-air-conditioner',
  fridge: 'mdi-fridge-outline',
  security: 'mdi-safe',
  appliance: 'mdi-hair-dryer',
}

function getCategoryIcon(code) {
  return categoryIconMap[code] || 'mdi-tag-outline'
}

onMounted(() => {
  loadDashboardData()
})

// Tự động làm mới số liệu Dashboard Real-time khi nhận sự kiện WebSocket
watch(
  () => realtimeStore.lastEvent,
  (event) => {
    if (!event) return

    // Cập nhật số liệu KPI tại chỗ (in-place) ngay lập tức
    if (event.type === 'status_changed' && event.equipment) {
      const oldSt = event.equipment.old_status
      const newSt = event.equipment.new_status
      if (oldSt && stats.value.by_status && stats.value.by_status[oldSt] !== undefined) {
        stats.value.by_status[oldSt] = Math.max(0, (stats.value.by_status[oldSt] || 0) - 1)
      }
      if (newSt && stats.value.by_status) {
        stats.value.by_status[newSt] = (stats.value.by_status[newSt] || 0) + 1
      }
    } else if (event.type === 'created') {
      stats.value.total_equipments += 1
      const st = event.equipment?.status || 'in_stock'
      if (stats.value.by_status) {
        stats.value.by_status[st] = (stats.value.by_status[st] || 0) + 1
      }
    } else if (event.type === 'deleted') {
      stats.value.total_equipments = Math.max(0, stats.value.total_equipments - 1)
    } else if (event.type === 'broken_reported') {
      if (stats.value.by_status) {
        stats.value.by_status.broken = (stats.value.by_status.broken || 0) + 1
      }
    }

    // Tự động đồng bộ số liệu chuẩn xác ngầm từ máy chủ (không bật vòng quay loading)
    loadDashboardData(true)
  }
)
</script>

<template>
  <DefaultLayout>
    <!-- Header with Quick Actions & Live Indicator -->
    <AppPageHeader
      title="Bảng điều khiển Tổng quan"
      subtitle="Theo dõi tình trạng trang thiết bị, sơ đồ mặt bằng và đối soát phòng theo thời gian thực"
    >
      <template #actions>
        <!-- Realtime WebSocket Live Badge -->
        <div class="hidden sm:inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-emerald-50 text-emerald-700 text-xs font-bold border border-emerald-200">
          <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse" />
          <span>Realtime Live</span>
        </div>

        <AppButton
          v-if="$can('read', 'Equipment')"
          variant="tonal"
          color="secondary"
          icon="mdi-format-list-bulleted"
          @click="router.push('/equipments')"
        >
          Danh sách thiết bị
        </AppButton>

        <AppButton
          v-if="$can('read', 'Equipment')"
          variant="tonal"
          color="secondary"
          icon="mdi-floor-plan"
          @click="router.push('/equipments/map-view')"
        >
          Sơ đồ Mặt bằng
        </AppButton>

        <AppButton
          v-if="$can('create', 'Equipment')"
          color="primary"
          icon="mdi-plus"
          @click="router.push('/equipments?action=create')"
        >
          Thêm thiết bị mới
        </AppButton>
      </template>
    </AppPageHeader>

    <!-- Modern Block Quick-Filter Pills (Đồng bộ với RoomListView & EquipmentMapView) -->
    <div class="bg-white border border-slate-200/80 rounded-2xl shadow-xs overflow-hidden mb-5 sm:mb-6">
      <div class="px-4 py-3 bg-slate-50/60 border-b border-slate-100 flex items-center justify-between gap-2">
        <span class="text-xs font-bold text-slate-500 uppercase tracking-wider flex items-center gap-1.5">
          <v-icon icon="mdi-domain" size="15" color="primary" />
          <span>Lọc Báo Cáo Theo Phân Khu & Tòa Nhà (Block):</span>
        </span>
        <button
          v-if="selectedBlock"
          type="button"
          class="text-xs text-primary font-semibold hover:underline flex items-center gap-1 cursor-pointer"
          @click="selectBlock('')"
        >
          <v-icon icon="mdi-eye-outline" size="14" />
          <span>Xem toàn bộ khách sạn</span>
        </button>
      </div>

      <!-- Horizontal Pills Scroll -->
      <div class="p-3 flex items-center gap-2 overflow-x-auto scrollbar-thin">
        <button
          type="button"
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
          type="button"
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

    <!-- Top 4 Actionable KPI Stat Cards (Clickable to Filter!) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3.5 sm:gap-5 mb-5 sm:mb-8">
      <!-- 1. Total Equipments -->
      <div
        class="cursor-pointer group transform transition-transform duration-150 hover:-translate-y-0.5"
        title="Nhấp để xem danh sách toàn bộ thiết bị"
        @click="navigateToEquipments('')"
      >
        <AppStatCard
          title="Tổng số thiết bị"
          :value="stats.total_equipments || 0"
          :subtitle="selectedBlock ? `Khu vực: ${selectedBlock}` : 'Quản lý toàn bộ khách sạn'"
          icon="mdi-devices"
          color="primary"
          :loading="loading"
          trend="Xem danh sách →"
          trend-type="neutral"
        />
      </div>

      <!-- 2. Good Equipments -->
      <div
        class="cursor-pointer group transform transition-transform duration-150 hover:-translate-y-0.5"
        title="Nhấp để lọc thiết bị đang hoạt động tốt"
        @click="navigateToEquipments('good')"
      >
        <AppStatCard
          title="Đang hoạt động tốt"
          :value="stats.by_status?.good || 0"
          subtitle="Sẵn sàng phục vụ khách"
          icon="mdi-check-circle-outline"
          color="success"
          :loading="loading"
          :trend="`Tỷ lệ: ${stats.total_equipments > 0 ? Math.round(((stats.by_status?.good || 0) / stats.total_equipments) * 100) : 100}%`"
          trend-type="up"
        />
      </div>

      <!-- 3. Broken / Maintenance Equipments -->
      <div
        class="cursor-pointer group transform transition-transform duration-150 hover:-translate-y-0.5"
        title="Nhấp để lọc thiết bị hỏng hóc hoặc đang bảo dưỡng"
        @click="navigateToEquipments('broken')"
      >
        <AppStatCard
          title="Báo hỏng / Cần bảo trì"
          :value="(stats.by_status?.broken || 0) + (stats.by_status?.maintenance || 0)"
          :subtitle="stats.rooms_with_issues_count > 0 ? `${stats.rooms_with_issues_count} phòng có sự cố` : 'Cần kỹ thuật xử lý'"
          icon="mdi-wrench-alert"
          color="error"
          :loading="loading"
          trend="Cần xử lý gấp →"
          trend-type="down"
        />
      </div>

      <!-- 4. Stock Equipments -->
      <div
        class="cursor-pointer group transform transition-transform duration-150 hover:-translate-y-0.5"
        title="Nhấp để vào kho quản lý thiết bị dự phòng"
        @click="navigateToEquipments('in_stock')"
      >
        <AppStatCard
          title="Tồn trong kho"
          :value="stats.by_status?.in_stock || 0"
          subtitle="Sẵn sàng cấp bù phòng"
          icon="mdi-package-variant-closed"
          color="info"
          :loading="loading"
          trend="Kho thiết bị tổng"
          trend-type="neutral"
        />
      </div>
    </div>

    <!-- Center 2 Columns: Status Distribution Chart & Smart Room Alert List -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 sm:gap-6 mb-5 sm:mb-8">
      <EquipmentOverviewChart
        :stats="stats"
        :selected-block="selectedBlock"
      />
      <RoomAlertList
        :rooms="filteredRooms"
        :loading="loading"
        @select-room="openRoomDetail"
      />
    </div>

    <!-- Category Breakdown Cards (Enhanced with Status Mini Matrix) -->
    <div v-if="stats.by_category && stats.by_category.length" class="mt-6 sm:mt-8">
      <div class="flex items-center justify-between mb-3 sm:mb-4">
        <div>
          <h3 class="text-sm sm:text-base font-bold text-slate-800">
            Số lượng Thiết bị theo Nhóm Danh mục
          </h3>
          <p class="text-[11px] sm:text-xs text-slate-400">
            Tivi, Điều khiển, TV Box, Wifi, Điều hòa, Tủ lạnh... (Nhấp vào nhóm để xem danh sách chi tiết)
          </p>
        </div>
      </div>

      <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-4 xl:grid-cols-8 gap-3 sm:gap-4">
        <div
          v-for="cat in stats.by_category"
          :key="cat.category_id || cat.name"
          class="bg-white p-3.5 rounded-2xl border border-slate-200/80 shadow-xs hover:border-primary/60 hover:shadow-sm transition-all text-center cursor-pointer group"
          :title="`Xem chi tiết nhóm ${cat.category_name || cat.name}`"
          @click="router.push({ path: '/equipments', query: { category_id: cat.category_id, ...(selectedBlock ? { block: selectedBlock } : {}) } })"
        >
          <div class="w-10 h-10 rounded-xl bg-slate-50 text-slate-700 flex items-center justify-center mx-auto mb-2 font-bold text-xs border border-slate-100 group-hover:bg-blue-50 group-hover:text-primary transition-colors">
            <v-icon :icon="cat.icon || getCategoryIcon(cat.category_code)" size="20" color="primary" />
          </div>

          <p class="text-xs font-bold text-slate-700 truncate mb-1 group-hover:text-primary transition-colors" :title="cat.category_name || cat.name">
            {{ cat.category_name || cat.name }}
          </p>

          <p class="text-lg font-extrabold text-slate-800 tracking-tight">
            {{ cat.total ?? (cat.count || 0) }}
          </p>

          <!-- Mini Status badges for each category -->
          <div v-if="cat.broken > 0 || cat.in_stock > 0" class="mt-2 pt-2 border-t border-slate-100 flex items-center justify-center gap-1.5 text-[10px]">
            <span v-if="cat.broken > 0" class="px-1.5 py-0.2 rounded font-bold bg-rose-50 text-rose-600">
              {{ cat.broken }} hỏng
            </span>
            <span v-if="cat.in_stock > 0" class="px-1.5 py-0.2 rounded font-medium bg-sky-50 text-sky-600">
              {{ cat.in_stock }} kho
            </span>
          </div>
        </div>
      </div>
    </div>

    <!-- Room Detail Modal (AppModal) right on Dashboard -->
    <AppModal
      v-model="roomDetailModalOpen"
      :title="`Danh sách Thiết bị — Phòng ${selectedRoomDetail?.room_number || selectedRoomSummary?.room_number || ''}`"
      :subtitle="`${selectedRoomDetail?.block || selectedRoomSummary?.block || ''} • Tầng ${selectedRoomDetail?.floor || selectedRoomSummary?.floor || ''} • Hạng: ${selectedRoomDetail?.room_type || selectedRoomSummary?.room_type || 'Tiêu chuẩn'}`"
      max-width="800"
    >
      <div v-if="roomDetailLoading" class="mb-4 rounded-xl border border-blue-200 bg-blue-50 p-3.5">
        <div class="flex items-center gap-2 text-xs font-semibold text-blue-800">
          <v-progress-circular indeterminate color="primary" size="18" />
          <span>Đang tải danh sách thiết bị đầy đủ...</span>
        </div>
        <div v-if="selectedRoomSummary" class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-600">
          <span>{{ selectedRoomSummary.block }} • Tầng {{ selectedRoomSummary.floor }}</span>
          <span>{{ selectedRoomSummary.stats?.total_equipments ?? 0 }} thiết bị</span>
          <span v-if="selectedRoomSummary.stats?.missing_count">Thiếu {{ selectedRoomSummary.stats.missing_count }}</span>
          <span v-if="selectedRoomSummary.stats?.broken_count">Hỏng {{ selectedRoomSummary.stats.broken_count }}</span>
        </div>
      </div>

      <div v-if="selectedRoomDetail" class="space-y-4">
        <!-- Room Status Summary Banner -->
        <div class="flex flex-wrap items-center justify-between gap-3 p-3.5 bg-slate-50 border border-slate-200/80 rounded-xl">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-primary text-white font-black text-sm flex items-center justify-center shadow-xs">
              {{ selectedRoomDetail.room_number }}
            </div>
            <div>
              <p class="text-xs font-bold text-slate-800">
                {{ selectedRoomDetail.name || `Phòng ${selectedRoomDetail.room_number}` }}
              </p>
              <p class="text-[11px] text-slate-500">
                {{ selectedRoomDetail.block }} • Tầng {{ selectedRoomDetail.floor }} • {{ selectedRoomDetail.room_type }}
              </p>
            </div>
          </div>

          <div class="flex items-center gap-2 text-xs">
            <span class="text-slate-500 font-medium">Tổng thiết bị:</span>
            <span class="px-2 py-0.5 rounded-full font-bold bg-slate-200 text-slate-800">
              {{ selectedRoomDetail.total_equipments || 0 }}
            </span>
          </div>
        </div>

        <!-- Categorized Equipment List in Room -->
        <div class="space-y-3 max-h-[50vh] overflow-y-auto pr-1">
          <div
            v-if="selectedRoomDetail.categories && selectedRoomDetail.categories.length > 0"
            class="space-y-3"
          >
            <div
              v-for="cat in selectedRoomDetail.categories"
              :key="cat.category_id"
              class="border border-slate-200/80 rounded-xl p-3 bg-white shadow-2xs"
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
                  <!-- Equipment Thumbnail with lightbox zoom -->
                  <div
                    class="relative w-11 h-11 rounded-lg overflow-hidden shrink-0 border border-slate-200/80 bg-white flex items-center justify-center shadow-2xs group cursor-pointer"
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
                      <v-icon :icon="cat.category_icon || getCategoryIcon(cat.category_code)" size="20" />
                    </div>

                    <div
                      v-if="item.photo_url || item.photos?.[0]?.url"
                      class="absolute inset-0 bg-slate-900/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center text-white"
                    >
                      <v-icon icon="mdi-magnify-plus" size="14" />
                    </div>
                  </div>

                  <!-- Equipment Info -->
                  <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-1.5">
                      <router-link
                        :to="`/equipments/${item.id}`"
                        class="font-bold text-slate-800 hover:text-primary transition-colors truncate"
                        title="Xem trang chi tiết thiết bị"
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

                  <!-- Right: Status Badge & Link -->
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

          <div v-else class="text-center py-8 text-slate-400 text-xs">
            <v-icon icon="mdi-package-variant-closed" size="36" class="text-slate-300 mb-2 block mx-auto" />
            <p class="font-bold text-slate-600">Phòng này chưa có bất kỳ thiết bị nào được gán.</p>
            <p class="text-[11px] text-slate-400 mt-0.5">Bạn có thể bấm "Gán thêm thiết bị" bên dưới để trang bị cho phòng.</p>
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
          v-if="selectedRoomDetail?.room_id"
          variant="tonal"
          color="primary"
          icon="mdi-floor-plan"
          @click="router.push({ path: '/equipments/map-view', query: { block: selectedRoomDetail.block, floor: selectedRoomDetail.floor } })"
        >
          Xem trên Sơ đồ Map
        </AppButton>
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
          class="absolute top-3 right-3 z-10 w-8 h-8 rounded-full bg-slate-800/80 text-white flex items-center justify-center hover:bg-slate-700 transition cursor-pointer"
          @click="lightboxOpen = false"
        >
          <v-icon icon="mdi-close" size="18" />
        </button>
        <p v-if="lightboxTitle" class="text-xs font-bold text-slate-200 mb-3 px-1 truncate">
          {{ lightboxTitle }}
        </p>
        <div class="flex items-center justify-center max-h-[75vh] overflow-hidden rounded-xl bg-black/40">
          <img
            :src="lightboxImage"
            :alt="lightboxTitle"
            class="max-w-full max-h-[75vh] object-contain rounded-xl"
          />
        </div>
      </div>
    </v-dialog>
  </DefaultLayout>
</template>
