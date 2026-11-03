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
    cardBorder: 'border-blue-600/30 dark:border-blue-500/30',
    iconBg: 'bg-blue-50 dark:bg-blue-950/70 text-blue-600 dark:text-blue-400 border-blue-200/60 dark:border-blue-800/60',
    valueColor: 'text-slate-800 dark:text-slate-100',
    titleColor: 'text-slate-500 dark:text-slate-400',
  },
  success: {
    cardBorder: 'border-emerald-600/30 dark:border-emerald-500/30',
    iconBg: 'bg-emerald-50 dark:bg-emerald-950/70 text-emerald-600 dark:text-emerald-400 border-emerald-200/60 dark:border-emerald-800/60',
    valueColor: 'text-emerald-700 dark:text-emerald-400',
    titleColor: 'text-emerald-600 dark:text-emerald-400',
  },
  warning: {
    cardBorder: 'border-amber-600/30 dark:border-amber-500/30',
    iconBg: 'bg-amber-50 dark:bg-amber-950/70 text-amber-600 dark:text-amber-400 border-amber-200/60 dark:border-amber-800/60',
    valueColor: 'text-amber-700 dark:text-amber-400',
    titleColor: 'text-amber-600 dark:text-amber-400',
  },
  error: {
    cardBorder: 'border-rose-600/30 dark:border-rose-500/30',
    iconBg: 'bg-rose-50 dark:bg-rose-950/70 text-rose-600 dark:text-rose-400 border-rose-200/60 dark:border-rose-800/60',
    valueColor: 'text-rose-700 dark:text-rose-400',
    titleColor: 'text-rose-600 dark:text-rose-400',
  },
  info: {
    cardBorder: 'border-sky-600/30 dark:border-sky-500/30',
    iconBg: 'bg-sky-50 dark:bg-sky-950/70 text-sky-600 dark:text-sky-400 border-sky-200/60 dark:border-sky-800/60',
    valueColor: 'text-sky-700 dark:text-sky-400',
    titleColor: 'text-sky-600 dark:text-sky-400',
  },
}
</script>

<template>
  <div
    class="bg-white dark:bg-slate-900 rounded-2xl p-4 sm:p-5 shadow-xs hover:shadow-md transition-all duration-200 relative overflow-hidden border"
    :class="colorMap[color]?.cardBorder || colorMap.primary.cardBorder"
  >
    <div class="flex items-start justify-between gap-3">
      <div class="min-w-0 flex-1">
        <p
          class="text-[11px] sm:text-xs font-semibold uppercase tracking-wider mb-1 truncate"
          :class="colorMap[color]?.titleColor || 'text-slate-500 dark:text-slate-400'"
        >
          {{ title }}
        </p>
        <div class="flex items-baseline gap-2">
          <h3
            v-if="!loading"
            class="text-xl sm:text-2xl font-black tracking-tight"
            :class="colorMap[color]?.valueColor || 'text-slate-800 dark:text-slate-100'"
          >
            {{ value }}
          </h3>
          <div
            v-else
            class="h-7 w-16 bg-slate-200 dark:bg-slate-700 rounded-md animate-pulse my-1"
          />
        </div>
        <p v-if="subtitle" class="text-[11px] sm:text-xs text-slate-400 dark:text-slate-400 mt-0.5 truncate">
          {{ subtitle }}
        </p>
      </div>

      <div
        class="w-10 h-10 sm:w-12 sm:h-12 rounded-xl flex items-center justify-center border flex-shrink-0"
        :class="colorMap[color]?.iconBg || colorMap.primary.iconBg"
      >
        <v-icon :icon="icon" size="20" class="sm:scale-110" />
      </div>
    </div>

    <div v-if="trend" class="mt-3 pt-2.5 border-t border-slate-100 dark:border-slate-800 flex items-center gap-1.5 text-xs truncate">
      <v-icon
        :icon="trendType === 'up' ? 'mdi-trending-up' : trendType === 'down' ? 'mdi-trending-down' : 'mdi-minus'"
        size="16"
        :color="trendType === 'up' ? 'success' : trendType === 'down' ? 'error' : 'secondary'"
      />
      <span class="text-slate-500 dark:text-slate-400 truncate">{{ trend }}</span>
    </div>
  </div>
</template>
