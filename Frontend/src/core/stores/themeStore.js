import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { storage, StorageKeys } from '@/core/helpers/storage'
import { vuetify } from '@/plugins/vuetify'

/**
 * Store quản lý giao diện Sáng / Tối (Theme Mode) toàn hệ thống.
 * Hỗ trợ 3 chế độ:
 *  - 'light': Giao diện Sáng
 *  - 'dark': Giao diện Tối
 *  - 'system': Tự động theo chế độ của Hệ điều hành / Trình duyệt
 *
 * Đồng bộ hai chiều với:
 *  1. Tailwind CSS: Thêm/gỡ class `dark` trên thẻ `<html>`
 *  2. Vuetify 3: Cập nhật `vuetify.theme.global.name.value`
 *  3. LocalStorage: Lưu trữ qua StorageKeys.THEME_MODE
 */
export const useThemeStore = defineStore('theme', () => {
  // Chế độ theme người dùng chọn ('light' | 'dark' | 'system')
  const mode = ref(storage.get(StorageKeys.THEME_MODE, 'light'))

  // Trạng thái dark của hệ điều hành
  const systemIsDark = ref(
    typeof window !== 'undefined' && window.matchMedia
      ? window.matchMedia('(prefers-color-scheme: dark)').matches
      : false
  )

  // Cờ lắng nghe sự kiện hệ điều hành đã được khởi tạo hay chưa
  let mediaQueryListenerAttached = false

  /**
   * Computed: Trạng thái Dark Mode thực tế đang áp dụng (true/false)
   */
  const isDark = computed(() => {
    if (mode.value === 'dark') return true
    if (mode.value === 'light') return false
    return systemIsDark.value
  })

  /**
   * Computed: Tên theme Vuetify tương ứng ('dark' hoặc 'light')
   */
  const currentTheme = computed(() => (isDark.value ? 'dark' : 'light'))

  /**
   * Computed: Icon đại diện cho chế độ hiện tại
   */
  const themeIcon = computed(() => {
    if (mode.value === 'dark') return 'mdi-weather-night'
    if (mode.value === 'light') return 'mdi-weather-sunny'
    return 'mdi-monitor'
  })

  /**
   * Computed: Nhãn tiếng Việt hiển thị
   */
  const themeLabel = computed(() => {
    if (mode.value === 'dark') return 'Chế độ tối'
    if (mode.value === 'light') return 'Chế độ sáng'
    return 'Theo hệ thống'
  })

  /**
   * Áp dụng theme vào DOM và Vuetify
   */
  function applyTheme() {
    if (typeof document === 'undefined') return

    const dark = isDark.value

    // 1. Đồng bộ Tailwind: Thêm hoặc xóa class 'dark' trên <html>
    if (dark) {
      document.documentElement.classList.add('dark')
    } else {
      document.documentElement.classList.remove('dark')
    }

    // 2. Gán thuộc tính data-theme để hỗ trợ các selector CSS thuần
    document.documentElement.setAttribute('data-theme', dark ? 'dark' : 'light')

    // 3. Đồng bộ Vuetify 3 Theme
    try {
      if (vuetify?.theme?.global) {
        vuetify.theme.global.name.value = dark ? 'dark' : 'light'
      }
    } catch (e) {
      console.warn('[ThemeStore] Không thể gán vuetify theme:', e)
    }
  }

  /**
   * Đặt chế độ theme cụ thể ('light' | 'dark' | 'system')
   * @param {'light'|'dark'|'system'} newMode
   */
  function setMode(newMode) {
    if (!['light', 'dark', 'system'].includes(newMode)) return
    mode.value = newMode
    storage.set(StorageKeys.THEME_MODE, newMode)
    applyTheme()
  }

  /**
   * Chuyển đổi nhanh qua lại giữa Sáng và Tối (Light <-> Dark)
   */
  function toggleTheme() {
    if (isDark.value) {
      setMode('light')
    } else {
      setMode('dark')
    }
  }

  /**
   * Khởi tạo store: Lắng nghe sự kiện OS và áp dụng theme ngay từ đầu
   */
  function initTheme() {
    if (typeof window === 'undefined') return

    // Setup listener OS prefers-color-scheme nếu chưa gán
    if (window.matchMedia && !mediaQueryListenerAttached) {
      const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)')
      systemIsDark.value = mediaQuery.matches

      const listener = (e) => {
        systemIsDark.value = e.matches
        if (mode.value === 'system') {
          applyTheme()
        }
      }

      if (mediaQuery.addEventListener) {
        mediaQuery.addEventListener('change', listener)
      } else if (mediaQuery.addListener) {
        mediaQuery.addListener(listener)
      }

      mediaQueryListenerAttached = true
    }

    // Áp dụng theme ngay khi khởi động
    applyTheme()
  }

  return {
    mode,
    isDark,
    systemIsDark,
    currentTheme,
    themeIcon,
    themeLabel,
    setMode,
    toggleTheme,
    applyTheme,
    initTheme,
  }
})
