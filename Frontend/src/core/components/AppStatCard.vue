<script setup>
defineProps({
  title: {
    type: String,
    required: true,
  },
  value: {
    type: [Number, String],
    default: 0,
  },
  subtitle: {
    type: String,
    default: '',
  },
  icon: {
    type: String,
    default: 'mdi-information-outline',
  },
  color: {
    type: String,
    default: 'primary', // primary | success | warning | error | info
  },
  trend: {
    type: String,
    default: '', // e.g. "+5% so với tháng trước"
  },
  trendType: {
    type: String,
    default: 'up', // up | down | neutral
  },
  loading: {
    type: Boolean,
    default: false,
  },
})

const colorMap = {
  primary: {
    bg: 'bg-blue-50 text-blue-600',
    border: 'border-blue-100',
  },
  success: {
    bg: 'bg-emerald-50 text-emerald-600',
    border: 'border-emerald-100',
  },
  warning: {
    bg: 'bg-amber-50 text-amber-600',
    border: 'border-amber-100',
  },
  error: {
    bg: 'bg-rose-50 text-rose-600',
    border: 'border-rose-100',
  },
  info: {
    bg: 'bg-sky-50 text-sky-600',
    border: 'border-sky-100',
  },
}
</script>

<template>
  <div
    class="bg-white rounded-2xl p-4 sm:p-5 shadow-xs hover:shadow-md transition-all duration-200 relative overflow-hidden"
    style="border: 2px solid #2563eb !important;"
  >
    <div class="flex items-start justify-between gap-3">
      <div class="min-w-0 flex-1">
        <p class="text-[11px] sm:text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1 truncate">
          {{ title }}
        </p>
        <div class="flex items-baseline gap-2">
          <h3 v-if="!loading" class="text-xl sm:text-2xl font-bold text-slate-800 tracking-tight">
            {{ value }}
          </h3>
          <div
            v-else
            class="h-7 w-16 bg-slate-200 rounded-md animate-pulse my-1"
          />
        </div>
        <p v-if="subtitle" class="text-[11px] sm:text-xs text-slate-400 mt-0.5 truncate">
          {{ subtitle }}
        </p>
      </div>

      <div
        class="w-10 h-10 sm:w-12 sm:h-12 rounded-xl flex items-center justify-center border flex-shrink-0"
        :class="[colorMap[color]?.bg || colorMap.primary.bg, colorMap[color]?.border || colorMap.primary.border]"
      >
        <v-icon :icon="icon" size="20" class="sm:scale-110" />
      </div>
    </div>

    <div v-if="trend" class="mt-3 pt-2.5 border-t border-slate-100 flex items-center gap-1.5 text-xs truncate">
      <v-icon
        :icon="trendType === 'up' ? 'mdi-trending-up' : trendType === 'down' ? 'mdi-trending-down' : 'mdi-minus'"
        size="16"
        :color="trendType === 'up' ? 'success' : trendType === 'down' ? 'error' : 'secondary'"
      />
      <span class="text-slate-500 truncate">{{ trend }}</span>
    </div>
  </div>
</template>
