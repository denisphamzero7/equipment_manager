<script setup>
import { ref, watch } from 'vue'
import AppModal from '@/core/components/AppModal.vue'
import AppButton from '@/core/components/AppButton.vue'
import { EquipmentStatus, EquipmentStatusOptions } from '@/core/enums/EquipmentStatus'
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
const isEdit = ref(false)
const photoFile = ref(null)
const photoPreview = ref(null)

const form = ref({
  equipment_model_id: 1,
  serial_number: '',
  room_id: null,
  status: EquipmentStatus.GOOD,
  position_note: '',
  note: '',
  installed_at: '',
})

watch(
  () => props.equipment,
  newVal => {
    photoFile.value = null
    if (newVal) {
      isEdit.value = true
      photoPreview.value = newVal.photo_url || newVal.photos?.[0]?.url || null
      form.value = {
        equipment_model_id: newVal.equipment_model_id || newVal.model?.id || 1,
        serial_number: newVal.serial_number || '',
        room_id: newVal.room_id || null,
        status: newVal.status || EquipmentStatus.GOOD,
        position_note: newVal.position_note || '',
        note: newVal.note || '',
        installed_at: newVal.installed_at ? newVal.installed_at.substring(0, 10) : '',
      }
    } else {
      isEdit.value = false
      photoPreview.value = null
      form.value = {
        equipment_model_id: 1,
        serial_number: '',
        room_id: null,
        status: EquipmentStatus.GOOD,
        position_note: '',
        note: '',
        installed_at: new Date().toISOString().substring(0, 10),
      }
    }
  },
  { immediate: true }
)

function handlePhotoChange(event) {
  const file = event.target?.files?.[0]
  if (file) {
    photoFile.value = file
    photoPreview.value = URL.createObjectURL(file)
  }
}

function removePhoto() {
  photoFile.value = null
  photoPreview.value = null
}

async function handleSubmit() {
  const { valid } = await formRef.value.validate()
  if (!valid) return

  const payloadData = { ...form.value }
  let dataToSend = payloadData
  if (photoFile.value) {
    const fd = new FormData()
    Object.keys(payloadData).forEach(key => {
      if (payloadData[key] !== null && payloadData[key] !== undefined && payloadData[key] !== '') {
        fd.append(key, payloadData[key])
      }
    })
    fd.append('photo', photoFile.value)
    dataToSend = fd
  }

  emit('submit', {
    isEdit: isEdit.value,
    id: props.equipment?.id,
    data: dataToSend,
  })
}
</script>

<template>
  <AppModal
    :model-value="modelValue"
    :title="isEdit ? 'Cập nhật Thông tin Thiết bị' : 'Thêm Thiết bị Mới'"
    :subtitle="isEdit ? `Mã thiết bị #${equipment?.id}` : 'Nhập thông tin cá thể thiết bị và định vị phòng'"
    max-width="600"
    @update:model-value="val => emit('update:modelValue', val)"
  >
    <v-form ref="formRef" @submit.prevent="handleSubmit">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <!-- Model ID -->
        <div>
          <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
            Mẫu thiết bị (Model ID) *
          </label>
          <v-text-field
            v-model.number="form.equipment_model_id"
            type="number"
            placeholder="1"
            :rules="[rules.required]"
            hide-details="auto"
          />
        </div>

        <!-- Serial Number -->
        <div>
          <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
            Mã Serial / QR code
          </label>
          <v-text-field
            v-model="form.serial_number"
            placeholder="TV-SAM-43-001"
            :rules="[rules.serialNumber]"
            hide-details="auto"
          />
        </div>

        <!-- Room ID -->
        <div>
          <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
            ID Phòng (Để trống nếu ở kho)
          </label>
          <v-text-field
            v-model.number="form.room_id"
            type="number"
            placeholder="101"
            hide-details="auto"
          />
        </div>

        <!-- Status -->
        <div>
          <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
            Trạng thái hoạt động
          </label>
          <v-select
            v-model="form.status"
            :items="EquipmentStatusOptions"
            item-title="title"
            item-value="value"
            hide-details="auto"
          />
        </div>

        <!-- Position Note -->
        <div class="md:col-span-2">
          <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
            Vị trí đặt trong phòng
          </label>
          <v-text-field
            v-model="form.position_note"
            placeholder="Ví dụ: Treo tường phòng ngủ, Cạnh bàn làm việc..."
            hide-details="auto"
          />
        </div>

        <!-- Installed Date -->
        <div>
          <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
            Ngày lắp đặt
          </label>
          <v-text-field
            v-model="form.installed_at"
            type="date"
            hide-details="auto"
          />
        </div>

        <!-- Note -->
        <div class="md:col-span-2">
          <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
            Ghi chú chi tiết
          </label>
          <v-textarea
            v-model="form.note"
            rows="2"
            placeholder="Thông tin bảo dưỡng, xuất xứ hoặc tình trạng đặc biệt..."
            hide-details="auto"
          />
        </div>
        <!-- Photo Upload -->
        <div class="md:col-span-2">
          <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
            Hình ảnh thiết bị (Tùy chọn)
          </label>
          <div class="flex items-center gap-3">
            <div
              v-if="photoPreview"
              class="relative w-16 h-16 rounded-xl overflow-hidden border border-slate-200 shrink-0 bg-slate-50"
            >
              <img :src="photoPreview" alt="Photo Preview" class="w-full h-full object-cover" />
              <button
                type="button"
                class="absolute top-1 right-1 w-5 h-5 rounded-full bg-rose-500 text-white flex items-center justify-center hover:bg-rose-600 transition-colors shadow-xs"
                title="Gỡ ảnh"
                @click="removePhoto"
              >
                <v-icon icon="mdi-close" size="12" />
              </button>
            </div>
            <v-file-input
              accept="image/png, image/jpeg, image/jpg, image/webp"
              placeholder="Chọn hình ảnh từ máy tính"
              prepend-inner-icon="mdi-camera"
              prepend-icon=""
              hide-details="auto"
              density="compact"
              variant="outlined"
              class="flex-1"
              @change="handlePhotoChange"
            />
          </div>
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
        {{ isEdit ? 'Lưu thay đổi' : 'Tạo thiết bị' }}
      </AppButton>
    </template>
  </AppModal>
</template>
