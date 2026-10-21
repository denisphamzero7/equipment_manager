<script setup>
import { ref, watch, computed } from 'vue'
import AppModal from '@/core/components/AppModal.vue'
import AppButton from '@/core/components/AppButton.vue'
import RoomCascaderSelect from '@/core/components/RoomCascaderSelect.vue'
import { equipmentService } from '../services/equipmentService'

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

// Quota check state
const checkLoading = ref(false)
const quotaResult = ref(null)
const confirmOverQuota = ref(false)

watch(
  () => props.equipment,
  newVal => {
    if (newVal) {
      roomId.value = newVal.room_id || null
      targetMode.value = newVal.room_id ? 'room' : 'warehouse'
      positionNote.value = newVal.position_note || ''
      quotaResult.value = null
      confirmOverQuota.value = false
    }
  },
  { immediate: true }
)

// When roomId or targetMode changes, check quota
watch(
  [roomId, targetMode],
  async ([newRoomId, newMode]) => {
    confirmOverQuota.value = false
    if (!props.equipment?.id) return

    if (newMode === 'warehouse') {
      await fetchQuotaCheck(null)
    } else if (newRoomId) {
      await fetchQuotaCheck(Number(newRoomId))
    } else {
      quotaResult.value = null
    }
  }
)

async function fetchQuotaCheck(targetId) {
  checkLoading.value = true
  try {
    const res = await equipmentService.checkAssignTarget(props.equipment.id, targetId)
    quotaResult.value = res
  } catch (err) {
    console.error('Lỗi kiểm tra định mức:', err)
    quotaResult.value = null
  } finally {
    checkLoading.value = false
  }
}

// Can submit validation
const canSubmit = computed(() => {
  if (props.loading) return false
  if (targetMode.value === 'room') {
    if (!roomId.value) return false
    // If over quota, require user confirmation
    if (quotaResult.value?.status === 'over_quota' && !confirmOverQuota.value) {
      return false
    }
  }
  return true
})

async function handleSubmit() {
  if (targetMode.value === 'room') {
    const { valid } = await formRef.value.validate()
    if (!valid) return
  }

  emit('submit', {
    equipment_id: props.equipment?.id,
    room_id: targetMode.value === 'warehouse' ? null : Number(roomId.value),
    position_note: positionNote.value,
    confirm_over_quota: confirmOverQuota.value,
  })
}
</script>

<template>
  <AppModal
    :model-value="modelValue"
    title="Điều chuyển & Cấp phát Thiết bị"
    :subtitle="`Thiết bị: #${equipment?.id} — ${equipment?.model?.name || equipment?.name} (${equipment?.serial_number || 'Chưa gắn mã'})`"
    max-width="560"
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
              :class="targetMode === 'room' ? 'border-primary bg-blue-50/70 text-blue-900 font-bold shadow-xs' : 'border-slate-200 hover:bg-slate-50 text-slate-600'"
              @click="targetMode = 'room'"
            >
              <v-icon icon="mdi-door-sliding" :color="targetMode === 'room' ? 'primary' : 'secondary'" size="22" />
              <div class="text-xs">
                <p class="font-bold">Gán vào phòng</p>
                <p class="text-[11px] text-slate-400">Đưa vào sử dụng</p>
              </div>
            </button>

            <button
              type="button"
              class="p-3 rounded-xl border text-left flex items-center gap-3 transition cursor-pointer"
              :class="targetMode === 'warehouse' ? 'border-primary bg-blue-50/70 text-blue-900 font-bold shadow-xs' : 'border-slate-200 hover:bg-slate-50 text-slate-600'"
              @click="targetMode = 'warehouse'"
            >
              <v-icon icon="mdi-package-variant-closed" :color="targetMode === 'warehouse' ? 'primary' : 'secondary'" size="22" />
              <div class="text-xs">
                <p class="font-bold">Thu hồi về kho</p>
                <p class="text-[11px] text-slate-400">Trạng thái in_stock</p>
              </div>
            </button>
          </div>
        </div>

        <!-- Room Input if targetMode is room -->
        <div v-if="targetMode === 'room'">
          <RoomCascaderSelect
            v-model="roomId"
            label="Chọn Phòng đích đến (Block → Tầng → Số phòng)"
            :allow-warehouse="false"
            :required="true"
          />
        </div>

        <!-- Pre-assignment Quota & Impact Analysis Widget -->
        <div v-if="checkLoading" class="p-3 bg-slate-50 rounded-xl border border-slate-200/80 flex items-center justify-center gap-2 text-xs text-slate-500">
          <v-progress-circular indeterminate size="16" width="2" color="primary" />
          <span>Đang đối soát định mức phòng đích...</span>
        </div>

        <div v-else-if="quotaResult" class="space-y-2.5">
          <!-- 1. Over-Quota Warning (Phòng đã có đủ/thừa thiết bị) -->
          <div
            v-if="quotaResult.status === 'over_quota'"
            class="p-3.5 bg-amber-50/90 border border-amber-300 rounded-xl text-xs space-y-2"
          >
            <div class="flex items-start gap-2.5">
              <v-icon icon="mdi-alert" size="20" color="warning" class="shrink-0 mt-0.5" />
              <div class="min-w-0 flex-1">
                <p class="font-bold text-amber-900 text-xs">
                  Cảnh báo: Phòng đích đã ĐỦ định mức thiết bị!
                </p>
                <p class="text-amber-800 text-[11px] mt-0.5 leading-relaxed">
                  {{ quotaResult.message }}
                </p>
              </div>
            </div>

            <!-- Quota comparison counter -->
            <div class="flex items-center justify-between bg-white/80 p-2 rounded-lg border border-amber-200 text-[11px]">
              <span class="text-amber-800 font-medium">Hiện có: <strong>{{ quotaResult.current_count }}</strong> / Chuẩn: <strong>{{ quotaResult.standard_quota }}</strong></span>
              <span class="text-amber-900 font-extrabold flex items-center gap-1">
                <span>Sau khi chuyển:</span>
                <span class="text-rose-600 bg-rose-50 px-1.5 py-0.2 rounded font-black border border-rose-200">
                  {{ quotaResult.after_count }} / {{ quotaResult.standard_quota }} (Thừa {{ quotaResult.after_count - quotaResult.standard_quota }})
                </span>
              </span>
            </div>

            <!-- Mandatory Confirmation Checkbox -->
            <label class="flex items-start gap-2 pt-1 text-slate-700 cursor-pointer select-none">
              <input
                v-model="confirmOverQuota"
                type="checkbox"
                class="mt-0.5 rounded text-amber-600 focus:ring-amber-500 w-4 h-4 cursor-pointer"
              />
              <span class="text-[11px] font-bold text-amber-950">
                Tôi xác nhận và đồng ý cho phép điều chuyển thiết bị này vượt quá định mức chuẩn của phòng.
              </span>
            </label>
          </div>

          <!-- 2. Exact Quota Met (Chuyển vào giúp phòng đạt chuẩn) -->
          <div
            v-else-if="quotaResult.status === 'exact_quota'"
            class="p-3 bg-emerald-50 border border-emerald-200 rounded-xl text-xs flex items-start gap-2.5"
          >
            <v-icon icon="mdi-check-decagram" size="20" color="success" class="shrink-0 mt-0.5" />
            <div class="min-w-0 flex-1">
              <p class="font-bold text-emerald-900 text-xs">
                Tuyệt vời: Cấp bù giúp phòng ĐẠT CHUẨN!
              </p>
              <p class="text-emerald-800 text-[11px] mt-0.5">
                {{ quotaResult.message }}
              </p>
            </div>
            <span class="px-2 py-0.5 rounded font-black bg-emerald-200/80 text-emerald-900 text-[11px] shrink-0">
              {{ quotaResult.after_count }}/{{ quotaResult.standard_quota }}
            </span>
          </div>

          <!-- 3. Under Quota (Sau khi chuyển vẫn thiếu) -->
          <div
            v-else-if="quotaResult.status === 'under_quota'"
            class="p-3 bg-blue-50 border border-blue-200 rounded-xl text-xs flex items-start gap-2.5"
          >
            <v-icon icon="mdi-information-outline" size="20" color="primary" class="shrink-0 mt-0.5" />
            <div class="min-w-0 flex-1">
              <p class="font-bold text-blue-900 text-xs">Cấp bù thiết bị phòng</p>
              <p class="text-blue-800 text-[11px] mt-0.5">{{ quotaResult.message }}</p>
            </div>
            <span class="px-2 py-0.5 rounded font-bold bg-blue-100 text-blue-800 text-[11px] shrink-0">
              {{ quotaResult.after_count }}/{{ quotaResult.standard_quota }}
            </span>
          </div>

          <!-- 4. No Standard Quota (Thiết bị trang bị thêm ngoài chuẩn) -->
          <div
            v-else-if="quotaResult.status === 'no_standard'"
            class="p-3 bg-slate-50 border border-slate-200 rounded-xl text-xs flex items-start gap-2.5"
          >
            <v-icon icon="mdi-tag-outline" size="20" color="secondary" class="shrink-0 mt-0.5" />
            <div class="min-w-0 flex-1">
              <p class="font-bold text-slate-700 text-xs">Thiết bị bổ sung ngoài định mức</p>
              <p class="text-slate-600 text-[11px] mt-0.5">{{ quotaResult.message }}</p>
            </div>
          </div>

          <!-- 5. Source Room Impact Warning (Phòng nguồn bị mất thiết bị) -->
          <div
            v-if="quotaResult.source_room_impact"
            class="p-3 rounded-xl border text-xs flex items-start gap-2.5"
            :class="quotaResult.source_room_impact.will_be_missing
              ? 'bg-rose-50/70 border-rose-200 text-rose-900'
              : 'bg-slate-50 border-slate-200 text-slate-700'"
          >
            <v-icon
              :icon="quotaResult.source_room_impact.will_be_missing ? 'mdi-alert-circle-outline' : 'mdi-home-outline'"
              size="18"
              :color="quotaResult.source_room_impact.will_be_missing ? 'error' : 'secondary'"
              class="shrink-0 mt-0.5"
            />
            <div class="min-w-0 flex-1">
              <p class="font-bold text-xs" :class="quotaResult.source_room_impact.will_be_missing ? 'text-rose-900' : 'text-slate-800'">
                Tác động đến phòng cũ (Phòng {{ quotaResult.source_room_impact.room_number }}):
              </p>
              <p class="text-[11px] mt-0.5 leading-relaxed" :class="quotaResult.source_room_impact.will_be_missing ? 'text-rose-800' : 'text-slate-600'">
                {{ quotaResult.source_room_impact.message }}
              </p>
            </div>
          </div>
        </div>

        <!-- Position Note -->
        <div>
          <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
            Ghi chú vị trí đặt mới / Lý do điều chuyển
          </label>
          <v-text-field
            v-model="positionNote"
            placeholder="Ví dụ: Kệ tivi phòng khách, chuyển bổ sung theo yêu cầu khách..."
            hide-details="auto"
            density="compact"
            variant="outlined"
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
        :disabled="!canSubmit"
        @click="handleSubmit"
      >
        Xác nhận điều chuyển
      </AppButton>
    </template>
  </AppModal>
</template>
