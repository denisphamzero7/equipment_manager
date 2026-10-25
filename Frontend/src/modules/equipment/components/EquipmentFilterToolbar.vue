<script setup>
import { HotelBlockOrder } from '@/core/enums'

defineProps({
  filters: {
    type: Object,
    required: true,
  },
  blockCounts: {
    type: Object,
    default: () => ({}),
  },
  hotelBlockOrder: {
    type: Array,
    default: () => HotelBlockOrder,
  },
  statusOptions: {
    type: Array,
    default: () => [],
  },
  categoryOptions: {
    type: Array,
    default: () => [],
  },
  hasActiveFilters: {
    type: Boolean,
    default: false,
  },
})

defineEmits(['select-block', 'reset-filters', 'search-change', 'filter-change'])
</script>

<template>
  <div>
    <!-- 1. Modern Block Quick-Filter Pills Bar -->
    <div class="bg-white border border-slate-200/80 rounded-2xl shadow-xs overflow-hidden mb-4">
      <div class="px-4 py-2.5 bg-slate-50/60 border-b border-slate-100 flex items-center justify-between gap-2">
        <span class="text-xs font-bold text-slate-500 uppercase tracking-wider flex items-center gap-1.5">
          <v-icon icon="mdi-domain" size="15" color="primary" />
          <span>Phân khu & Vị trí (Block):</span>
        </span>
        <button
          v-if="filters.block"
          type="button"
          class="text-xs text-primary font-semibold hover:underline flex items-center gap-1 cursor-pointer"
          @click="$emit('select-block', '')"
        >
          <v-icon icon="mdi-eye-outline" size="14" />
          <span>Hiển thị tất cả vị trí</span>
        </button>
      </div>

      <!-- Horizontal Pills Scroll -->
      <div class="p-3 flex items-center gap-2 overflow-x-auto scrollbar-thin">
        <button
          type="button"
          class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all duration-150 shrink-0 flex items-center gap-2 border cursor-pointer"
          :class="!filters.block
            ? 'bg-primary text-white border-primary shadow-xs'
            : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-100 hover:border-slate-300'"
          @click="$emit('select-block', '')"
        >
          <span>Tất cả vị trí</span>
          <span
            class="px-1.5 py-0.2 rounded-full text-[10px] font-extrabold"
            :class="!filters.block ? 'bg-white/25 text-white' : 'bg-slate-100 text-slate-600'"
          >
            {{ blockCounts.total || 0 }}
          </span>
        </button>

        <button
          v-for="b in hotelBlockOrder"
          :key="b"
          type="button"
          class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all duration-150 shrink-0 flex items-center gap-2 border cursor-pointer"
          :class="filters.block === b
            ? 'bg-primary text-white border-primary shadow-xs'
            : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-100 hover:border-slate-300'"
          @click="$emit('select-block', b)"
        >
          <v-icon icon="mdi-domain" size="13" />
          <span>{{ b }}</span>
          <span
            class="px-1.5 py-0.2 rounded-full text-[10px] font-extrabold"
            :class="filters.block === b ? 'bg-white/25 text-white' : 'bg-slate-100 text-slate-600'"
          >
            {{ blockCounts[b] || 0 }}
          </span>
        </button>

        <!-- Pill: Thiết bị đang trong kho -->
        <button
          type="button"
          class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all duration-150 shrink-0 flex items-center gap-2 border cursor-pointer ml-auto"
          :class="filters.block === '__stock__'
            ? 'bg-sky-600 text-white border-sky-600 shadow-xs'
            : 'bg-sky-50 text-sky-700 border-sky-200 hover:bg-sky-100'"
          @click="$emit('select-block', '__stock__')"
        >
          <v-icon icon="mdi-package-variant-closed" size="14" />
          <span>Trong kho</span>
          <span
            class="px-1.5 py-0.2 rounded-full text-[10px] font-extrabold"
            :class="filters.block === '__stock__' ? 'bg-white/25 text-white' : 'bg-sky-100 text-sky-800'"
          >
            {{ blockCounts.__stock__ || 0 }}
          </span>
        </button>
      </div>
    </div>

    <!-- 2. Detailed Filter Row (Search, Status, Floor, Room Number, Category) -->
    <div class="bg-white rounded-2xl p-4 border border-slate-200/80 shadow-xs mb-6">
      <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 items-center">
        <!-- 1. Search input -->
        <div class="lg:col-span-2">
          <v-text-field
            v-model="filters.search"
            placeholder="Tìm theo Serial, tên thiết bị, phòng..."
            prepend-inner-icon="mdi-magnify"
            clearable
            hide-details
            density="compact"
            variant="outlined"
            @update:model-value="val => $emit('search-change', val)"
          />
        </div>

        <!-- 2. Status filter -->
        <div>
          <v-select
            v-model="filters.status"
            :items="[{ value: '', title: 'Tất cả trạng thái' }, ...statusOptions]"
            item-title="title"
            item-value="value"
            placeholder="Trạng thái"
            hide-details
            density="compact"
            variant="outlined"
            prepend-inner-icon="mdi-shield-check-outline"
            @update:model-value="$emit('filter-change')"
          />
        </div>

        <!-- 3. Floor filter -->
        <div>
          <v-select
            v-model="filters.floor"
            :items="[
              { value: null, title: 'Tất cả Tầng' },
              { value: 1, title: 'Tầng 1' },
              { value: 2, title: 'Tầng 2' },
              { value: 3, title: 'Tầng 3' },
              { value: 4, title: 'Tầng 4' },
              { value: 5, title: 'Tầng 5' },
              { value: 6, title: 'Tầng 6' },
              { value: 7, title: 'Tầng 7' },
            ]"
            item-title="title"
            item-value="value"
            placeholder="Tầng"
            hide-details
            density="compact"
            variant="outlined"
            prepend-inner-icon="mdi-stairs"
            :disabled="filters.block === '__stock__'"
            @update:model-value="$emit('filter-change')"
          />
        </div>

        <!-- 4. Room Number filter -->
        <div>
          <v-text-field
            v-model="filters.room_number"
            placeholder="Số phòng (vd: 122)"
            prepend-inner-icon="mdi-door"
            clearable
            hide-details
            density="compact"
            variant="outlined"
            :disabled="filters.block === '__stock__'"
            @update:model-value="val => $emit('search-change', val)"
          />
        </div>

        <!-- 5. Category filter -->
        <div>
          <v-select
            v-model="filters.equipment_category_id"
            :items="[{ id: null, name: 'Tất cả danh mục' }, ...categoryOptions]"
            item-title="name"
            item-value="id"
            placeholder="Danh mục"
            hide-details
            density="compact"
            variant="outlined"
            prepend-inner-icon="mdi-tag-outline"
            @update:model-value="$emit('filter-change')"
          />
        </div>
      </div>

      <!-- Active filters tags bar & Reset button -->
      <div v-if="hasActiveFilters" class="mt-3 pt-3 border-t border-slate-100 flex items-center justify-between flex-wrap gap-2 text-xs">
        <div class="flex items-center gap-2 flex-wrap">
          <span class="text-slate-400 font-medium">Đang áp dụng bộ lọc:</span>
          <span v-if="filters.block" class="px-2 py-0.5 rounded-md bg-blue-50 text-primary font-bold border border-blue-200">
            Khu vực: {{ filters.block === '__stock__' ? 'Trong kho' : filters.block }}
          </span>
          <span v-if="filters.floor" class="px-2 py-0.5 rounded-md bg-slate-100 text-slate-700 font-bold">
            Tầng: {{ filters.floor }}
          </span>
          <span v-if="filters.room_number" class="px-2 py-0.5 rounded-md bg-slate-100 text-slate-700 font-bold">
            Phòng: {{ filters.room_number }}
          </span>
          <span v-if="filters.status" class="px-2 py-0.5 rounded-md bg-slate-100 text-slate-700 font-bold">
            Trạng thái: {{ filters.status }}
          </span>
          <span v-if="filters.search" class="px-2 py-0.5 rounded-md bg-slate-100 text-slate-700 font-medium">
            Từ khóa: "{{ filters.search }}"
          </span>
        </div>

        <button
          type="button"
          class="px-3 py-1.5 text-xs font-semibold rounded-lg border border-slate-200 text-slate-600 bg-slate-50 hover:bg-slate-100 hover:text-slate-900 transition flex items-center gap-1.5 cursor-pointer ml-auto"
          title="Đặt lại toàn bộ bộ lọc"
          @click="$emit('reset-filters')"
        >
          <v-icon icon="mdi-filter-off-outline" size="14" />
          <span>Đặt lại bộ lọc</span>
        </button>
      </div>
    </div>
  </div>
</template>
