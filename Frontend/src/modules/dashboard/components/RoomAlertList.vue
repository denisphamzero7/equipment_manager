<script setup>
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'

const props = defineProps({
  rooms: {
    type: Array,
    default: () => [],
  },
  loading: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['select-room'])
const router = useRouter()

const activeTab = ref('broken') // 'broken' | 'missing' | 'all'
const isExpanded = ref(false)

// Categorize rooms
const brokenRooms = computed(() => {
  return props.rooms.filter(r => (r.stats?.broken_count > 0) || r.equipment_status === 'broken' || (r.badge_color === 'red' && (r.stats?.broken_count > 0 || !r.stats)))
})

const missingRooms = computed(() => {
  return props.rooms.filter(r => (r.stats?.missing_count > 0) || r.equipment_status === 'missing' || (r.badge_color === 'yellow' && r.stats?.missing_count > 0))
})

const allAlertRooms = computed(() => {
  return props.rooms.filter(r => 
    r.badge_color === 'red' || 
    r.badge_color === 'yellow' || 
    (r.stats && (r.stats.broken_count > 0 || r.stats.missing_count > 0))
  )
})

// Auto-select tab if activeTab is empty
const currentList = computed(() => {
  let list = []
  if (activeTab.value === 'broken') {
    list = brokenRooms.value.length > 0 ? brokenRooms.value : (missingRooms.value.length > 0 ? missingRooms.value : allAlertRooms.value)
  } else if (activeTab.value === 'missing') {
    list = missingRooms.value
  } else {
    list = allAlertRooms.value
  }

  return isExpanded.value ? list : list.slice(0, 6)
})

const currentTotalCount = computed(() => {
  if (activeTab.value === 'broken') return brokenRooms.value.length
  if (activeTab.value === 'missing') return missingRooms.value.length
  return allAlertRooms.value.length
})

function onRoomClick(room) {
  emit('select-room', room)
}

function goToRoomEquipments(roomId, e) {
  e.stopPropagation()
  router.push({ path: '/rooms/equipments', query: { room_id: roomId } })
}
</script>

<template>
  <div class="bg-white rounded-2xl p-4 sm:p-6 border border-slate-200/80 shadow-xs flex flex-col justify-between">
    <div>
      <!-- Header -->
      <div class="flex items-center justify-between gap-3 mb-3">
        <div>
          <h3 class="text-sm sm:text-base font-bold text-slate-800 flex items-center gap-2">
            <span>Cảnh báo Thiết bị theo Phòng</span>
            <span
              v-if="allAlertRooms.length > 0"
              class="px-2 py-0.5 rounded-full text-[11px] font-extrabold bg-rose-100 text-rose-700"
            >
              {{ allAlertRooms.length }} phòng
            </span>
          </h3>
          <p class="text-[11px] sm:text-xs text-slate-400 mt-0.5">
            Ưu tiên giải quyết sự cố ảnh hưởng khách và cấp bù thiết bị thiếu
          </p>
        </div>
        <div class="p-2 bg-rose-50 text-rose-600 rounded-xl flex-shrink-0">
          <v-icon icon="mdi-alert-octagon-outline" size="20" />
        </div>
      </div>

      <!-- Tab Switcher (Broken vs Missing vs All) -->
      <div class="flex items-center gap-1.5 p-1 bg-slate-100/90 rounded-xl mb-3.5 border border-slate-200/60">
        <button
          type="button"
          class="flex-1 py-1.5 px-2.5 rounded-lg text-xs font-bold transition-all flex items-center justify-center gap-1.5 cursor-pointer"
          :class="activeTab === 'broken'
            ? 'bg-white text-rose-600 shadow-xs'
            : 'text-slate-600 hover:text-slate-900'"
          @click="activeTab = 'broken'"
        >
          <span class="w-2 h-2 rounded-full bg-rose-500" :class="{ 'animate-pulse': brokenRooms.length > 0 }" />
          <span>Hỏng hóc</span>
          <span class="px-1.5 py-0.2 rounded-full text-[10px] font-extrabold bg-rose-50 text-rose-600">
            {{ brokenRooms.length }}
          </span>
        </button>

        <button
          type="button"
          class="flex-1 py-1.5 px-2.5 rounded-lg text-xs font-bold transition-all flex items-center justify-center gap-1.5 cursor-pointer"
          :class="activeTab === 'missing'
            ? 'bg-white text-amber-600 shadow-xs'
            : 'text-slate-600 hover:text-slate-900'"
          @click="activeTab = 'missing'"
        >
          <span class="w-2 h-2 rounded-full bg-amber-500" />
          <span>Thiếu định mức</span>
          <span class="px-1.5 py-0.2 rounded-full text-[10px] font-extrabold bg-amber-50 text-amber-600">
            {{ missingRooms.length }}
          </span>
        </button>

        <button
          type="button"
          class="py-1.5 px-2.5 rounded-lg text-xs font-bold transition-all flex items-center justify-center gap-1 cursor-pointer shrink-0"
          :class="activeTab === 'all'
            ? 'bg-white text-slate-800 shadow-xs'
            : 'text-slate-500 hover:text-slate-900'"
          @click="activeTab = 'all'"
        >
          <span>Tất cả</span>
          <span class="text-[10px] text-slate-400">({{ allAlertRooms.length }})</span>
        </button>
      </div>

      <!-- Loading skeleton -->
      <div v-if="loading" class="space-y-2.5">
        <div v-for="n in 3" :key="n" class="h-14 bg-slate-100 rounded-xl animate-pulse" />
      </div>

      <!-- Alert room list -->
      <div v-else-if="currentList.length > 0" class="space-y-2">
        <div
          v-for="room in currentList"
          :key="room.id"
          class="flex items-center justify-between gap-2.5 p-2.5 sm:p-3 rounded-xl border transition-all duration-150 cursor-pointer group hover:shadow-xs"
          :class="room.stats?.broken_count > 0 || room.equipment_status === 'broken'
            ? 'bg-rose-50/50 hover:bg-rose-50 border-rose-100 hover:border-rose-200'
            : 'bg-amber-50/40 hover:bg-amber-50/80 border-amber-100 hover:border-amber-200'"
          @click="onRoomClick(room)"
        >
          <!-- Left: Room number badge & info -->
          <div class="flex items-center gap-2.5 sm:gap-3 min-w-0 flex-1">
            <div
              class="w-9 h-9 sm:w-10 sm:h-10 rounded-lg flex items-center justify-center font-bold text-xs sm:text-sm shadow-2xs flex-shrink-0 transition-transform group-hover:scale-105"
              :class="room.stats?.broken_count > 0 || room.equipment_status === 'broken'
                ? 'bg-rose-600 text-white'
                : 'bg-amber-500 text-white'"
            >
              {{ room.room_number }}
            </div>

            <div class="min-w-0 flex-1">
              <div class="flex items-center gap-1.5">
                <p class="text-xs font-bold text-slate-800 truncate group-hover:text-primary transition-colors">
                  {{ room.name || `Phòng ${room.room_number}` }}
                </p>
                <span class="text-[10px] text-slate-400 truncate">
                  ({{ room.room_type || 'Tiêu chuẩn' }})
                </span>
              </div>
              <p class="text-[10px] sm:text-[11px] text-slate-500 truncate mt-0.5">
                Tầng {{ room.floor || '—' }} • {{ room.block || 'Khu chính' }}
              </p>
            </div>
          </div>

          <!-- Right: Badges & Quick Action -->
          <div class="flex items-center gap-1.5 flex-shrink-0">
            <div class="text-right">
              <span
                v-if="room.stats?.broken_count > 0"
                class="inline-block px-2 py-0.5 rounded text-[10px] sm:text-[11px] font-bold bg-rose-100 text-rose-700 mr-1"
              >
                {{ room.stats.broken_count }} hỏng
              </span>
              <span
                v-if="room.stats?.missing_count > 0"
                class="inline-block px-2 py-0.5 rounded text-[10px] sm:text-[11px] font-bold bg-amber-100 text-amber-800"
              >
                {{ room.stats.missing_count }} thiếu
              </span>
              <span
                v-if="!room.stats && room.badge_color === 'red'"
                class="inline-block px-2 py-0.5 rounded text-[10px] sm:text-[11px] font-bold bg-rose-100 text-rose-700"
              >
                Sự cố
              </span>
            </div>

            <!-- Quick Manage Equipments Button -->
            <button
              type="button"
              class="p-1.5 rounded-lg text-slate-400 hover:text-primary hover:bg-white transition cursor-pointer"
              title="Quản lý và cấp phát thiết bị phòng này"
              @click="goToRoomEquipments(room.id, $event)"
            >
              <v-icon icon="mdi-tune-vertical" size="16" />
            </button>
          </div>
        </div>

        <!-- Expand / Collapse Button -->
        <div v-if="currentTotalCount > 6" class="text-center pt-1">
          <button
            type="button"
            class="text-xs font-semibold text-slate-500 hover:text-slate-800 transition inline-flex items-center gap-1 cursor-pointer"
            @click="isExpanded = !isExpanded"
          >
            <span>{{ isExpanded ? 'Thu gọn danh sách' : `Xem thêm ${currentTotalCount - 6} phòng khác` }}</span>
            <v-icon :icon="isExpanded ? 'mdi-chevron-up' : 'mdi-chevron-down'" size="16" />
          </button>
        </div>
      </div>

      <!-- Empty state: All rooms good in this category -->
      <div v-else class="text-center py-7 text-slate-400">
        <v-icon icon="mdi-check-decagram-outline" size="40" class="text-emerald-500 mb-2 block mx-auto" />
        <p class="text-sm font-bold text-slate-700">Không có phòng cảnh báo!</p>
        <p class="text-xs text-slate-400 mt-0.5">
          {{ activeTab === 'broken' ? 'Không có thiết bị nào bị báo hỏng ở khu vực này.' : 'Tất cả các phòng đều được trang bị đủ định mức chuẩn.' }}
        </p>
      </div>
    </div>

    <!-- Footer: Link to Map View -->
    <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between text-xs">
      <span class="text-slate-400">
        Nhấp vào phòng để xem danh mục thiết bị
      </span>
      <router-link
        to="/equipments/map-view"
        class="font-bold text-blue-600 hover:text-blue-700 inline-flex items-center gap-1"
      >
        <span>Xem toàn bộ trên Sơ đồ Mặt bằng</span>
        <v-icon icon="mdi-arrow-right" size="14" />
      </router-link>
    </div>
  </div>
</template>
