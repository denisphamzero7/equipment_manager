<script setup>
import { computed } from 'vue'

const props = defineProps({
  room: {
    type: Object,
    required: true,
  },
  compact: {
    type: Boolean,
    default: false,
  },
})

const items = computed(() => {
  const summary = Array.isArray(props.room.equipment_summary)
    ? props.room.equipment_summary
    : []
  const equipment = summary.map(item => ({
    name: item.name,
    quantity: Number(item.quantity) || 0,
    shared: false,
  }))

  if (props.room.wifi_mode === 'shared') {
    equipment.push({ name: 'WiFi (dùng ké)', quantity: 1, shared: true })
  }

  return equipment.filter(item => item.quantity > 0)
})
</script>

<template>
  <div
    v-if="items.length"
    class="flex flex-wrap gap-1"
    :class="compact ? 'mt-1' : 'mt-1.5'"
    aria-label="Số lượng thiết bị theo mẫu"
  >
    <span
      v-for="item in items"
      :key="`${item.name}-${item.shared ? 'shared' : 'assigned'}`"
      class="inline-flex max-w-full items-center gap-1 rounded-md border px-1.5 py-0.5 text-[10px] leading-4"
      :class="item.shared
        ? 'border-purple-200 bg-purple-50 text-purple-700'
        : 'border-slate-200 bg-white text-slate-700'"
      :title="`${item.name}: ${item.quantity}`"
    >
      <span class="truncate">{{ item.name }}</span>
      <strong class="shrink-0">{{ item.quantity }}</strong>
    </span>
  </div>
  <span
    v-else
    class="mt-1 block text-[10px] text-slate-400"
  >
    Chưa có thiết bị được gán
  </span>
</template>
