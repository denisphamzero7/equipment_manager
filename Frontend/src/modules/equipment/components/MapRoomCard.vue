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
    return 'border-emerald-300 dark:border-emerald-700/60 bg-white dark:bg-slate-900 hover:border-emerald-500 hover:dark:border-emerald-500 hover:shadow-emerald-100 hover:dark:bg-slate-800/80'
  }
  if (room.badge_color === 'yellow' || room.equipment_status === 'maintenance') {
    return 'border-amber-300 dark:border-amber-700/60 bg-amber-50/40 dark:bg-amber-950/20 hover:border-amber-500 hover:dark:border-amber-500 hover:shadow-amber-100 hover:dark:bg-amber-950/40'
  }
  if (room.badge_color === 'red' || room.equipment_status === 'broken' || room.equipment_status === 'missing') {
    return 'border-rose-300 dark:border-rose-700/60 bg-rose-50/40 dark:bg-rose-950/20 hover:border-rose-500 hover:dark:border-rose-500 hover:shadow-rose-100 hover:dark:bg-rose-950/40'
  }
  return 'border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 hover:border-slate-400 hover:dark:border-slate-600 hover:dark:bg-slate-800/80'
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
      <span class="font-black text-sm sm:text-base text-slate-900 dark:text-slate-100 tracking-tight">
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
      <p class="text-[11px] sm:text-xs font-semibold text-slate-700 dark:text-slate-300 truncate" :title="room.name">
        {{ room.name || `Phòng ${room.room_number}` }}
      </p>
      <div class="flex items-center gap-1 mt-0.5 flex-wrap">
        <span class="inline-flex items-center gap-0.5 px-1.5 py-0.2 rounded bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 text-[9px] font-bold">
          {{ room.bedroom_count ?? 1 }}PN<template v-if="(room.living_room_count ?? 0) > 0"> + {{ room.living_room_count }}PK</template>
        </span>
        <span
          v-if="room.wifi_mode === 'shared'"
          class="inline-flex items-center gap-0.5 px-1 py-0.2 rounded text-[9px] font-bold"
          :class="room.shared_wifi_issue ? 'bg-rose-100 dark:bg-rose-950/80 text-rose-700 dark:text-rose-300 animate-pulse border border-rose-300 dark:border-rose-700' : 'bg-purple-100 dark:bg-purple-950/60 text-purple-700 dark:text-purple-300'"
          :title="room.shared_wifi_issue ? room.shared_wifi_issue.message : (room.shared_wifi_note || (room.shared_wifi_room ? `Dùng ké Wifi P.${room.shared_wifi_room.room_number}` : 'Dùng ké Wifi phòng bên cạnh'))"
        >
          <v-icon :icon="room.shared_wifi_issue ? 'mdi-wifi-alert' : 'mdi-wifi-arrow-left-right'" size="10" />
          {{ room.shared_wifi_issue ? 'Mất Wifi ké' : 'Ké Wifi' }}
        </span>
        <span
          v-else-if="room.dependent_rooms && room.dependent_rooms.length"
          class="inline-flex items-center gap-0.5 px-1 py-0.2 rounded bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 text-[9px] font-semibold border border-indigo-200 dark:border-indigo-800"
          :title="`Đang phát Wifi cho: ${room.dependent_rooms.map(r => 'P.' + r.room_number).join(', ')}`"
        >
          <v-icon icon="mdi-access-point-network" size="10" /> Cấp {{ room.dependent_rooms.length }}P
        </span>
      </div>
    </div>

    <!-- Bottom Devices Stats -->
    <div class="pt-1.5 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between text-[10px] sm:text-[11px]">
      <span class="text-slate-500 dark:text-slate-400 font-medium">
        {{ room.stats?.total_equipments || 0 }} TB
      </span>

      <div class="flex items-center gap-1">
        <span
          v-if="room.stats?.broken_count > 0"
          class="px-1.5 py-0.2 rounded bg-rose-100 dark:bg-rose-950/80 text-rose-700 dark:text-rose-300 font-bold"
          title="Thiết bị hỏng"
        >
          {{ room.stats.broken_count }}!
        </span>
        <span
          v-if="room.stats?.missing_count > 0"
          class="px-1.5 py-0.2 rounded bg-amber-100 dark:bg-amber-950/80 text-amber-800 dark:text-amber-300 font-bold"
          title="Thiết bị thiếu định mức"
        >
          -{{ room.stats.missing_count }}
        </span>
      </div>
    </div>
  </div>
</template>
