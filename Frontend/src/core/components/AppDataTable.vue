<script setup>
import { computed } from 'vue'

const props = defineProps({
  headers: {
    type: Array,
    required: true,
  },
  items: {
    type: Array,
    default: () => [],
  },
  loading: {
    type: Boolean,
    default: false,
  },
  totalItems: {
    type: Number,
    default: 0,
  },
  page: {
    type: Number,
    default: 1,
  },
  itemsPerPage: {
    type: Number,
    default: 15,
  },
  emptyText: {
    type: String,
    default: 'Chưa có dữ liệu phù hợp.',
  },
  showSelect: {
    type: Boolean,
    default: false,
  },
  modelValue: {
    type: Array,
    default: () => [],
  },
  itemValue: {
    type: String,
    default: 'id',
  },
})

const emit = defineEmits(['update:page', 'update:itemsPerPage', 'click:row', 'update:modelValue'])

const isSelected = (item) => {
  const val = item[props.itemValue] ?? item.id
  return props.modelValue.includes(val)
}

const isAllSelected = computed(() => {
  if (!props.items.length) return false
  return props.items.every(item => isSelected(item))
})

const isIndeterminate = computed(() => {
  if (!props.items.length) return false
  const selectedCount = props.items.filter(item => isSelected(item)).length
  return selectedCount > 0 && selectedCount < props.items.length
})

function toggleSelectAll(event) {
  if (event.target.checked) {
    const allIds = Array.from(new Set([
      ...props.modelValue,
      ...props.items.map(i => i[props.itemValue] ?? i.id),
    ]))
    emit('update:modelValue', allIds)
  } else {
    const currentItemIds = new Set(props.items.map(i => i[props.itemValue] ?? i.id))
    const remaining = props.modelValue.filter(id => !currentItemIds.has(id))
    emit('update:modelValue', remaining)
  }
}

function toggleSelectItem(item) {
  const val = item[props.itemValue] ?? item.id
  const next = [...props.modelValue]
  const idx = next.indexOf(val)
  if (idx > -1) {
    next.splice(idx, 1)
  } else {
    next.push(val)
  }
  emit('update:modelValue', next)
}
</script>

<template>
  <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden flex flex-col">
    <!-- Table Content (Horizontal scroll wrapper for mobile) -->
    <div class="overflow-x-auto touch-pan-x">
      <table class="w-full text-left text-sm text-slate-700 min-w-[620px] sm:min-w-full">
        <thead class="bg-slate-50/80 text-xs uppercase font-semibold text-slate-500 border-b border-slate-200">
          <tr>
            <th v-if="showSelect" class="w-10 px-3.5 sm:px-5 py-3 text-center">
              <input
                type="checkbox"
                :checked="isAllSelected"
                :indeterminate.prop="isIndeterminate"
                class="rounded border-slate-300 text-primary focus:ring-primary w-4 h-4 cursor-pointer"
                @change="toggleSelectAll"
              />
            </th>
            <th
              v-for="head in headers"
              :key="head.key"
              class="px-3.5 sm:px-5 py-3 tracking-wider whitespace-nowrap"
              :class="head.align === 'center' ? 'text-center' : head.align === 'end' ? 'text-right' : 'text-left'"
              :style="head.width ? { width: head.width } : {}"
            >
              {{ head.title }}
            </th>
          </tr>
        </thead>

        <tbody v-if="loading" class="divide-y divide-slate-100">
          <tr v-for="n in 5" :key="n" class="animate-pulse">
            <td v-if="showSelect" class="px-3.5 sm:px-5 py-3.5 text-center">
              <div class="h-4 w-4 bg-slate-200 rounded mx-auto" />
            </td>
            <td v-for="head in headers" :key="head.key" class="px-3.5 sm:px-5 py-3.5">
              <div class="h-4 bg-slate-200 rounded w-3/4" />
            </td>
          </tr>
        </tbody>

        <tbody v-else-if="items.length > 0" class="divide-y divide-slate-100">
          <tr
            v-for="(item, idx) in items"
            :key="item.id || idx"
            class="hover:bg-slate-50/70 transition-colors cursor-pointer"
            @click="emit('click:row', item)"
          >
            <td v-if="showSelect" class="px-3.5 sm:px-5 py-3 text-center" @click.stop>
              <input
                type="checkbox"
                :checked="isSelected(item)"
                class="rounded border-slate-300 text-primary focus:ring-primary w-4 h-4 cursor-pointer"
                @change="toggleSelectItem(item)"
              />
            </td>
            <td
              v-for="head in headers"
              :key="head.key"
              class="px-3.5 sm:px-5 py-3 align-middle"
              :class="head.align === 'center' ? 'text-center' : head.align === 'end' ? 'text-right' : 'text-left'"
            >
              <slot :name="`item.${head.key}`" :item="item" :index="idx">
                {{ item[head.key] ?? '—' }}
              </slot>
            </td>
          </tr>
        </tbody>

        <tbody v-else>
          <tr>
            <td :colspan="headers.length + (showSelect ? 1 : 0)" class="text-center py-12 text-slate-400">
              <v-icon icon="mdi-database-off-outline" size="44" class="text-slate-300 mb-2 block mx-auto" />
              <p class="text-sm font-medium">{{ emptyText }}</p>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Table Pagination Footer -->
    <div
      v-if="totalItems > 0"
      class="px-3.5 sm:px-5 py-3 bg-slate-50/50 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-2.5 sm:gap-3 text-xs text-slate-500"
    >
      <div class="text-center sm:text-left">
        Hiển thị <strong>{{ (page - 1) * itemsPerPage + (items.length ? 1 : 0) }}</strong>
        đến <strong>{{ Math.min(page * itemsPerPage, totalItems) }}</strong> trong tổng số <strong>{{ totalItems }}</strong> bản ghi
      </div>

      <div class="flex items-center gap-1">
        <v-btn
          icon="mdi-chevron-left"
          variant="tonal"
          density="comfortable"
          size="small"
          :disabled="page <= 1"
          @click="emit('update:page', page - 1)"
        />
        <span class="px-2.5 py-1 font-semibold text-slate-700 bg-white border border-slate-200 rounded-lg text-xs">
          Trang {{ page }} / {{ Math.ceil(totalItems / itemsPerPage) || 1 }}
        </span>
        <v-btn
          icon="mdi-chevron-right"
          variant="tonal"
          density="comfortable"
          size="small"
          :disabled="page >= Math.ceil(totalItems / itemsPerPage)"
          @click="emit('update:page', page + 1)"
        />
      </div>
    </div>
  </div>
</template>
