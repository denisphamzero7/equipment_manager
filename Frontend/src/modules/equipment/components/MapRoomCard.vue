<script setup>
defineProps({
  room: {
    type: Object,
    required: true,
  },
})

defineEmits(['click'])

function getRoomColorClass(room) {
  if (room.badge_color === 'green' || room.equipment_status === 'good') {
    return 'border-emerald-300 bg-white hover:border-emerald-500 hover:shadow-emerald-100'
  }
  if (room.badge_color === 'yellow' || room.equipment_status === 'maintenance') {
    return 'border-amber-300 bg-amber-50/40 hover:border-amber-500 hover:shadow-amber-100'
  }
  if (room.badge_color === 'red' || room.equipment_status === 'broken' || room.equipment_status === 'missing') {
    return 'border-rose-300 bg-rose-50/40 hover:border-rose-500 hover:shadow-rose-100'
  }
  return 'border-slate-200 bg-white hover:border-slate-400'
}

function getBadgeIndicatorClass(room) {
  if (room.badge_color === 'green') return 'bg-emerald-500'
  if (room.badge_color === 'yellow') return 'bg-amber-500'
  if (room.badge_color === 'red') return 'bg-rose-500'
  return 'bg-slate-400'
}
</script>

<template>
  <div
    class="rounded-xl p-3 sm:p-3.5 border shadow-2xs hover:shadow-md transition-all cursor-pointer flex flex-col justify-between min-h-[136px] relative overflow-hidden active:scale-[0.98]"
    :class="getRoomColorClass(room)"
    @click="$emit('click', room)"
  >
    <!-- Top Status Bar Indicator -->
    <div class="flex items-center justify-between">
      <span class="font-black text-sm sm:text-base text-slate-900 tracking-tight">
        {{ room.room_number }}
      </span>
      <span
        class="w-2.5 h-2.5 rounded-full shadow-xs flex-shrink-0"
        :class="getBadgeIndicatorClass(room)"
        :title="room.equipment_status"
      />
    </div>

    <!-- Center Room Name / Floor / Layout -->
    <div class="my-auto py-1">
      <p class="text-[11px] sm:text-xs font-semibold text-slate-700 truncate" :title="room.name">
        {{ room.name || `Phòng ${room.room_number}` }}
      </p>
      <div class="flex items-center gap-1 mt-0.5 flex-wrap">
        <span class="inline-flex items-center gap-0.5 px-1.5 py-0.2 rounded bg-slate-100 text-slate-700 text-[9px] font-bold">
          {{ room.bedroom_count ?? 1 }}PN<template v-if="(room.living_room_count ?? 0) > 0"> + {{ room.living_room_count }}PK</template>
        </span>
        <span
          v-if="room.wifi_mode === 'shared'"
          class="inline-flex items-center gap-0.5 px-1 py-0.2 rounded bg-purple-100 text-purple-700 text-[9px] font-semibold"
          :title="room.shared_wifi_note || (room.shared_wifi_room ? `Dùng ké Wifi P.${room.shared_wifi_room.room_number}` : 'Dùng ké Wifi phòng bên cạnh')"
        >
          <v-icon icon="mdi-wifi-arrow-left-right" size="10" /> Ké Wifi
        </span>
      </div>
    </div>

    <!-- Bottom Devices Stats -->
    <div class="pt-1.5 border-t border-slate-100 flex items-center justify-between text-[10px] sm:text-[11px]">
      <span class="text-slate-500 font-medium">
        {{ room.stats?.total_equipments || 0 }} TB
      </span>

      <div class="flex items-center gap-1">
        <span
          v-if="room.stats?.broken_count > 0"
          class="px-1.5 py-0.2 rounded bg-rose-100 text-rose-700 font-bold"
          title="Thiết bị hỏng"
        >
          {{ room.stats.broken_count }}!
        </span>
        <span
          v-if="room.stats?.missing_count > 0"
          class="px-1.5 py-0.2 rounded bg-amber-100 text-amber-800 font-bold"
          title="Thiết bị thiếu định mức"
        >
          -{{ room.stats.missing_count }}
        </span>
      </div>
    </div>
  </div>
</template>
