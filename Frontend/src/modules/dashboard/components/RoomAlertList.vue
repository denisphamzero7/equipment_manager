<script setup>
import { computed } from 'vue'

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

// Filter rooms that have broken or missing equipment (badge_color === 'red' or 'yellow')
const alertRooms = computed(() => {
  return props.rooms
    .filter(r => r.badge_color === 'red' || r.badge_color === 'yellow' || (r.stats && (r.stats.broken_count > 0 || r.stats.missing_count > 0)))
    .slice(0, 6)
})
</script>

<template>
  <div class="bg-white rounded-2xl p-6 border border-slate-200/80 shadow-sm flex flex-col justify-between">
    <div>
      <div class="flex items-center justify-between mb-4">
        <div>
          <h3 class="text-base font-bold text-slate-800">
            Cảnh báo Thiết bị theo Phòng
          </h3>
          <p class="text-xs text-slate-400 mt-0.5">
            Các phòng phát hiện sự cố hỏng hóc hoặc thiếu thiết bị chuẩn
          </p>
        </div>
        <div class="p-2 bg-rose-50 text-rose-600 rounded-xl">
          <v-icon icon="mdi-alert-octagon-outline" size="20" />
        </div>
      </div>

      <!-- Loading skeleton -->
      <div v-if="loading" class="space-y-3">
        <div v-for="n in 3" :key="n" class="h-14 bg-slate-100 rounded-xl animate-pulse" />
      </div>

      <!-- Alert room list -->
      <div v-else-if="alertRooms.length > 0" class="space-y-2.5">
        <div
          v-for="room in alertRooms"
          :key="room.id"
          class="flex items-center justify-between p-3 rounded-xl border transition-colors"
          :class="room.badge_color === 'red' ? 'bg-rose-50/60 border-rose-100' : 'bg-amber-50/60 border-amber-100'"
        >
          <div class="flex items-center gap-3">
            <div
              class="w-10 h-10 rounded-lg flex items-center justify-center font-bold text-sm shadow-xs"
              :class="room.badge_color === 'red' ? 'bg-rose-600 text-white' : 'bg-amber-500 text-white'"
            >
              {{ room.room_number }}
            </div>
            <div>
              <p class="text-xs font-bold text-slate-800">
                {{ room.name || `Phòng ${room.room_number}` }}
              </p>
              <p class="text-[11px] text-slate-500">
                Tầng {{ room.floor || '—' }} • {{ room.block || 'Khu chính' }}
              </p>
            </div>
          </div>

          <div class="text-right">
            <span
              v-if="room.stats?.broken_count > 0"
              class="inline-block px-2 py-0.5 rounded text-[11px] font-bold bg-rose-100 text-rose-700 mr-1"
            >
              {{ room.stats.broken_count }} hỏng
            </span>
            <span
              v-if="room.stats?.missing_count > 0"
              class="inline-block px-2 py-0.5 rounded text-[11px] font-bold bg-amber-100 text-amber-800"
            >
              {{ room.stats.missing_count }} thiếu
            </span>
          </div>
        </div>
      </div>

      <!-- Empty state: All rooms good -->
      <div v-else class="text-center py-8 text-slate-400">
        <v-icon icon="mdi-check-decagram-outline" size="44" class="text-emerald-500 mb-2 block mx-auto" />
        <p class="text-sm font-bold text-slate-700">Tất cả phòng đều đạt chuẩn!</p>
        <p class="text-xs text-slate-400 mt-1">Không phát hiện phòng nào bị thiếu hụt hoặc hư hỏng thiết bị.</p>
      </div>
    </div>

    <div class="mt-4 pt-3 border-t border-slate-100 text-right">
      <router-link
        to="/equipments/map-view"
        class="text-xs font-bold text-blue-600 hover:text-blue-700 inline-flex items-center gap-1"
      >
        <span>Xem toàn bộ trên Sơ đồ Mặt bằng</span>
        <v-icon icon="mdi-arrow-right" size="14" />
      </router-link>
    </div>
  </div>
</template>
