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
const isWarehouseMode = ref(false)

onMounted(async () => {
  await loadRooms()
  syncWithModelValue(props.modelValue)
})

watch(
  () => props.modelValue,
  (newVal) => {
    if (newVal === null || newVal === undefined || newVal === '') {
      internalRoomId.value = null
      if (props.allowWarehouse && !selectedBlock.value && selectedFloor.value === '') {
        isWarehouseMode.value = true
      }
    } else {
      const idNum = Number(newVal)
      if (internalRoomId.value !== idNum) {
        syncWithModelValue(idNum)
      }
    }
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
    if (props.allowWarehouse) {
      isWarehouseMode.value = true
    }
    return
  }
  const idNum = Number(val)
  internalRoomId.value = idNum
  isWarehouseMode.value = false

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
  // If current floor is not in the new block, reset floor
  if (selectedFloor.value !== '') {
    const validFloor = rooms.value.some(r => (!selectedBlock.value || r.block === selectedBlock.value) && r.floor === selectedFloor.value)
    if (!validFloor) selectedFloor.value = ''
  }
  validateCurrentRoom()
}

function onFloorChange() {
  validateCurrentRoom()
}

function validateCurrentRoom() {
  if (!internalRoomId.value) return
  const current = rooms.value.find(r => r.id === internalRoomId.value)
  if (!current) {
    internalRoomId.value = null
    emit('update:modelValue', null)
    emit('change', null)
    return
  }

  const blockMismatch = selectedBlock.value !== '' && current.block !== selectedBlock.value
  const floorMismatch = selectedFloor.value !== '' && selectedFloor.value !== null && current.floor !== selectedFloor.value

  if (blockMismatch || floorMismatch) {
    internalRoomId.value = null
    emit('update:modelValue', null)
    emit('change', null)
  }
}

function onRoomSelected(val) {
  const idNum = val ? Number(val) : null
  internalRoomId.value = idNum
  if (idNum) {
    const found = rooms.value.find(r => r.id === idNum)
    if (found) {
      selectedBlock.value = found.block
      selectedFloor.value = found.floor
    }
  }
  emit('update:modelValue', idNum)
  emit('change', selectedRoomObj.value)
}

function selectWarehouse() {
  isWarehouseMode.value = true
  internalRoomId.value = null
  selectedBlock.value = ''
  selectedFloor.value = ''
  emit('update:modelValue', null)
  emit('change', null)
}

function startSelectingRoom() {
  isWarehouseMode.value = false
  internalRoomId.value = null
  emit('update:modelValue', null)
  emit('change', null)
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
        v-if="allowWarehouse && !isWarehouseMode"
        type="button"
        class="text-[11px] text-blue-600 hover:text-blue-800 font-semibold flex items-center gap-1 transition"
        @click="selectWarehouse"
      >
        <v-icon icon="mdi-package-variant-closed" size="14" />
        Lưu ở kho (Không gán vào phòng)
      </button>
    </div>

    <!-- Warehouse state banner (if in warehouse mode) -->
    <div
      v-if="allowWarehouse && isWarehouseMode"
      class="p-3 bg-amber-50/80 border border-amber-200 rounded-xl flex items-center justify-between text-xs"
    >
      <div class="flex items-center gap-2.5 text-amber-900 font-medium">
        <v-icon icon="mdi-package-variant-closed" color="warning" size="20" />
        <span>Thiết bị đang ở <strong>Kho tổng</strong> (Chưa gán vào phòng)</span>
      </div>
      <button
        type="button"
        class="px-3 py-1.5 rounded-lg bg-white border border-amber-300 text-amber-900 text-xs font-bold hover:bg-amber-100 transition shadow-2xs flex items-center gap-1.5"
        @click="startSelectingRoom"
      >
        <v-icon icon="mdi-door-open" size="16" />
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

      <!-- Hint banner when in room mode but no room selected yet -->
      <div
        v-else
        class="p-2.5 bg-slate-50 border border-dashed border-slate-300 rounded-xl flex items-center justify-between text-xs"
      >
        <div class="flex items-center gap-2 text-slate-600">
          <v-icon icon="mdi-map-marker-outline" size="16" class="text-blue-500" />
          <span>
            <template v-if="selectedBlock || selectedFloor !== ''">
              Đang lọc: <strong>{{ selectedBlock || 'Tất cả Block' }}</strong>
              <span v-if="selectedFloor !== ''"> • Tầng {{ selectedFloor }}</span>.
            </template>
            Vui lòng chọn phòng ở <strong>Bậc 3</strong> bên dưới.
          </span>
        </div>
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
            clearable
            placeholder="Tìm & chọn phòng..."
            no-data-text="Không tìm thấy phòng phù hợp"
            @update:model-value="onRoomSelected"
          >
            <template #no-data>
              <div class="p-3 text-xs text-slate-400 text-center">
                Không tìm thấy phòng nào trong bộ lọc này
              </div>
            </template>
          </v-autocomplete>
        </div>
      </div>
    </div>
  </div>
</template>
