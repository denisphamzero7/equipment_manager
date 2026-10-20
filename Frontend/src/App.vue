<script setup>
import { toastState } from '@/core/helpers/toast'
</script>

<template>
  <v-app>
    <router-view />

    <!-- Global Toast / Notification Snackbar -->
    <v-snackbar
      v-model="toastState.show"
      :color="toastState.color"
      :timeout="toastState.timeout"
      location="top right"
      rounded="xl"
      elevation="4"
    >
      <div class="flex items-center gap-2.5 font-medium text-sm text-white">
        <v-icon :icon="toastState.icon" size="20" />
        <span>{{ toastState.message }}</span>
      </div>

      <template #actions>
        <v-btn
          variant="text"
          icon="mdi-close"
          size="small"
          density="comfortable"
          color="white"
          @click="toastState.show = false"
        />
      </template>
    </v-snackbar>
  </v-app>
</template>
