<script setup>
defineProps({
  stats: {
    type: Object,
    required: true,
    default: () => ({
      total: 0,
      good: 0,
      broken: 0,
      maintenance: 0,
      in_stock: 0,
    }),
  },
  loading: {
    type: Boolean,
    default: false,
  },
  activeStatus: {
    type: String,
    default: '',
  },
  activeBlock: {
    type: String,
    default: '',
  },
})

defineEmits(['select-status', 'select-stock'])
</script>

<template>
  <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-4">
    <!-- 1. Tổng thiết bị -->
    <div
      class="bg-white border-2 border-blue-400 rounded-xl p-3.5 flex items-center gap-3 shadow-xs cursor-pointer transition hover:border-blue-600"
      title="Hiển thị tất cả trạng thái"
      @click="$emit('select-status', '')"
    >
      <div class="w-10 h-10 rounded-lg bg-blue-50 flex items-center justify-center text-blue-600">
        <v-icon icon="mdi-cube-outline" size="22" />
      </div>
      <div>
        <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Tổng thiết bị</div>
        <div v-if="loading" class="h-6 w-12 bg-slate-200 rounded animate-pulse my-0.5" />
        <div v-else class="text-xl font-bold text-slate-800">{{ stats.total }}</div>
      </div>
    </div>

    <!-- 2. Hoạt động tốt -->
    <div
      class="bg-white border-2 rounded-xl p-3.5 flex items-center gap-3 shadow-xs cursor-pointer transition"
      :class="activeStatus === 'good' ? 'border-emerald-500 ring-2 ring-emerald-400/30 bg-emerald-50/40' : 'border-blue-400 hover:border-blue-600'"
      title="Lọc nhanh thiết bị hoạt động tốt"
      @click="$emit('select-status', 'good')"
    >
      <div class="w-10 h-10 rounded-lg bg-emerald-50 flex items-center justify-center text-emerald-600">
        <v-icon icon="mdi-check-circle-outline" size="22" />
      </div>
      <div>
        <div class="text-xs font-semibold text-emerald-600 uppercase tracking-wider">Hoạt động tốt</div>
        <div v-if="loading" class="h-6 w-12 bg-slate-200 rounded animate-pulse my-0.5" />
        <div v-else class="text-xl font-bold text-emerald-700">{{ stats.good }}</div>
      </div>
    </div>

    <!-- 3. Hư hỏng / Thiếu -->
    <div
      class="bg-white border-2 rounded-xl p-3.5 flex items-center gap-3 shadow-xs cursor-pointer transition"
      :class="activeStatus === 'broken' ? 'border-rose-500 ring-2 ring-rose-400/30 bg-rose-50/40' : 'border-blue-400 hover:border-blue-600'"
      title="Lọc nhanh thiết bị hư hỏng"
      @click="$emit('select-status', 'broken')"
    >
      <div class="w-10 h-10 rounded-lg bg-rose-50 flex items-center justify-center text-rose-600">
        <v-icon icon="mdi-alert-circle-outline" size="22" />
      </div>
      <div>
        <div class="text-xs font-semibold text-rose-600 uppercase tracking-wider">Hư hỏng / Thiếu</div>
        <div v-if="loading" class="h-6 w-12 bg-slate-200 rounded animate-pulse my-0.5" />
        <div v-else class="text-xl font-bold text-rose-700">{{ stats.broken }}</div>
      </div>
    </div>

    <!-- 4. Đang bảo trì -->
    <div
      class="bg-white border-2 rounded-xl p-3.5 flex items-center gap-3 shadow-xs cursor-pointer transition"
      :class="activeStatus === 'maintenance' ? 'border-amber-500 ring-2 ring-amber-400/30 bg-amber-50/40' : 'border-blue-400 hover:border-blue-600'"
      title="Lọc nhanh thiết bị đang bảo trì"
      @click="$emit('select-status', 'maintenance')"
    >
      <div class="w-10 h-10 rounded-lg bg-amber-50 flex items-center justify-center text-amber-600">
        <v-icon icon="mdi-wrench-outline" size="22" />
      </div>
      <div>
        <div class="text-xs font-semibold text-amber-600 uppercase tracking-wider">Đang bảo trì</div>
        <div v-if="loading" class="h-6 w-12 bg-slate-200 rounded animate-pulse my-0.5" />
        <div v-else class="text-xl font-bold text-amber-700">{{ stats.maintenance }}</div>
      </div>
    </div>

    <!-- 5. Trong kho -->
    <div
      class="bg-white border-2 rounded-xl p-3.5 flex items-center gap-3 shadow-xs cursor-pointer transition"
      :class="activeStatus === 'in_stock' || activeBlock === '__stock__' ? 'border-sky-500 ring-2 ring-sky-400/30 bg-sky-50/40' : 'border-blue-400 hover:border-blue-600'"
      title="Lọc thiết bị đang lưu kho"
      @click="$emit('select-stock')"
    >
      <div class="w-10 h-10 rounded-lg bg-sky-50 flex items-center justify-center text-sky-600">
        <v-icon icon="mdi-package-variant-closed" size="22" />
      </div>
      <div>
        <div class="text-xs font-semibold text-sky-600 uppercase tracking-wider">Trong kho</div>
        <div v-if="loading" class="h-6 w-12 bg-slate-200 rounded animate-pulse my-0.5" />
        <div v-else class="text-xl font-bold text-sky-700">{{ stats.in_stock }}</div>
      </div>
    </div>
  </div>
</template>
