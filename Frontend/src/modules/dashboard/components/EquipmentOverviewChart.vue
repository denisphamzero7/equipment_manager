<script setup>
import { computed } from 'vue'

const props = defineProps({
  stats: {
    type: Object,
    default: () => ({}),
  },
})

const total = computed(() => props.stats?.total_equipments || 0)

const statusBreakdown = computed(() => {
  const byStatus = props.stats?.by_status || {}
  const items = [
    { key: 'good', label: 'Hoạt động tốt', count: byStatus.good || 0, color: 'bg-emerald-500', text: 'text-emerald-700' },
    { key: 'broken', label: 'Hư hỏng', count: byStatus.broken || 0, color: 'bg-rose-500', text: 'text-rose-700' },
    { key: 'maintenance', label: 'Bảo trì', count: byStatus.maintenance || 0, color: 'bg-amber-500', text: 'text-amber-700' },
    { key: 'in_stock', label: 'Trong kho', count: byStatus.in_stock || 0, color: 'bg-sky-500', text: 'text-sky-700' },
    { key: 'lost', label: 'Thất lạc', count: byStatus.lost || 0, color: 'bg-slate-400', text: 'text-slate-600' },
  ]

  return items.map(item => ({
    ...item,
    percentage: total.value > 0 ? Math.round((item.count / total.value) * 100) : 0,
  }))
})
</script>

<template>
  <div class="bg-white rounded-2xl p-6 border border-slate-200/80 shadow-sm flex flex-col justify-between">
    <div>
      <div class="flex items-center justify-between mb-4">
        <div>
          <h3 class="text-base font-bold text-slate-800">
            Phân bố Tình trạng Thiết bị
          </h3>
          <p class="text-xs text-slate-400 mt-0.5">
            Tỷ lệ tình trạng sức khỏe của tổng {{ total }} thiết bị
          </p>
        </div>
        <div class="p-2 bg-blue-50 text-blue-600 rounded-xl">
          <v-icon icon="mdi-chart-pie" size="20" />
        </div>
      </div>

      <!-- Multi-segment Progress Bar -->
      <div class="w-full h-3.5 bg-slate-100 rounded-full overflow-hidden flex mb-6">
        <template v-for="item in statusBreakdown" :key="item.key">
          <div
            v-if="item.percentage > 0"
            :class="item.color"
            :style="{ width: `${item.percentage}%` }"
            :title="`${item.label}: ${item.count} (${item.percentage}%)`"
            class="h-full transition-all duration-500"
          />
        </template>
      </div>

      <!-- Breakdown Detail List -->
      <div class="space-y-3">
        <div
          v-for="item in statusBreakdown"
          :key="item.key"
          class="flex items-center justify-between text-xs py-1"
        >
          <div class="flex items-center gap-2.5">
            <span class="w-3 h-3 rounded-full flex-shrink-0" :class="item.color" />
            <span class="text-slate-700 font-medium">{{ item.label }}</span>
          </div>

          <div class="flex items-center gap-3">
            <span class="font-bold text-slate-800">{{ item.count }} máy</span>
            <span class="text-slate-400 w-10 text-right">{{ item.percentage }}%</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Health Rate footer -->
    <div class="mt-6 pt-4 border-t border-slate-100 flex items-center justify-between text-xs">
      <span class="text-slate-500 font-medium">Tỷ lệ sẵn sàng vận hành:</span>
      <span class="font-bold text-emerald-600 text-sm">
        {{ total > 0 ? Math.round(((stats.by_status?.good || 0) / total) * 100) : 100 }}%
      </span>
    </div>
  </div>
</template>
