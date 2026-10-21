<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { equipmentService } from '@/modules/equipment/services/equipmentService'

const props = defineProps({
  modelValue: {
    type: [Number, String, null],
    default: null,
  },
  label: {
    type: String,
    default: 'Phòng / Vị trí đặt thiết bị',
  },
  allowWarehouse: {
    type: Boolean,
    default: true,
  },
  required: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['update:modelValue', 'change'])

const loading = ref(false)
const rooms = ref([])
const selectedBlock = ref('')
const selectedFloor = ref('')
const internalRoomId = ref(null)

onMounted(async () => {
  await loadRooms()
  syncWithModelValue(props.modelValue)
})

watch(
  () => props.modelValue,
  (newVal) => {
    syncWithModelValue(newVal)
  }
)

async function loadRooms() {
  loading.value = true
  try {
    const data = await equipmentService.getRoomOptions()
    rooms.value = data || []
  } catch (err) {
    console.error('Failed to load room options for cascader:', err)
  } finally {
    loading.value = false
  }
}

function syncWithModelValue(val) {
  if (val === null || val === undefined || val === '') {
    internalRoomId.value = null
    return
  }
  const idNum = Number(val)
  internalRoomId.value = idNum

  const found = rooms.value.find(r => r.id === idNum)
  if (found) {
    selectedBlock.value = found.block
    selectedFloor.value = found.floor
  }
}

// Block options list
const blockOptions = computed(() => {
  const set = new Set()
  rooms.value.forEach(r => {
    if (r.block) set.add(r.block)
  })
  return Array.from(set).map(b => ({ title: b, value: b }))
})

// Floor options list (filtered by selected block if any)
const floorOptions = computed(() => {
  const filtered = selectedBlock.value
    ? rooms.value.filter(r => r.block === selectedBlock.value)
    : rooms.value

  const set = new Set()
  filtered.forEach(r => {
    if (r.floor !== null && r.floor !== undefined) set.add(r.floor)
  })
  return Array.from(set)
    .sort((a, b) => a - b)
    .map(f => ({ title: `Tầng ${f}`, value: f }))
})

// Filtered rooms based on Block and Floor
const filteredRooms = computed(() => {
  return rooms.value
    .filter(r => {
      if (selectedBlock.value && r.block !== selectedBlock.value) return false
      if (selectedFloor.value !== '' && selectedFloor.value !== null && r.floor !== selectedFloor.value) return false
      return true
    })
    .map(r => ({
      ...r,
      displayTitle: `Phòng ${r.room_number} • ${r.block} - T${r.floor} (${r.room_type})`,
    }))
})

// Currently selected room object
const selectedRoomObj = computed(() => {
  if (!internalRoomId.value) return null
  return rooms.value.find(r => r.id === internalRoomId.value) || null
})

function onBlockChange() {
  // If current floor not in block, reset floor
  if (selectedFloor.value !== '') {
    const validFloor = rooms.value.some(r => r.block === selectedBlock.value && r.floor === selectedFloor.value)
    if (!validFloor) selectedFloor.value = ''
  }
}

function onFloorChange() {
  // If room is selected but not in this floor/block, clear it
  if (internalRoomId.value && selectedRoomObj.value) {
    if (selectedFloor.value !== '' && selectedRoomObj.value.floor !== selectedFloor.value) {
      internalRoomId.value = null
      emit('update:modelValue', null)
      emit('change', null)
    }
  }
}

function onRoomSelected(val) {
  if (val) {
    const found = rooms.value.find(r => r.id === val)
    if (found) {
      selectedBlock.value = found.block
      selectedFloor.value = found.floor
    }
  }
  emit('update:modelValue', val ? Number(val) : null)
  emit('change', selectedRoomObj.value)
}

function selectWarehouse() {
  internalRoomId.value = null
  emit('update:modelValue', null)
  emit('change', null)
}

function startSelectingRoom() {
  // Default pick the first available room or let user pick
  if (filteredRooms.value.length > 0) {
    const first = filteredRooms.value[0]
    onRoomSelected(first.id)
  } else if (rooms.value.length > 0) {
    onRoomSelected(rooms.value[0].id)
  }
}

function clearSelection() {
  selectWarehouse()
}
</script>

<template>
  <div class="space-y-2.5">
    <!-- Header with label and Warehouse quick button -->
    <div class="flex items-center justify-between">
      <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">
        {{ label }} <span v-if="required" class="text-rose-500">*</span>
      </label>
      <button
        v-if="allowWarehouse && internalRoomId !== null"
        type="button"
        class="text-[11px] text-blue-600 hover:text-blue-800 font-semibold flex items-center gap-1 transition"
        @click="selectWarehouse"
      >
        <v-icon icon="mdi-package-variant-closed" size="14" />
        Lưu ở kho (Không gán vào phòng)
      </button>
    </div>

    <!-- Warehouse state banner (if room_id is null) -->
    <div
      v-if="allowWarehouse && internalRoomId === null"
      class="p-3 bg-amber-50/80 border border-amber-200 rounded-xl flex items-center justify-between text-xs"
    >
      <div class="flex items-center gap-2.5 text-amber-900 font-medium">
        <v-icon icon="mdi-package-variant-closed" color="warning" size="20" />
        <span>Thiết bị đang ở <strong>Kho tổng</strong> (Chưa gán vào phòng)</span>
      </div>
      <button
        type="button"
        class="px-2.5 py-1 rounded-lg bg-white border border-amber-300 text-amber-900 text-xs font-bold hover:bg-amber-100 transition shadow-2xs"
        @click="startSelectingRoom"
      >
        Chọn phòng gán
      </button>
    </div>

    <!-- Cascading (Bậc thang) Selector: Block -> Floor -> Room -->
    <div v-else class="space-y-2">
      <!-- Active selection preview -->
      <div
        v-if="selectedRoomObj"
        class="p-2.5 bg-blue-50/80 border border-blue-200 rounded-xl flex items-center justify-between text-xs"
      >
        <div class="flex items-center gap-2.5 min-w-0">
          <div class="w-8 h-8 rounded-lg bg-blue-600 text-white flex items-center justify-center font-bold text-xs shrink-0 shadow-2xs">
            <v-icon icon="mdi-door-open" size="18" />
          </div>
          <div class="truncate">
            <p class="font-bold text-blue-900">
              Phòng {{ selectedRoomObj.room_number }}
              <span class="font-normal text-blue-700 ml-1">({{ selectedRoomObj.room_type }})</span>
            </p>
            <p class="text-[11px] text-blue-600 font-medium">
              {{ selectedRoomObj.block }} • Tầng {{ selectedRoomObj.floor }}
            </p>
          </div>
        </div>

        <button
          v-if="allowWarehouse"
          type="button"
          class="p-1 rounded-md text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition"
          title="Thu hồi về kho"
          @click="clearSelection"
        >
          <v-icon icon="mdi-close" size="16" />
        </button>
      </div>

      <!-- Tier 1, 2, 3 selection grid -->
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
        <!-- Bậc 1: Block -->
        <div>
          <label class="block text-[11px] font-bold text-slate-500 mb-1">
            Bậc 1: Block / Tòa
          </label>
          <v-select
            v-model="selectedBlock"
            :items="[{ title: 'Tất cả Block', value: '' }, ...blockOptions]"
            item-title="title"
            item-value="value"
            density="compact"
            variant="outlined"
            hide-details
            placeholder="Tất cả Block"
            @update:model-value="onBlockChange"
          />
        </div>

        <!-- Bậc 2: Tầng -->
        <div>
          <label class="block text-[11px] font-bold text-slate-500 mb-1">
            Bậc 2: Tầng
          </label>
          <v-select
            v-model="selectedFloor"
            :items="[{ title: 'Tất cả Tầng', value: '' }, ...floorOptions]"
            item-title="title"
            item-value="value"
            density="compact"
            variant="outlined"
            hide-details
            placeholder="Tất cả Tầng"
            @update:model-value="onFloorChange"
          />
        </div>

        <!-- Bậc 3: Chọn Phòng -->
        <div>
          <label class="block text-[11px] font-bold text-slate-500 mb-1">
            Bậc 3: Chọn Phòng *
          </label>
          <v-autocomplete
            v-model="internalRoomId"
            :items="filteredRooms"
            item-title="displayTitle"
            item-value="id"
            density="compact"
            variant="outlined"
            hide-details
            placeholder="Tìm & chọn phòng..."
            no-data-text="Không tìm thấy phòng"
            @update:model-value="onRoomSelected"
          />
        </div>
      </div>
    </div>
  </div>
</template>
