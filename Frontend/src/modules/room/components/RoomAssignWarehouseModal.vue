<script setup>
import { ref, watch } from 'vue'
import AppModal from '@/core/components/AppModal.vue'
import AppButton from '@/core/components/AppButton.vue'

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  roomNumber: {
    type: [String, Number],
    default: '',
  },
  warehouseEquipments: {
    type: Array,
    default: () => [],
  },
  warehouseLoading: {
    type: Boolean,
    default: false,
  },
  actionLoading: {
    type: Boolean,
    default: false,
  },
  suggestedPositions: {
    type: Array,
    default: () => [],
  },
})

const emit = defineEmits(['update:modelValue', 'submit'])

const selectedWarehouseEquipmentId = ref(null)
const warehousePositionNote = ref('')

watch(
  () => props.modelValue,
  (val) => {
    if (val) {
      selectedWarehouseEquipmentId.value = null
      warehousePositionNote.value = ''
    }
  }
)

function handleSubmit() {
  if (!selectedWarehouseEquipmentId.value) return
  emit('submit', {
    equipment_id: selectedWarehouseEquipmentId.value,
    position_note: warehousePositionNote.value || 'Gán trực tiếp vào phòng',
  })
}
</script>

<template>
  <AppModal
    :model-value="modelValue"
    title="Gán Thiết Bị từ Kho vào Phòng"
    :subtitle="`Phòng đích: Phòng ${roomNumber}`"
    max-width="540"
    @update:model-value="val => emit('update:modelValue', val)"
  >
    <div v-if="warehouseLoading" class="py-8 text-center text-slate-400 text-xs">
      <v-progress-circular indeterminate color="primary" size="30" />
      <p class="mt-2">Đang tải danh sách thiết bị trong kho...</p>
    </div>

    <div v-else class="space-y-4">
      <div>
        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
          Chọn thiết bị còn trong kho (in_stock) *
        </label>
        <v-select
          v-model="selectedWarehouseEquipmentId"
          :items="warehouseEquipments.map(e => ({
            value: e.id,
            title: `${e.serial_number || 'Chưa serial'} — ${e.model?.name || e.name} (${e.category?.name || 'Khác'})`,
          }))"
          item-title="title"
          item-value="value"
          placeholder="Tìm và chọn thiết bị trong kho..."
          density="compact"
          variant="outlined"
          hide-details
          prepend-inner-icon="mdi-package-variant-closed"
        />
        <p v-if="warehouseEquipments.length === 0" class="text-xs text-amber-600 mt-1">
          Kho hiện không còn thiết bị nào ở trạng thái sẵn sàng.
        </p>
      </div>

      <div>
        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
          Ghi chú vị trí trong phòng
        </label>
        <v-text-field
          v-model="warehousePositionNote"
          placeholder="Ví dụ: Kệ tivi phòng ngủ, bàn làm việc..."
          density="compact"
          variant="outlined"
          hide-details
          prepend-inner-icon="mdi-map-marker-outline"
        />

        <!-- Gợi ý vị trí không gian con nhanh -->
        <div v-if="suggestedPositions.length" class="flex items-center gap-1.5 flex-wrap mt-2">
          <span class="text-[11px] text-slate-400 font-medium">Gợi ý nhanh:</span>
          <button
            v-for="pos in suggestedPositions"
            :key="pos"
            type="button"
            class="px-2 py-0.5 rounded-full text-[11px] font-medium transition cursor-pointer"
            :class="warehousePositionNote === pos ? 'bg-primary text-white font-bold' : 'bg-slate-100 hover:bg-slate-200 text-slate-700'"
            @click="warehousePositionNote = pos"
          >
            {{ pos }}
          </button>
        </div>
      </div>
    </div>

    <template #footer>
      <AppButton
        variant="secondary"
        :disabled="actionLoading"
        @click="emit('update:modelValue', false)"
      >
        Hủy bỏ
      </AppButton>
      <AppButton
        variant="primary"
        :loading="actionLoading"
        :disabled="!selectedWarehouseEquipmentId"
        @click="handleSubmit"
      >
        <v-icon start icon="mdi-check" />
        Gán vào phòng
      </AppButton>
    </template>
  </AppModal>
</template>
