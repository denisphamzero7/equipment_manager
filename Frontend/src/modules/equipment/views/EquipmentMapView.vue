<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import { equipmentService } from '../services/equipmentService'
import DefaultLayout from '@/core/layouts/DefaultLayout.vue'
import AppPageHeader from '@/core/components/AppPageHeader.vue'
import AppButton from '@/core/components/AppButton.vue'
import AppModal from '@/core/components/AppModal.vue'
import AppStatusBadge from '@/core/components/AppStatusBadge.vue'

const router = useRouter()
const loading = ref(true)
const blocks = ref([])
const floors = ref([])
const selectedBlock = ref('')
const selectedFloor = ref('')
const rooms = ref([])

// Detail room modal
const selectedRoomDetail = ref(null)
const roomDetailModalOpen = ref(false)
const roomDetailLoading = ref(false)

onMounted(() => {
  // Load filters and map grid concurrently for fastest perceived load time
  Promise.all([
    loadFilterOptions(),
    loadMapViewData(),
  ])
})

async function loadFilterOptions() {
  try {
    const [blocksData, floorsData] = await Promise.all([
      equipmentService.getBlocks().catch(() => []),
      equipmentService.getFloors().catch(() => []),
    ])
    blocks.value = blocksData || []
    floors.value = floorsData || []
  } catch (e) {
    console.error('Failed to load blocks/floors:', e)
  }
}

async function loadMapViewData() {
  loading.value = true
  try {
    const params = {}
    if (selectedBlock.value) params.block = selectedBlock.value
    if (selectedFloor.value) params.floor = selectedFloor.value

    const data = await equipmentService.getMapView(params)
    rooms.value = data || []
  } finally {
    loading.value = false
  }
}

async function handleBlockChange(block) {
  selectedBlock.value = block
  // Reload floors for this block
  floors.value = await equipmentService.getFloors(block).catch(() => [])
  await loadMapViewData()
}

async function handleFloorChange(floor) {
  selectedFloor.value = floor
  await loadMapViewData()
}

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

const colorLegends = [
  { label: 'Hoạt động tốt & Đầy đủ', color: 'bg-emerald-500', border: 'border-emerald-200', text: 'text-emerald-700' },
  { label: 'Đang bảo trì thiết bị', color: 'bg-amber-500', border: 'border-amber-200', text: 'text-amber-700' },
  { label: 'Có thiết bị hỏng / Thiếu', color: 'bg-rose-500', border: 'border-rose-200', text: 'text-rose-700' },
  { label: 'Chưa cấu hình thiết bị', color: 'bg-slate-400', border: 'border-slate-200', text: 'text-slate-600' },
]

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
          @click="loadMapViewData"
        >
          Tải lại sơ đồ
        </AppButton>
      </template>
    </AppPageHeader>

    <!-- Top Toolbar: Filters & Legends -->
    <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-xs mb-6 space-y-4">
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <!-- Block & Floor selectors -->
        <div class="flex items-center gap-3 flex-wrap">
          <div class="w-48">
            <v-select
              v-model="selectedBlock"
              :items="[{ value: '', title: 'Tất cả Block' }, ...blocks.map(b => ({ value: b, title: b }))]"
              item-title="title"
              item-value="value"
              density="compact"
              hide-details
              prepend-inner-icon="mdi-office-building"
              placeholder="Chọn Block"
              @update:model-value="handleBlockChange"
            />
          </div>

          <div class="w-40">
            <v-select
              v-model="selectedFloor"
              :items="[{ value: '', title: 'Tất cả Tầng' }, ...floors.map(f => ({ value: f, title: `Tầng ${f}` }))]"
              item-title="title"
              item-value="value"
              density="compact"
              hide-details
              prepend-inner-icon="mdi-layers-outline"
              placeholder="Chọn Tầng"
              @update:model-value="handleFloorChange"
            />
          </div>

          <div class="text-xs text-slate-500 font-medium">
            Tìm thấy <strong>{{ rooms.length }}</strong> phòng
          </div>
        </div>

        <!-- Color Legend Badges -->
        <div class="flex items-center gap-3 flex-wrap text-xs">
          <div v-for="leg in colorLegends" :key="leg.label" class="flex items-center gap-1.5">
            <span class="w-2.5 h-2.5 rounded-full" :class="leg.color" />
            <span class="text-slate-600 font-medium">{{ leg.label }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Map View Room Grid -->
    <div v-if="loading" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4">
      <div v-for="n in 12" :key="n" class="h-32 bg-slate-100 rounded-2xl animate-pulse" />
    </div>

    <div v-else-if="rooms.length > 0" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4">
      <div
        v-for="room in rooms"
        :key="room.id"
        class="rounded-2xl p-4 border shadow-xs hover:shadow-md transition-all cursor-pointer flex flex-col justify-between h-36 relative overflow-hidden"
        :class="getRoomColorClass(room)"
        @click="openRoomDetail(room)"
      >
        <!-- Top Status Bar Indicator -->
        <div class="flex items-center justify-between">
          <span class="font-extrabold text-base text-slate-800">
            {{ room.room_number }}
          </span>
          <span
            class="w-3 h-3 rounded-full shadow-xs"
            :class="getBadgeIndicatorClass(room)"
            :title="room.equipment_status"
          />
        </div>

        <!-- Center Room Type / Floor -->
        <div class="my-auto">
          <p class="text-xs font-semibold text-slate-700 truncate" :title="room.name">
            {{ room.name || `Phòng ${room.room_number}` }}
          </p>
          <p class="text-[11px] text-slate-400">
            Tầng {{ room.floor || '1' }} • {{ room.block || 'Chính' }}
          </p>
        </div>

        <!-- Bottom Devices Stats -->
        <div class="pt-2 border-t border-slate-100/80 flex items-center justify-between text-[11px]">
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

    <!-- Empty state -->
    <div v-else class="text-center py-16 bg-white rounded-2xl border border-slate-200">
      <v-icon icon="mdi-map-marker-off-outline" size="56" class="text-slate-300 mb-2 block mx-auto" />
      <h3 class="text-base font-bold text-slate-700">Không tìm thấy phòng nào</h3>
      <p class="text-xs text-slate-400 mt-1">Vui lòng chọn Block hoặc Tầng khác trong bộ lọc.</p>
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
                  class="flex items-center justify-between p-2.5 bg-slate-50 rounded-lg text-xs"
                >
                  <div>
                    <p class="font-bold text-slate-800">{{ item.model_name || 'Thiết bị' }}</p>
                    <p class="text-[11px] font-mono text-blue-600 font-medium">
                      {{ item.serial_number || 'Chưa gắn Serial' }}
                    </p>
                    <p v-if="item.position_note" class="text-[10px] text-slate-400">
                      Vị trí: {{ item.position_note }}
                    </p>
                  </div>
                  <AppStatusBadge :status="item.status" />
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
  </DefaultLayout>
</template>
