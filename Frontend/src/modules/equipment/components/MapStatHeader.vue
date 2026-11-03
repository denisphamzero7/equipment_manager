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
      class="bg-white dark:bg-slate-900 rounded-2xl p-3.5 flex items-center gap-3 shadow-xs border border-blue-600/30 dark:border-blue-500/30 transition-colors"
    >
      <div class="w-10 h-10 rounded-xl bg-blue-50 dark:bg-blue-950/70 flex items-center justify-center text-blue-600 dark:text-blue-400 shrink-0">
        <v-icon icon="mdi-door" size="22" />
      </div>
      <div class="min-w-0">
        <div class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider truncate">
          {{ selectedBlock ? `Phòng (${selectedBlock})` : 'Tổng phòng' }}
        </div>
        <div v-if="loading" class="h-6 w-12 bg-slate-200 dark:bg-slate-700 rounded animate-pulse my-0.5" />
        <div v-else class="text-xl font-bold text-slate-800 dark:text-slate-100">{{ stats.total }}</div>
      </div>
    </div>

    <!-- 2. Đạt chuẩn 100% -->
    <div
      class="bg-white dark:bg-slate-900 rounded-2xl p-3.5 flex items-center gap-3 shadow-xs border border-emerald-600/30 dark:border-emerald-500/30 transition-colors"
    >
      <div class="w-10 h-10 rounded-xl bg-emerald-50 dark:bg-emerald-950/70 flex items-center justify-center text-emerald-600 dark:text-emerald-400 shrink-0">
        <v-icon icon="mdi-check-circle-outline" size="22" />
      </div>
      <div class="min-w-0">
        <div class="text-xs font-semibold text-emerald-600 dark:text-emerald-400 uppercase tracking-wider truncate">Đạt chuẩn 100%</div>
        <div v-if="loading" class="h-6 w-12 bg-slate-200 dark:bg-slate-700 rounded animate-pulse my-0.5" />
        <div v-else class="text-xl font-bold text-emerald-700 dark:text-emerald-300">{{ stats.good }}</div>
      </div>
    </div>

    <!-- 3. Có sự cố / Thiếu TB -->
    <div
      class="bg-white dark:bg-slate-900 rounded-2xl p-3.5 flex items-center gap-3 shadow-xs border border-rose-600/30 dark:border-rose-500/30 transition-colors"
    >
      <div class="w-10 h-10 rounded-xl bg-rose-50 dark:bg-rose-950/70 flex items-center justify-center text-rose-600 dark:text-rose-400 shrink-0">
        <v-icon icon="mdi-alert-circle-outline" size="22" />
      </div>
      <div class="min-w-0">
        <div class="text-xs font-semibold text-rose-600 dark:text-rose-400 uppercase tracking-wider truncate">Hỏng / Thiếu TB</div>
        <div v-if="loading" class="h-6 w-12 bg-slate-200 dark:bg-slate-700 rounded animate-pulse my-0.5" />
        <div v-else class="text-xl font-bold text-rose-700 dark:text-rose-300">{{ stats.broken }}</div>
      </div>
    </div>

    <!-- 4. Đang bảo trì -->
    <div
      class="bg-white dark:bg-slate-900 rounded-2xl p-3.5 flex items-center gap-3 shadow-xs border border-amber-600/30 dark:border-amber-500/30 transition-colors"
    >
      <div class="w-10 h-10 rounded-xl bg-amber-50 dark:bg-amber-950/70 flex items-center justify-center text-amber-600 dark:text-amber-400 shrink-0">
        <v-icon icon="mdi-wrench" size="22" />
      </div>
      <div class="min-w-0">
        <div class="text-xs font-semibold text-amber-600 dark:text-amber-400 uppercase tracking-wider truncate">Đang bảo trì</div>
        <div v-if="loading" class="h-6 w-12 bg-slate-200 dark:bg-slate-700 rounded animate-pulse my-0.5" />
        <div v-else class="text-xl font-bold text-amber-700 dark:text-amber-300">{{ stats.maintenance }}</div>
      </div>
    </div>

    <!-- 5. Chưa cấu hình -->
    <div
      class="bg-white dark:bg-slate-900 rounded-2xl p-3.5 flex items-center gap-3 shadow-xs col-span-2 sm:col-span-1 border border-slate-200/80 dark:border-slate-800 transition-colors"
    >
      <div class="w-10 h-10 rounded-xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-slate-500 dark:text-slate-400 shrink-0">
        <v-icon icon="mdi-help-circle-outline" size="22" />
      </div>
      <div class="min-w-0">
        <div class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider truncate">Chưa cấu hình</div>
        <div v-if="loading" class="h-6 w-12 bg-slate-200 dark:bg-slate-700 rounded animate-pulse my-0.5" />
        <div v-else class="text-xl font-bold text-slate-700 dark:text-slate-200">{{ stats.unconfigured }}</div>
      </div>
    </div>
  </div>
</template>
