<script setup>
defineProps({
  searchQuery: {
    type: String,
    default: '',
  },
  selectedBlock: {
    type: String,
    default: '',
  },
  selectedFloor: {
    type: [Number, String, null],
    default: null,
  },
  selectedStatus: {
    type: String,
    default: '',
  },
  floorSortOrder: {
    type: String,
    default: 'asc',
  },
  existingBlocks: {
    type: Array,
    default: () => [],
  },
  blockCounts: {
    type: Object,
    default: () => ({}),
  },
  availableFloors: {
    type: Array,
    default: () => [],
  },
  statusFilterOptions: {
    type: Array,
    default: () => [],
  },
  colorLegends: {
    type: Array,
    default: () => [],
  },
  hasActiveFilters: {
    type: Boolean,
    default: false,
  },
  totalFilteredRooms: {
    type: Number,
    default: 0,
  },
  totalRawRooms: {
    type: Number,
    default: 0,
  },
})

defineEmits([
  'update:searchQuery',
  'update:selectedBlock',
  'update:selectedFloor',
  'update:selectedStatus',
  'update:floorSortOrder',
  'select-block',
  'select-status',
  'reset-filters',
])
</script>

<template>
  <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs mb-6 overflow-hidden">
    <!-- Top Section: Block Quick-Filter Pills Bar -->
    <div class="px-4 pt-3.5 pb-3 border-b border-slate-100 bg-slate-50/60">
      <div class="flex items-center justify-between gap-2 mb-2">
        <span class="text-xs font-bold text-slate-500 uppercase tracking-wider flex items-center gap-1.5">
          <v-icon icon="mdi-domain" size="15" color="primary" />
          <span>Phân khu & Tòa nhà (Block):</span>
        </span>
        <button
          v-if="selectedBlock"
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
          class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all duration-150 shrink-0 flex items-center gap-2 border cursor-pointer"
          :class="!selectedBlock
            ? 'bg-primary text-white border-primary shadow-xs'
            : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-100 hover:border-slate-300'"
          @click="$emit('select-block', '')"
        >
          <span>Tất cả Block</span>
          <span
            class="px-1.5 py-0.2 rounded-full text-[10px] font-extrabold"
            :class="!selectedBlock ? 'bg-white/25 text-white' : 'bg-slate-100 text-slate-600'"
          >
            {{ blockCounts.total || 0 }}
          </span>
        </button>

        <button
          v-for="b in existingBlocks"
          :key="b"
          type="button"
          class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all duration-150 shrink-0 flex items-center gap-2 border cursor-pointer"
          :class="selectedBlock === b
            ? 'bg-primary text-white border-primary shadow-xs'
            : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-100 hover:border-slate-300'"
          @click="$emit('select-block', b)"
        >
          <span>{{ b }}</span>
          <span
            class="px-1.5 py-0.2 rounded-full text-[10px] font-extrabold"
            :class="selectedBlock === b ? 'bg-white/25 text-white' : 'bg-slate-100 text-slate-600'"
          >
            {{ blockCounts[b] || 0 }}
          </span>
        </button>
      </div>
    </div>

    <!-- Bottom Section: Search, Floor, Status & Clickable Legends -->
    <div class="p-4 space-y-3.5">
      <div class="flex flex-col lg:flex-row items-stretch lg:items-center justify-between gap-3.5">
        <!-- Left: Search Box -->
        <div class="flex-1 min-w-[240px]">
          <v-text-field
            :model-value="searchQuery"
            placeholder="Tìm nhanh theo số phòng, tên phòng..."
            density="compact"
            variant="outlined"
            hide-details
            prepend-inner-icon="mdi-magnify"
            clearable
            bg-color="slate-50"
            @update:model-value="val => $emit('update:searchQuery', val || '')"
          />
        </div>

        <!-- Middle: Filters Group (Floor, Status) -->
        <div class="flex flex-wrap sm:flex-nowrap items-center gap-2.5">
          <!-- Floor Filter -->
          <div class="w-full sm:w-36 shrink-0">
            <v-select
              :model-value="selectedFloor"
              :items="[{ value: null, title: 'Tất cả Tầng' }, ...availableFloors.map(f => ({ value: f, title: `Tầng ${f}` }))]"
              item-title="title"
              item-value="value"
              density="compact"
              variant="outlined"
              hide-details
              prepend-inner-icon="mdi-stairs"
              @update:model-value="val => $emit('update:selectedFloor', val)"
            />
          </div>

          <!-- Floor Sort Direction Toggle Button -->
          <button
            type="button"
            class="px-2.5 py-2 text-xs font-semibold rounded-lg border border-slate-200 text-slate-700 bg-white hover:bg-slate-50 transition flex items-center gap-1.5 shrink-0 cursor-pointer shadow-xs"
            :title="floorSortOrder === 'asc' ? 'Đang sắp xếp Tầng 1 → 7 (Bấm để đảo 7 → 1)' : 'Đang sắp xếp Tầng 7 → 1 (Bấm để đảo 1 → 7)'"
            @click="$emit('update:floorSortOrder', floorSortOrder === 'asc' ? 'desc' : 'asc')"
          >
            <v-icon
              :icon="floorSortOrder === 'asc' ? 'mdi-sort-numeric-ascending' : 'mdi-sort-numeric-descending'"
              size="16"
              color="primary"
            />
            <span>Tầng {{ floorSortOrder === 'asc' ? '1 → 7' : '7 → 1' }}</span>
          </button>

          <!-- Status Filter -->
          <div class="w-full sm:w-52 shrink-0">
            <v-select
              :model-value="selectedStatus"
              :items="statusFilterOptions"
              item-title="label"
              item-value="value"
              density="compact"
              variant="outlined"
              hide-details
              prepend-inner-icon="mdi-shield-check-outline"
              @update:model-value="val => $emit('update:selectedStatus', val || '')"
            />
          </div>

          <!-- Reset Button -->
          <button
            v-if="hasActiveFilters"
            type="button"
            class="px-3 py-2 text-xs font-semibold rounded-lg border border-slate-200 text-slate-600 bg-slate-50 hover:bg-slate-100 hover:text-slate-900 transition flex items-center gap-1 shrink-0 cursor-pointer"
            title="Xóa tất cả bộ lọc đang áp dụng"
            @click="$emit('reset-filters')"
          >
            <v-icon icon="mdi-filter-off-outline" size="16" />
            <span>Đặt lại</span>
          </button>
        </div>

        <!-- Counter badge -->
        <div class="text-xs text-slate-500 font-medium whitespace-nowrap self-end lg:self-center">
          Tìm thấy <strong class="text-slate-800 text-sm font-bold">{{ totalFilteredRooms }}</strong> / {{ totalRawRooms }} phòng
        </div>
      </div>

      <!-- Interactive Color Legend Badges (Click to quick-filter!) -->
      <div class="pt-2 border-t border-slate-100 flex flex-wrap items-center gap-2 sm:gap-3 text-xs">
        <span class="text-slate-400 font-semibold mr-1">Tình trạng:</span>
        <button
          v-for="leg in colorLegends"
          :key="leg.key"
          type="button"
          class="px-2.5 py-1 rounded-lg border text-xs font-medium transition-all duration-150 flex items-center gap-1.5 cursor-pointer"
          :class="selectedStatus === leg.key
            ? `${leg.border} ${leg.text} bg-white ring-2 ring-primary shadow-xs font-bold`
            : 'border-slate-200/80 bg-slate-50 text-slate-600 hover:bg-white hover:border-slate-300'"
          @click="$emit('select-status', leg.key)"
        >
          <span class="w-2.5 h-2.5 rounded-full flex-shrink-0" :class="leg.color" />
          <span>{{ leg.label }}</span>
        </button>
      </div>
    </div>
  </div>
</template>
