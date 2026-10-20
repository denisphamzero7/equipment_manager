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
    class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-sm hover:shadow-md transition-all duration-200 relative overflow-hidden"
  >
    <div class="flex items-start justify-between">
      <div>
        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">
          {{ title }}
        </p>
        <div class="flex items-baseline gap-2">
          <h3 v-if="!loading" class="text-2xl font-bold text-slate-800 tracking-tight">
            {{ value }}
          </h3>
          <v-progress-circular
            v-else
            indeterminate
            size="24"
            width="2"
            color="primary"
            class="my-1"
          />
        </div>
        <p v-if="subtitle" class="text-xs text-slate-400 mt-1">
          {{ subtitle }}
        </p>
      </div>

      <div
        class="w-12 h-12 rounded-xl flex items-center justify-center border"
        :class="[colorMap[color]?.bg || colorMap.primary.bg, colorMap[color]?.border || colorMap.primary.border]"
      >
        <v-icon :icon="icon" size="24" />
      </div>
    </div>

    <div v-if="trend" class="mt-4 pt-3 border-t border-slate-100 flex items-center gap-1.5 text-xs">
      <v-icon
        :icon="trendType === 'up' ? 'mdi-trending-up' : trendType === 'down' ? 'mdi-trending-down' : 'mdi-minus'"
        size="16"
        :color="trendType === 'up' ? 'success' : trendType === 'down' ? 'error' : 'secondary'"
      />
      <span
        :class="trendType === 'up' ? 'text-emerald-600 font-medium' : trendType === 'down' ? 'text-rose-600 font-medium' : 'text-slate-500'"
      >
        {{ trend }}
      </span>
    </div>
  </div>
</template>
