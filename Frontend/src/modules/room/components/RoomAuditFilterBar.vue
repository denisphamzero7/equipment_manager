<script setup>
defineProps({
  filters: {
    type: Object,
    required: true,
  },
  totalRooms: {
    type: Number,
    default: 0,
  },
  filteredRoomsCount: {
    type: Number,
    default: 0,
  },
  existingBlocks: {
    type: Array,
    default: () => [],
  },
  blockCounts: {
    type: Object,
    default: () => ({}),
  },
  floorOptions: {
    type: Array,
    default: () => [],
  },
  complianceOptions: {
    type: Array,
    default: () => [],
  },
  roomTypeOptions: {
    type: Array,
    default: () => [],
  },
  hasActiveFilters: {
    type: Boolean,
    default: false,
  },
})

defineEmits(['select-block', 'reset-filters'])
</script>

<template>
  <div class="bg-white border border-slate-200/80 rounded-2xl shadow-xs overflow-hidden">
    <!-- Top: Block Quick-Filter Pills Bar -->
    <div class="px-4 pt-3.5 pb-3 border-b border-slate-100 bg-slate-50/60">
      <div class="flex items-center justify-between gap-2 mb-2">
        <span class="text-xs font-bold text-slate-500 uppercase tracking-wider flex items-center gap-1.5">
          <v-icon icon="mdi-domain" size="15" color="primary" />
          <span>Phân khu & Tòa nhà (Block):</span>
        </span>
        <button
          v-if="filters.block"
          class="text-xs text-primary font-semibold hover:underline flex items-center gap-1 cursor-pointer"
          @click="$emit('select-block', '')"
        >
          <v-icon icon="mdi-eye-outline" size="14" />
          <span>Hiển thị tất cả Block</span>
        </button>
      </div>

      <!-- Horizontal Block Pills Bar -->
      <div class="flex items-center gap-2 overflow-x-auto pb-1 scrollbar-thin">
        <button
          type="button"
          class="px-3.5 py-1.5 rounded-xl text-xs font-semibold whitespace-nowrap transition-all duration-150 flex items-center gap-1.5 cursor-pointer shrink-0"
          :class="!filters.block ? 'bg-primary text-white shadow-xs' : 'bg-white text-slate-700 border border-slate-200 hover:border-slate-300 hover:bg-slate-50'"
          @click="$emit('select-block', '')"
        >
          <span>Tất cả vị trí</span>
          <span
            class="text-[11px] px-1.5 py-0.2 rounded-full font-bold"
            :class="!filters.block ? 'bg-white/25 text-white' : 'bg-slate-100 text-slate-600'"
          >
            {{ totalRooms }}
          </span>
        </button>
        <button
          v-for="b in existingBlocks"
          :key="b"
          type="button"
          class="px-3.5 py-1.5 rounded-xl text-xs font-semibold whitespace-nowrap transition-all duration-150 flex items-center gap-1.5 cursor-pointer shrink-0"
          :class="filters.block === b ? 'bg-primary text-white shadow-xs' : 'bg-white text-slate-700 border border-slate-200 hover:border-slate-300 hover:bg-slate-50'"
          @click="$emit('select-block', b)"
        >
          <v-icon icon="mdi-office-building-outline" size="13" />
          <span>{{ b }}</span>
          <span
            class="text-[11px] px-1.5 py-0.2 rounded-full font-bold"
            :class="filters.block === b ? 'bg-white/25 text-white' : 'bg-slate-100 text-slate-600'"
          >
            {{ blockCounts[b] || 0 }}
          </span>
        </button>
      </div>
    </div>

    <!-- Bottom: Search & Detailed Filters Controls -->
    <div class="p-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 items-center">
      <!-- 1. Search Room -->
      <div class="lg:col-span-2">
        <v-text-field
          v-model="filters.search"
          placeholder="Tìm nhanh theo số phòng, tên phòng..."
          density="compact"
          variant="outlined"
          prepend-inner-icon="mdi-magnify"
          hide-details
          clearable
        />
      </div>

      <!-- 2. Floor Select -->
      <div>
        <v-select
          v-model="filters.floor"
          :items="floorOptions"
          item-value="value"
          item-title="title"
          placeholder="Tất cả Tầng"
          density="compact"
          variant="outlined"
          prepend-inner-icon="mdi-stairs"
          hide-details
          clearable
        />
      </div>

      <!-- 3. Compliance Status Select -->
      <div>
        <v-select
          v-model="filters.status"
          :items="complianceOptions"
          item-value="value"
          item-title="title"
          placeholder="Tất cả định mức"
          density="compact"
          variant="outlined"
          prepend-inner-icon="mdi-check-decagram-outline"
          hide-details
          clearable
        />
      </div>

      <!-- 4. Room Type Select -->
      <div>
        <v-select
          v-model="filters.room_type_id"
          :items="roomTypeOptions"
          item-value="value"
          item-title="title"
          placeholder="Tất cả loại phòng"
          density="compact"
          variant="outlined"
          prepend-inner-icon="mdi-home-outline"
          hide-details
          clearable
        />
      </div>
    </div>

    <!-- Filter Count & Reset Bar -->
    <div v-if="hasActiveFilters" class="px-4 py-2.5 bg-blue-50/40 border-t border-slate-100 flex items-center justify-between text-xs">
      <span class="text-slate-600">
        Đang lọc: Tìm thấy <strong>{{ filteredRoomsCount }}</strong> trên tổng số <strong>{{ totalRooms }}</strong> phòng
      </span>
      <button
        type="button"
        class="text-primary font-bold hover:underline flex items-center gap-1 cursor-pointer"
        @click="$emit('reset-filters')"
      >
        <v-icon icon="mdi-filter-remove-outline" size="14" />
        <span>Xóa bộ lọc</span>
      </button>
    </div>
  </div>
</template>
