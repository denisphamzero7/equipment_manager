<script setup>
defineProps({
  stats: {
    type: Object,
    required: true,
    default: () => ({
      total: 0,
      compliant: 0,
      missing: 0,
      maintenance: 0,
      rate: 0,
    }),
  },
  loading: {
    type: Boolean,
    default: false,
  },
})
</script>

<template>
  <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-4">
    <!-- 1. Tổng phòng -->
    <div
      class="bg-white dark:bg-slate-900 rounded-2xl p-3.5 flex items-center gap-3 shadow-xs border border-blue-600/30 dark:border-blue-500/30 transition-colors"
    >
      <div class="w-10 h-10 rounded-xl bg-blue-50 dark:bg-blue-950/70 flex items-center justify-center text-blue-600 dark:text-blue-400 shrink-0">
        <v-icon icon="mdi-door" size="22" />
      </div>
      <div class="min-w-0">
        <div class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider truncate">Tổng phòng</div>
        <div v-if="loading" class="h-6 w-12 bg-slate-200 dark:bg-slate-700 rounded animate-pulse my-0.5" />
        <div v-else class="text-xl font-bold text-slate-800 dark:text-slate-100">{{ stats.total }}</div>
      </div>
    </div>

    <!-- 2. Đạt chuẩn định mức -->
    <div
      class="bg-white dark:bg-slate-900 rounded-2xl p-3.5 flex items-center gap-3 shadow-xs border border-emerald-600/30 dark:border-emerald-500/30 transition-colors"
    >
      <div class="w-10 h-10 rounded-xl bg-emerald-50 dark:bg-emerald-950/70 flex items-center justify-center text-emerald-600 dark:text-emerald-400 shrink-0">
        <v-icon icon="mdi-check-circle-outline" size="22" />
      </div>
      <div class="min-w-0">
        <div class="text-xs font-semibold text-emerald-600 dark:text-emerald-400 uppercase tracking-wider truncate">Đủ chuẩn</div>
        <div v-if="loading" class="h-6 w-12 bg-slate-200 dark:bg-slate-700 rounded animate-pulse my-0.5" />
        <div v-else class="text-xl font-bold text-emerald-700 dark:text-emerald-300">
          {{ stats.compliant }} <span class="text-xs font-normal text-slate-400 dark:text-slate-500">/ {{ stats.total }}</span>
        </div>
      </div>
    </div>

    <!-- 3. Thiếu thiết bị -->
    <div
      class="bg-white dark:bg-slate-900 rounded-2xl p-3.5 flex items-center gap-3 shadow-xs border border-rose-600/30 dark:border-rose-500/30 transition-colors"
    >
      <div class="w-10 h-10 rounded-xl bg-rose-50 dark:bg-rose-950/70 flex items-center justify-center text-rose-600 dark:text-rose-400 shrink-0">
        <v-icon icon="mdi-alert-circle-outline" size="22" />
      </div>
      <div class="min-w-0">
        <div class="text-xs font-semibold text-rose-600 dark:text-rose-400 uppercase tracking-wider truncate">Thiếu TB</div>
        <div v-if="loading" class="h-6 w-12 bg-slate-200 dark:bg-slate-700 rounded animate-pulse my-0.5" />
        <div v-else class="text-xl font-bold text-rose-700 dark:text-rose-300">
          {{ stats.missing }} <span class="text-xs font-normal text-slate-400 dark:text-slate-500">phòng</span>
        </div>
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
        <div v-else class="text-xl font-bold text-amber-700 dark:text-amber-300">
          {{ stats.maintenance || 0 }} <span class="text-xs font-normal text-slate-400 dark:text-slate-500">phòng</span>
        </div>
      </div>
    </div>

    <!-- 5. Tỷ lệ phủ / Tuân thủ -->
    <div
      class="bg-white dark:bg-slate-900 rounded-2xl p-3.5 flex items-center gap-3 shadow-xs col-span-2 md:col-span-1 border border-sky-600/30 dark:border-sky-500/30 transition-colors"
    >
      <div class="w-10 h-10 rounded-xl bg-sky-50 dark:bg-sky-950/70 flex items-center justify-center text-sky-600 dark:text-sky-400 shrink-0">
        <v-icon icon="mdi-percent-outline" size="22" />
      </div>
      <div class="min-w-0">
        <div class="text-xs font-semibold text-sky-600 dark:text-sky-400 uppercase tracking-wider truncate">Tỷ lệ phủ</div>
        <div v-if="loading" class="h-6 w-12 bg-slate-200 dark:bg-slate-700 rounded animate-pulse my-0.5" />
        <div v-else class="text-xl font-bold text-sky-700 dark:text-sky-300">{{ stats.rate }}%</div>
      </div>
    </div>
  </div>
</template>
