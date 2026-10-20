<script setup>
import { ref } from 'vue'
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
const issueDescription = ref('')
const photoFile = ref(null)

async function handleSubmit() {
  const { valid } = await formRef.value.validate()
  if (!valid) return

  const formData = new FormData()
  formData.append('issue_description', issueDescription.value)
  if (photoFile.value) {
    formData.append('broken_photo', photoFile.value)
  }

  emit('submit', {
    id: props.equipment?.id,
    formData,
  })
}
</script>

<template>
  <AppModal
    :model-value="modelValue"
    title="Báo hỏng & Lập Phiếu Bảo trì"
    :subtitle="`Thiết bị #${equipment?.id} — ${equipment?.serial_number || equipment?.model?.name}`"
    max-width="500"
    @update:model-value="val => emit('update:modelValue', val)"
  >
    <div class="mb-4 p-3 bg-rose-50 border border-rose-100 rounded-xl flex items-start gap-2.5 text-xs text-rose-800">
      <v-icon icon="mdi-alert-circle" size="18" color="error" class="flex-shrink-0 mt-0.5" />
      <span>
        Hành động này sẽ tự động chuyển trạng thái thiết bị sang <strong>Hư hỏng (broken)</strong> và khởi tạo phiếu bảo dưỡng trong nhật ký hệ thống.
      </span>
    </div>

    <v-form ref="formRef" @submit.prevent="handleSubmit">
      <div class="space-y-4">
        <!-- Issue Description -->
        <div>
          <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
            Mô tả chi tiết tình trạng hư hỏng *
          </label>
          <v-textarea
            v-model="issueDescription"
            rows="3"
            placeholder="Ví dụ: Tivi bật không lên màn hình, nguồn chớp nháy màu đỏ, dây cắm lỏng..."
            :rules="[rules.required]"
            hide-details="auto"
          />
        </div>

        <!-- Photo Upload -->
        <div>
          <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
            Ảnh chụp hiện trường sự cố (Tùy chọn)
          </label>
          <v-file-input
            v-model="photoFile"
            label="Chọn ảnh chụp (.jpg, .png, .webp)"
            prepend-icon=""
            prepend-inner-icon="mdi-camera-outline"
            accept="image/*"
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
        color="error"
        :loading="loading"
        icon="mdi-wrench"
        @click="handleSubmit"
      >
        Gửi báo hỏng
      </AppButton>
    </template>
  </AppModal>
</template>
