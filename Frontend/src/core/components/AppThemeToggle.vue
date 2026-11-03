<script setup>
import { computed } from 'vue'
import { useThemeStore } from '@/core/stores/themeStore'

/**
 * Component nút bật/tắt hoặc chọn chế độ giao diện Sáng / Tối (Theme Mode Toggle).
 * Được đặt trong core/components để sử dụng chung cho Layout, Header, Settings, v.v.
 */
const props = defineProps({
  /**
   * Kiểu hiển thị:
   *  - 'dropdown': Nút bấm mở menu chọn (Sáng, Tối, Hệ thống) - Mặc định
   *  - 'icon': Nút bấm chuyển đổi nhanh 1 chạm (Light <-> Dark)
   */
  variant: {
    type: String,
    default: 'dropdown',
    validator: (v) => ['dropdown', 'icon'].includes(v),
  },
  /**
   * Kích thước nút ('compact' | 'comfortable' | 'default')
   */
  density: {
    type: String,
    default: 'comfortable',
  },
  /**
   * Hiển thị Tooltip khi hover vào nút
   */
  showTooltip: {
    type: Boolean,
    default: true,
  },
})

const themeStore = useThemeStore()

const themeOptions = [
  {
    key: 'light',
    label: 'Chế độ sáng',
    desc: 'Giao diện nền trắng sáng, tiêu chuẩn',
    icon: 'mdi-weather-sunny',
    iconColor: 'text-amber-500',
  },
  {
    key: 'dark',
    label: 'Chế độ tối',
    desc: 'Nền tối chống mỏi mắt ban đêm',
    icon: 'mdi-weather-night',
    iconColor: 'text-indigo-400',
  },
  {
    key: 'system',
    label: 'Theo hệ thống',
    desc: 'Tự động theo cài đặt của máy tính',
    icon: 'mdi-monitor',
    iconColor: 'text-slate-400',
  },
]

const currentTooltip = computed(() => {
  return `Giao diện: ${themeStore.themeLabel} (Bấm để thay đổi)`
})
</script>

<template>
  <div class="inline-flex items-center">
    <!-- 1. Kiểu ICON: Chuyển đổi nhanh 1 chạm giữa Sáng <-> Tối -->
    <template v-if="variant === 'icon'">
      <button
        type="button"
        class="w-9 h-9 rounded-xl border border-slate-200/80 dark:border-slate-700/80 bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:text-blue-600 dark:hover:text-blue-400 hover:bg-slate-50 dark:hover:bg-slate-700/70 flex items-center justify-center transition shadow-2xs cursor-pointer"
        :title="showTooltip ? currentTooltip : undefined"
        @click="themeStore.toggleTheme"
      >
        <v-icon
          :icon="themeStore.isDark ? 'mdi-weather-night' : 'mdi-weather-sunny'"
          size="18"
          :class="themeStore.isDark ? 'text-indigo-400' : 'text-amber-500'"
        />
      </button>
    </template>

    <!-- 2. Kiểu DROPDOWN: Mở menu chọn 3 chế độ (Sáng, Tối, Hệ thống) -->
    <template v-else>
      <v-menu location="bottom end" transition="slide-y-transition">
        <template #activator="{ props: menuProps }">
          <button
            v-bind="menuProps"
            type="button"
            class="w-9 h-9 rounded-xl border border-slate-200/80 dark:border-slate-700/80 bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:text-blue-600 dark:hover:text-blue-400 hover:bg-slate-50 dark:hover:bg-slate-700/70 flex items-center justify-center transition shadow-2xs cursor-pointer"
            :title="showTooltip ? currentTooltip : undefined"
          >
            <v-icon
              :icon="themeStore.themeIcon"
              size="18"
              :class="themeStore.mode === 'dark' ? 'text-indigo-400' : (themeStore.mode === 'light' ? 'text-amber-500' : 'text-slate-500 dark:text-slate-400')"
            />
          </button>
        </template>

        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-xl border border-slate-200/80 dark:border-slate-700 p-1.5 min-w-[220px] select-none">
          <div class="px-3 py-1.5 text-[11px] font-semibold text-slate-400 dark:text-slate-500 uppercase tracking-wider border-b border-slate-100 dark:border-slate-700/60 mb-1">
            Giao diện hiển thị
          </div>

          <div class="space-y-0.5">
            <button
              v-for="opt in themeOptions"
              :key="opt.key"
              type="button"
              class="w-full flex items-center justify-between px-2.5 py-2 rounded-xl text-left transition cursor-pointer"
              :class="themeStore.mode === opt.key
                ? 'bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 font-semibold'
                : 'text-slate-700 dark:text-slate-300 hover:bg-slate-100/70 dark:hover:bg-slate-700/50'"
              @click="themeStore.setMode(opt.key)"
            >
              <div class="flex items-center gap-2.5 min-w-0">
                <div
                  class="w-7 h-7 rounded-lg flex items-center justify-center flex-shrink-0"
                  :class="themeStore.mode === opt.key
                    ? 'bg-blue-600 text-white shadow-2xs'
                    : 'bg-slate-100 dark:bg-slate-700/70 text-slate-500 dark:text-slate-400'"
                >
                  <v-icon
                    :icon="opt.icon"
                    size="16"
                    :color="themeStore.mode === opt.key ? 'white' : undefined"
                  />
                </div>
                <div>
                  <p class="text-xs font-semibold leading-tight">{{ opt.label }}</p>
                  <p class="text-[10px] text-slate-400 dark:text-slate-500 leading-tight">{{ opt.desc }}</p>
                </div>
              </div>

              <!-- Dấu checkmark nếu đang chọn -->
              <v-icon
                v-if="themeStore.mode === opt.key"
                icon="mdi-check"
                size="16"
                class="text-blue-600 dark:text-blue-400 shrink-0 ml-2"
              />
            </button>
          </div>
        </div>
      </v-menu>
    </template>
  </div>
</template>
