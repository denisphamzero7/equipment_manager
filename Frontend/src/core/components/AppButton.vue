<script setup>
defineProps({
  variant: {
    type: String,
    default: 'elevated', // elevated | flat | text | outlined | tonal
  },
  color: {
    type: String,
    default: 'primary',
  },
  icon: {
    type: String,
    default: '',
  },
  loading: {
    type: Boolean,
    default: false,
  },
  disabled: {
    type: Boolean,
    default: false,
  },
  size: {
    type: String,
    default: 'default', // x-small | small | default | large | x-large
  },
})
</script>

<template>
  <v-btn
    :variant="variant"
    :color="color"
    :loading="loading"
    :disabled="disabled"
    :size="size"
    class="font-medium"
  >
    <v-icon v-if="icon" :icon="icon" start size="small" />
    <slot />
  </v-btn>
</template>
