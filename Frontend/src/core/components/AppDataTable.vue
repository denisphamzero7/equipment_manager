<script setup>
defineProps({
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
})

const emit = defineEmits(['update:page', 'update:itemsPerPage', 'click:row'])
</script>

<template>
  <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden flex flex-col">
    <!-- Table Content -->
    <div class="overflow-x-auto">
      <table class="w-full text-left text-sm text-slate-700">
        <thead class="bg-slate-50/80 text-xs uppercase font-semibold text-slate-500 border-b border-slate-200">
          <tr>
            <th
              v-for="head in headers"
              :key="head.key"
              class="px-5 py-3.5 tracking-wider"
              :class="head.align === 'center' ? 'text-center' : head.align === 'end' ? 'text-right' : 'text-left'"
              :style="head.width ? { width: head.width } : {}"
            >
              {{ head.title }}
            </th>
          </tr>
        </thead>

        <tbody v-if="loading" class="divide-y divide-slate-100">
          <tr v-for="n in 5" :key="n" class="animate-pulse">
            <td v-for="head in headers" :key="head.key" class="px-5 py-4">
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
            <td
              v-for="head in headers"
              :key="head.key"
              class="px-5 py-3.5 align-middle"
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
            <td :colspan="headers.length" class="text-center py-12 text-slate-400">
              <v-icon icon="mdi-database-off-outline" size="48" class="text-slate-300 mb-2 block mx-auto" />
              <p class="text-sm font-medium">{{ emptyText }}</p>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Table Pagination Footer -->
    <div
      v-if="totalItems > 0"
      class="px-5 py-3 bg-slate-50/50 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-slate-500"
    >
      <div>
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
        <span class="px-3 py-1 font-semibold text-slate-700 bg-white border border-slate-200 rounded-lg">
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
