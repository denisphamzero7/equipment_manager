<script setup>
defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  title: {
    type: String,
    default: 'Xác nhận thao tác',
  },
  message: {
    type: String,
    default: 'Bạn có chắc chắn muốn thực hiện hành động này? Hành động không thể hoàn tác.',
  },
  confirmText: {
    type: String,
    default: 'Xác nhận',
  },
  cancelText: {
    type: String,
    default: 'Hủy bỏ',
  },
  confirmColor: {
    type: String,
    default: 'error', // error | primary | warning
  },
  confirmIcon: {
    type: String,
    default: 'mdi-alert-circle-outline',
  },
  loading: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['update:modelValue', 'confirm', 'cancel'])

function close() {
  emit('update:modelValue', false)
  emit('cancel')
}

function confirm() {
  emit('confirm')
}
</script>

<template>
  <v-dialog :model-value="modelValue" max-width="480" persistent @update:model-value="val => emit('update:modelValue', val)">
    <div class="bg-white rounded-2xl p-6 shadow-xl border border-slate-100">
      <div class="flex items-start gap-4">
        <div
          class="w-12 h-12 rounded-xl flex-shrink-0 flex items-center justify-center"
          :class="confirmColor === 'error' ? 'bg-rose-50 text-rose-600' : 'bg-amber-50 text-amber-600'"
        >
          <v-icon :icon="confirmIcon" size="28" />
        </div>
        <div class="flex-1">
          <h3 class="text-lg font-bold text-slate-800 mb-1">
            {{ title }}
          </h3>
          <p class="text-sm text-slate-500 leading-relaxed">
            {{ message }}
          </p>
        </div>
      </div>

      <div class="mt-6 flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
        <v-btn
          variant="tonal"
          color="secondary"
          :disabled="loading"
          @click="close"
        >
          {{ cancelText }}
        </v-btn>
        <v-btn
          :color="confirmColor"
          :loading="loading"
          @click="confirm"
        >
          {{ confirmText }}
        </v-btn>
      </div>
    </div>
  </v-dialog>
</template>
