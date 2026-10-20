<script setup>
import { computed } from 'vue'
import { EquipmentStatusMeta } from '@/core/enums/EquipmentStatus'
import { RoomStatusMeta } from '@/core/enums/RoomStatus'

const props = defineProps({
  status: {
    type: String,
    required: true,
  },
  type: {
    type: String,
    default: 'equipment', // equipment | room
  },
})

const meta = computed(() => {
  if (props.type === 'room') {
    return RoomStatusMeta[props.status] || {
      label: props.status,
      color: 'secondary',
      badgeClass: 'bg-slate-100 text-slate-700 border-slate-300',
      icon: 'mdi-help-circle',
    }
  }

  return EquipmentStatusMeta[props.status] || {
    label: props.status,
    color: 'secondary',
    badgeClass: 'bg-slate-100 text-slate-700 border-slate-300',
    icon: 'mdi-help-circle',
  }
})
</script>

<template>
  <span
    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold border transition-all"
    :class="meta.badgeClass"
  >
    <v-icon :icon="meta.icon" size="14" />
    <span>{{ meta.label }}</span>
  </span>
</template>
