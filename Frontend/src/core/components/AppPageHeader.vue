<script setup>
defineProps({
  title: {
    type: String,
    required: true,
  },
  subtitle: {
    type: String,
    default: '',
  },
  breadcrumbs: {
    type: Array,
    default: () => [],
  },
})
</script>

<template>
  <div class="mb-4 sm:mb-6 flex flex-col md:flex-row md:items-center md:justify-between gap-3 sm:gap-4">
    <div>
      <div v-if="breadcrumbs.length" class="flex items-center gap-1.5 text-xs text-slate-400 mb-1 font-medium flex-wrap">
        <template v-for="(crumb, idx) in breadcrumbs" :key="idx">
          <router-link
            v-if="crumb.to"
            :to="crumb.to"
            class="hover:text-blue-600 transition-colors"
          >
            {{ crumb.title }}
          </router-link>
          <span v-else class="text-slate-600 font-semibold">{{ crumb.title }}</span>
          <v-icon v-if="idx < breadcrumbs.length - 1" icon="mdi-chevron-right" size="14" />
        </template>
      </div>

      <h1 class="text-xl sm:text-2xl font-bold text-slate-800 tracking-tight">
        {{ title }}
      </h1>
      <p v-if="subtitle" class="text-xs sm:text-sm text-slate-500 mt-0.5">
        {{ subtitle }}
      </p>
    </div>

    <!-- Actions slot -->
    <div v-if="$slots.actions" class="flex items-center gap-2 flex-wrap">
      <slot name="actions" />
    </div>
  </div>
</template>
