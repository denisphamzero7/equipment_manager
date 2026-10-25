<script setup>
import MapRoomCard from './MapRoomCard.vue'

defineProps({
  groupedRooms: {
    type: Array,
    required: true,
  },
  selectedBlock: {
    type: String,
    default: '',
  },
})

defineEmits(['select-block', 'select-room'])
</script>

<template>
  <div class="space-y-6">
    <div
      v-for="blockGroup in groupedRooms"
      :key="blockGroup.name"
      class="space-y-4"
    >
      <!-- Block Header Title -->
      <div class="flex items-center justify-between pb-2 border-b border-slate-200">
        <div class="flex items-center gap-2">
          <span class="w-2.5 h-6 rounded-full bg-primary inline-block"></span>
          <h2 class="text-base sm:text-lg font-bold text-slate-800">{{ blockGroup.name }}</h2>
          <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-slate-100 text-slate-600">
            {{ blockGroup.totalRooms }} phòng
          </span>
        </div>
        <button
          v-if="selectedBlock !== blockGroup.name"
          type="button"
          class="text-xs text-primary font-semibold hover:underline flex items-center gap-1 cursor-pointer"
          @click="$emit('select-block', blockGroup.name)"
        >
          <span>Chỉ xem {{ blockGroup.name }}</span>
          <v-icon icon="mdi-chevron-right" size="14" />
        </button>
      </div>

      <!-- Floors in Block -->
      <div
        v-for="floorGroup in blockGroup.floors"
        :key="floorGroup.floor"
        class="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-xs space-y-3"
      >
        <!-- Floor Header -->
        <div class="flex items-center justify-between border-b border-slate-100 pb-2">
          <div class="flex items-center gap-2">
            <v-icon icon="mdi-stairs" color="primary" size="18" />
            <span class="font-bold text-slate-800 text-xs sm:text-sm">Tầng {{ floorGroup.floor }}</span>
            <span class="text-xs text-slate-400">({{ floorGroup.rooms.length }} phòng)</span>
          </div>
        </div>

        <!-- Rooms Grid for this floor -->
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 2xl:grid-cols-7 gap-2.5 sm:gap-3.5">
          <MapRoomCard
            v-for="room in floorGroup.rooms"
            :key="room.id"
            :room="room"
            @click="r => $emit('select-room', r)"
          />
        </div>
      </div>
    </div>
  </div>
</template>
