<script setup>
defineProps({
  stats: {
    type: Object,
    required: true,
    default: () => ({
      total: 0,
      compliant: 0,
      missing: 0,
      rate: 0,
    }),
  },
})
</script>

<template>
  <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
    <!-- Tổng phòng -->
    <div class="bg-white border border-slate-200/80 rounded-2xl p-4 flex items-center gap-3.5 shadow-xs">
      <div class="w-11 h-11 rounded-xl bg-slate-100 flex items-center justify-center text-slate-700">
        <v-icon icon="mdi-door" size="24" />
      </div>
      <div>
        <div class="text-xs font-bold text-slate-500 uppercase tracking-wider">Tổng phòng đối soát</div>
        <div class="text-2xl font-black text-slate-800 mt-0.5">{{ stats.total }}</div>
      </div>
    </div>

    <!-- Đạt chuẩn định mức -->
    <div class="bg-white border border-emerald-200/80 rounded-2xl p-4 flex items-center gap-3.5 shadow-xs">
      <div class="w-11 h-11 rounded-xl bg-emerald-50 flex items-center justify-center text-emerald-600">
        <v-icon icon="mdi-check-decagram" size="24" />
      </div>
      <div>
        <div class="text-xs font-bold text-emerald-600 uppercase tracking-wider">Số phòng đủ chuẩn</div>
        <div class="text-2xl font-black text-emerald-700 mt-0.5">
          {{ stats.compliant }} <span class="text-xs font-normal text-slate-400">/ {{ stats.total }} phòng</span>
        </div>
      </div>
    </div>

    <!-- Chưa đạt định mức -->
    <div class="bg-white border border-rose-200/80 rounded-2xl p-4 flex items-center gap-3.5 shadow-xs">
      <div class="w-11 h-11 rounded-xl bg-rose-50 flex items-center justify-center text-rose-600">
        <v-icon icon="mdi-alert-decagram" size="24" />
      </div>
      <div>
        <div class="text-xs font-bold text-rose-600 uppercase tracking-wider">Phòng thiếu thiết bị</div>
        <div class="text-2xl font-black text-rose-700 mt-0.5">
          {{ stats.missing }} <span class="text-xs font-normal text-slate-400">phòng</span>
        </div>
      </div>
    </div>

    <!-- Tỷ lệ tuân thủ -->
    <div class="bg-white border border-blue-200/80 rounded-2xl p-4 flex items-center gap-3.5 shadow-xs">
      <div class="w-11 h-11 rounded-xl bg-blue-50 flex items-center justify-center text-blue-600">
        <v-icon icon="mdi-percent-outline" size="24" />
      </div>
      <div>
        <div class="text-xs font-bold text-blue-600 uppercase tracking-wider">Tỷ lệ phủ định mức</div>
        <div class="text-2xl font-black text-blue-700 mt-0.5">{{ stats.rate }}%</div>
      </div>
    </div>
  </div>
</template>
