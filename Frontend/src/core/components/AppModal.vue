<script setup>
defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  title: {
    type: String,
    default: '',
  },
  subtitle: {
    type: String,
    default: '',
  },
  maxWidth: {
    type: [Number, String],
    default: 640,
  },
  persistent: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['update:modelValue', 'close'])

function handleClose() {
  emit('update:modelValue', false)
  emit('close')
}
</script>

<template>
  <v-dialog
    :model-value="modelValue"
    :max-width="maxWidth"
    :persistent="persistent"
    @update:model-value="val => emit('update:modelValue', val)"
  >
    <div class="bg-white rounded-2xl shadow-2xl border border-slate-100 flex flex-col max-h-[90vh] overflow-hidden">
      <!-- Modal Header -->
      <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
        <div>
          <h3 class="text-base font-bold text-slate-800">
            {{ title }}
          </h3>
          <p v-if="subtitle" class="text-xs text-slate-400 mt-0.5">
            {{ subtitle }}
          </p>
        </div>
        <v-btn
          icon="mdi-close"
          variant="text"
          density="comfortable"
          size="small"
          color="secondary"
          @click="handleClose"
        />
      </div>

      <!-- Modal Body (Scrollable) -->
      <div class="px-6 py-5 overflow-y-auto flex-1">
        <slot />
      </div>

      <!-- Modal Footer -->
      <div v-if="$slots.footer" class="px-6 py-4 border-t border-slate-100 bg-slate-50/50 flex items-center justify-end gap-3">
        <slot name="footer" />
      </div>
    </div>
  </v-dialog>
</template>
