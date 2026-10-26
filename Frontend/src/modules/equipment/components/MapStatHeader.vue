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
  selectedBlock: {
    type: String,
    default: '',
  },
})

const stats = computed(() => {
  const list = props.rooms || []
  const total = list.length
  let good = 0
  let broken = 0
  let maintenance = 0
  let unconfigured = 0

  list.forEach((r) => {
    const isGood = r.badge_color === 'green' || r.equipment_status === 'good'
    const isMaint = r.badge_color === 'yellow' || r.equipment_status === 'maintenance'
    const isBroken = r.badge_color === 'red' || r.equipment_status === 'broken' || r.equipment_status === 'missing'
    const isUnconf = !r.badge_color || r.badge_color === 'slate' || r.badge_color === 'gray' || r.equipment_status === 'unconfigured'

    if (isBroken) {
      broken++
    } else if (isMaint) {
      maintenance++
    } else if (isUnconf) {
      unconfigured++
    } else if (isGood) {
      good++
    }
  })

  return { total, good, broken, maintenance, unconfigured }
})
</script>

<template>
  <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 mb-5 sm:mb-6">
    <!-- 1. Tổng phòng -->
    <div
      class="bg-white rounded-xl p-3.5 flex items-center gap-3 shadow-xs"
      style="border: 2px solid #2563eb !important;"
    >
      <div class="w-10 h-10 rounded-lg bg-blue-50 flex items-center justify-center text-blue-600 shrink-0">
        <v-icon icon="mdi-door" size="22" />
      </div>
      <div class="min-w-0">
        <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider truncate">
          {{ selectedBlock ? `Phòng (${selectedBlock})` : 'Tổng phòng' }}
        </div>
        <div v-if="loading" class="h-6 w-12 bg-slate-200 rounded animate-pulse my-0.5" />
        <div v-else class="text-xl font-bold text-slate-800">{{ stats.total }}</div>
      </div>
    </div>

    <!-- 2. Đạt chuẩn 100% -->
    <div
      class="bg-white rounded-xl p-3.5 flex items-center gap-3 shadow-xs"
      style="border: 2px solid #2563eb !important;"
    >
      <div class="w-10 h-10 rounded-lg bg-emerald-50 flex items-center justify-center text-emerald-600 shrink-0">
        <v-icon icon="mdi-check-circle-outline" size="22" />
      </div>
      <div class="min-w-0">
        <div class="text-xs font-semibold text-emerald-600 uppercase tracking-wider truncate">Đạt chuẩn 100%</div>
        <div v-if="loading" class="h-6 w-12 bg-slate-200 rounded animate-pulse my-0.5" />
        <div v-else class="text-xl font-bold text-emerald-700">{{ stats.good }}</div>
      </div>
    </div>

    <!-- 3. Có sự cố / Thiếu TB -->
    <div
      class="bg-white rounded-xl p-3.5 flex items-center gap-3 shadow-xs"
      style="border: 2px solid #2563eb !important;"
    >
      <div class="w-10 h-10 rounded-lg bg-rose-50 flex items-center justify-center text-rose-600 shrink-0">
        <v-icon icon="mdi-alert-circle-outline" size="22" />
      </div>
      <div class="min-w-0">
        <div class="text-xs font-semibold text-rose-600 uppercase tracking-wider truncate">Hỏng / Thiếu TB</div>
        <div v-if="loading" class="h-6 w-12 bg-slate-200 rounded animate-pulse my-0.5" />
        <div v-else class="text-xl font-bold text-rose-700">{{ stats.broken }}</div>
      </div>
    </div>

    <!-- 4. Đang bảo trì -->
    <div
      class="bg-white rounded-xl p-3.5 flex items-center gap-3 shadow-xs"
      style="border: 2px solid #2563eb !important;"
    >
      <div class="w-10 h-10 rounded-lg bg-amber-50 flex items-center justify-center text-amber-600 shrink-0">
        <v-icon icon="mdi-wrench" size="22" />
      </div>
      <div class="min-w-0">
        <div class="text-xs font-semibold text-amber-600 uppercase tracking-wider truncate">Đang bảo trì</div>
        <div v-if="loading" class="h-6 w-12 bg-slate-200 rounded animate-pulse my-0.5" />
        <div v-else class="text-xl font-bold text-amber-700">{{ stats.maintenance }}</div>
      </div>
    </div>

    <!-- 5. Chưa cấu hình -->
    <div
      class="bg-white rounded-xl p-3.5 flex items-center gap-3 shadow-xs col-span-2 sm:col-span-1"
      style="border: 2px solid #2563eb !important;"
    >
      <div class="w-10 h-10 rounded-lg bg-slate-100 flex items-center justify-center text-slate-500 shrink-0">
        <v-icon icon="mdi-help-circle-outline" size="22" />
      </div>
      <div class="min-w-0">
        <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider truncate">Chưa cấu hình</div>
        <div v-if="loading" class="h-6 w-12 bg-slate-200 rounded animate-pulse my-0.5" />
        <div v-else class="text-xl font-bold text-slate-700">{{ stats.unconfigured }}</div>
      </div>
    </div>
  </div>
</template>
