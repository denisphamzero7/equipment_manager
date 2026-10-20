<script setup>
import { ref, watch } from 'vue'
import AppModal from '@/core/components/AppModal.vue'
import AppButton from '@/core/components/AppButton.vue'
import { rules } from '@/core/helpers/validators'

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  equipment: {
    type: Object,
    default: null,
  },
  loading: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['update:modelValue', 'submit'])

const formRef = ref(null)
const targetMode = ref('room') // 'room' | 'warehouse'
const roomId = ref(null)
const positionNote = ref('')

watch(
  () => props.equipment,
  newVal => {
    if (newVal) {
      roomId.value = newVal.room_id || null
      targetMode.value = newVal.room_id ? 'room' : 'warehouse'
      positionNote.value = newVal.position_note || ''
    }
  },
  { immediate: true }
)

async function handleSubmit() {
  if (targetMode.value === 'room') {
    const { valid } = await formRef.value.validate()
    if (!valid) return
  }

  emit('submit', {
    equipment_id: props.equipment?.id,
    room_id: targetMode.value === 'warehouse' ? null : Number(roomId.value),
    position_note: positionNote.value,
  })
}
</script>

<template>
  <AppModal
    :model-value="modelValue"
    title="Điều chuyển Thiết bị"
    :subtitle="`Thiết bị: #${equipment?.id} — ${equipment?.serial_number || equipment?.model?.name || 'Không rõ serial'}`"
    max-width="500"
    @update:model-value="val => emit('update:modelValue', val)"
  >
    <v-form ref="formRef" @submit.prevent="handleSubmit">
      <div class="space-y-4">
        <!-- Radio choice: Room or Warehouse -->
        <div>
          <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">
            Nơi chuyển đến
          </label>
          <div class="grid grid-cols-2 gap-3">
            <button
              type="button"
              class="p-3 rounded-xl border text-left flex items-center gap-3 transition cursor-pointer"
              :class="targetMode === 'room' ? 'border-blue-600 bg-blue-50/60 text-blue-800 font-bold' : 'border-slate-200 hover:bg-slate-50 text-slate-600'"
              @click="targetMode = 'room'"
            >
              <v-icon icon="mdi-door-sliding" :color="targetMode === 'room' ? 'primary' : 'secondary'" />
              <div class="text-xs">
                <p class="font-bold">Gán vào phòng</p>
                <p class="text-[11px] text-slate-400">Đưa vào vận hành</p>
              </div>
            </button>

            <button
              type="button"
              class="p-3 rounded-xl border text-left flex items-center gap-3 transition cursor-pointer"
              :class="targetMode === 'warehouse' ? 'border-blue-600 bg-blue-50/60 text-blue-800 font-bold' : 'border-slate-200 hover:bg-slate-50 text-slate-600'"
              @click="targetMode = 'warehouse'"
            >
              <v-icon icon="mdi-package-variant-closed" :color="targetMode === 'warehouse' ? 'primary' : 'secondary'" />
              <div class="text-xs">
                <p class="font-bold">Thu hồi về kho</p>
                <p class="text-[11px] text-slate-400">Trạng thái in_stock</p>
              </div>
            </button>
          </div>
        </div>

        <!-- Room Input if targetMode is room -->
        <div v-if="targetMode === 'room'">
          <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
            ID Phòng đích *
          </label>
          <v-text-field
            v-model.number="roomId"
            type="number"
            placeholder="Ví dụ: 101, 202..."
            prepend-inner-icon="mdi-door"
            :rules="[rules.required]"
            hide-details="auto"
          />
        </div>

        <!-- Position Note -->
        <div>
          <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
            Ghi chú vị trí đặt mới
          </label>
          <v-text-field
            v-model="positionNote"
            placeholder="Ví dụ: Kệ tivi phòng khách, chuyển từ kho A..."
            hide-details="auto"
          />
        </div>
      </div>
    </v-form>

    <template #footer>
      <v-btn
        variant="tonal"
        color="secondary"
        :disabled="loading"
        @click="emit('update:modelValue', false)"
      >
        Hủy bỏ
      </v-btn>
      <AppButton
        color="primary"
        :loading="loading"
        @click="handleSubmit"
      >
        Xác nhận điều chuyển
      </AppButton>
    </template>
  </AppModal>
</template>
