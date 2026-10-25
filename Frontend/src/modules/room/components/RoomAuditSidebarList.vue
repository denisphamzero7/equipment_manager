<script setup>
import { computed } from 'vue'

const props = defineProps({
  rooms: {
    type: Array,
    default: () => [],
  },
  totalRooms: {
    type: Number,
    default: 0,
  },
  selectedRoomId: {
    type: [Number, String],
    default: null,
  },
})

const emit = defineEmits(['select-room', 'reset-filters'])

const quickSelectItems = computed(() => {
  return props.rooms.map(r => ({
    value: r.id,
    title: `Phòng ${r.room_number} (${r.block || 'Khu chính'})`,
  }))
})
</script>

<template>
  <div class="bg-white rounded-2xl p-4 sm:p-5 border border-slate-200/80 shadow-xs space-y-3.5 sticky top-20">
    <!-- Header -->
    <div class="flex items-center justify-between pb-2 border-b border-slate-100">
      <div>
        <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider flex items-center gap-1.5">
          <v-icon icon="mdi-format-list-bulleted" size="16" class="text-primary" />
          <span>Danh sách phòng</span>
        </h3>
        <p class="text-[11px] text-slate-400 mt-0.5">
          {{ rooms.length }} phòng hiển thị
        </p>
      </div>
      <v-chip size="x-small" color="primary" variant="tonal" class="font-bold font-mono">
        {{ rooms.length }}/{{ totalRooms }}
      </v-chip>
    </div>

    <!-- Quick select dropdown for mobile / fast jump -->
    <div>
      <v-select
        :model-value="selectedRoomId"
        :items="quickSelectItems"
        item-title="title"
        item-value="value"
        density="compact"
        variant="outlined"
        hide-details
        prepend-inner-icon="mdi-door"
        placeholder="Chọn nhanh phòng..."
        @update:model-value="val => emit('select-room', val)"
      />
    </div>

    <!-- Vertical Room Cards List -->
    <div class="space-y-1.5 max-h-[600px] overflow-y-auto pr-1 scrollbar-thin">
      <div v-if="rooms.length === 0" class="py-8 text-center text-slate-400 text-xs">
        <v-icon icon="mdi-filter-off-outline" size="32" class="text-slate-300 mb-1.5 block mx-auto" />
        Không tìm thấy phòng phù hợp.
        <button
          class="text-primary block mx-auto mt-2 font-bold hover:underline cursor-pointer"
          @click="emit('reset-filters')"
        >
          Đặt lại bộ lọc
        </button>
      </div>

      <button
        v-for="room in rooms"
        :key="room.id"
        type="button"
        class="w-full text-left p-3 rounded-xl text-xs transition-all duration-150 flex items-center justify-between border cursor-pointer group"
        :class="selectedRoomId === room.id
          ? 'bg-blue-50/90 text-blue-900 border-primary shadow-xs ring-2 ring-primary/20 font-bold'
          : 'bg-white hover:bg-slate-50 text-slate-700 border-slate-200/90 hover:border-slate-300'"
        @click="emit('select-room', room.id)"
      >
        <div class="min-w-0 pr-2">
          <div class="flex items-center gap-1.5">
            <v-icon
              icon="mdi-door"
              size="15"
              :class="selectedRoomId === room.id ? 'text-primary' : 'text-slate-400'"
            />
            <span class="text-sm font-black truncate">Phòng {{ room.room_number }}</span>
          </div>
          <div class="text-[11px] text-slate-500 mt-0.5 truncate">
            {{ room.block || 'Block A' }} • Tầng {{ room.floor ?? 1 }}
          </div>
        </div>

        <!-- Badge right indicator -->
        <div class="shrink-0 text-right">
          <span
            v-if="room.badge_color === 'green'"
            class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-200"
          >
            ✓ Đạt chuẩn
          </span>
          <span
            v-else-if="room.stats?.missing_count > 0"
            class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-100 text-rose-800 border border-rose-200"
          >
            Thiếu {{ room.stats.missing_count }}
          </span>
          <span
            v-else-if="room.stats?.broken_count > 0"
            class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-100 text-rose-800 border border-rose-200"
          >
            {{ room.stats.broken_count }} hỏng
          </span>
          <span
            v-else-if="room.stats?.maintenance_count > 0"
            class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800 border border-amber-200"
          >
            Bảo trì
          </span>
          <span
            v-else
            class="w-2.5 h-2.5 rounded-full inline-block"
            :class="room.badge_color === 'green' ? 'bg-emerald-500' : (room.badge_color === 'red' ? 'bg-rose-500' : 'bg-amber-500')"
          />
        </div>
      </button>
    </div>
  </div>
</template>
