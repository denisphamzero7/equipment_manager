<script setup>
import { computed } from 'vue'
import { useRouter } from 'vue-router'

const props = defineProps({
  stats: {
    type: Object,
    default: () => ({}),
  },
  selectedBlock: {
    type: String,
    default: '',
  },
})

const router = useRouter()

const total = computed(() => props.stats?.total_equipments || 0)

const statusBreakdown = computed(() => {
  const byStatus = props.stats?.by_status || {}
  const items = [
    { key: 'good', label: 'Hoạt động tốt', count: byStatus.good || 0, color: 'bg-emerald-500', text: 'text-emerald-700', border: 'border-emerald-200' },
    { key: 'broken', label: 'Hư hỏng', count: byStatus.broken || 0, color: 'bg-rose-500', text: 'text-rose-700', border: 'border-rose-200' },
    { key: 'maintenance', label: 'Bảo trì', count: byStatus.maintenance || 0, color: 'bg-amber-500', text: 'text-amber-700', border: 'border-amber-200' },
    { key: 'in_stock', label: 'Trong kho', count: byStatus.in_stock || 0, color: 'bg-sky-500', text: 'text-sky-700', border: 'border-sky-200' },
    { key: 'lost', label: 'Thất lạc', count: byStatus.lost || 0, color: 'bg-slate-400', text: 'text-slate-600', border: 'border-slate-200' },
  ]

  return items.map(item => ({
    ...item,
    percentage: total.value > 0 ? Math.round((item.count / total.value) * 100) : 0,
  }))
})

function filterByStatus(key) {
  const query = { status: key }
  if (props.selectedBlock) {
    query.block = props.selectedBlock
  }
  router.push({ path: '/equipments', query })
}
</script>

<template>
  <div class="bg-white rounded-2xl p-4 sm:p-6 border border-slate-200/80 shadow-xs flex flex-col justify-between">
    <div>
      <div class="flex items-center justify-between gap-3 mb-4">
        <div>
          <h3 class="text-sm sm:text-base font-bold text-slate-800">
            Phân bố Tình trạng Thiết bị
          </h3>
          <p class="text-[11px] sm:text-xs text-slate-400 mt-0.5">
            {{ selectedBlock ? `Khu vực: ${selectedBlock}` : 'Tỷ lệ tình trạng sức khỏe' }} (tổng {{ total }} thiết bị)
          </p>
        </div>
        <div class="p-2 bg-blue-50 text-blue-600 rounded-xl flex-shrink-0">
          <v-icon icon="mdi-chart-pie" size="20" />
        </div>
      </div>

      <!-- Multi-segment Progress Bar -->
      <div class="w-full h-3.5 bg-slate-100 rounded-full overflow-hidden flex mb-6 shadow-inner">
        <template v-for="item in statusBreakdown" :key="item.key">
          <div
            v-if="item.percentage > 0"
            :class="[item.color, 'h-full transition-all duration-500 hover:opacity-85 cursor-pointer']"
            :style="{ width: `${item.percentage}%` }"
            :title="`${item.label}: ${item.count} máy (${item.percentage}%) - Bấm để xem danh sách`"
            @click="filterByStatus(item.key)"
          />
        </template>
      </div>

      <!-- Breakdown Detail List (Clickable Rows!) -->
      <div class="space-y-2">
        <div
          v-for="item in statusBreakdown"
          :key="item.key"
          class="flex items-center justify-between text-xs py-1.5 px-2 rounded-xl transition-all hover:bg-slate-50 cursor-pointer group"
          :title="`Xem ${item.count} thiết bị ${item.label}`"
          @click="filterByStatus(item.key)"
        >
          <div class="flex items-center gap-2.5">
            <span class="w-2.5 h-2.5 rounded-full flex-shrink-0" :class="item.color" />
            <span class="text-slate-700 font-medium group-hover:text-primary transition-colors">
              {{ item.label }}
            </span>
          </div>

          <div class="flex items-center gap-3">
            <span class="font-bold text-slate-800">{{ item.count }} máy</span>
            <span class="text-slate-400 w-10 text-right">{{ item.percentage }}%</span>
            <v-icon icon="mdi-chevron-right" size="14" class="text-slate-300 group-hover:text-primary transition-colors" />
          </div>
        </div>
      </div>
    </div>

    <!-- Health Rate footer -->
    <div class="mt-6 pt-4 border-t border-slate-100 flex items-center justify-between text-xs">
      <div class="flex items-center gap-1.5">
        <v-icon icon="mdi-shield-check" size="16" color="success" />
        <span class="text-slate-500 font-medium">Tỷ lệ sẵn sàng vận hành:</span>
      </div>
      <span class="font-extrabold text-emerald-600 text-sm">
        {{ total > 0 ? Math.round(((stats.by_status?.good || 0) / total) * 100) : 100 }}%
      </span>
    </div>
  </div>
</template>
