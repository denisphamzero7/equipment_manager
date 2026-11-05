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
   * [CƠ CHẾ HOẠT HỌA THEME CHUYỂN ĐỔI SIÊU MƯỢT]
   * Sử dụng Web View Transitions API (tiêu chuẩn W3C hiện đại trên Chrome/Edge/Safari).
   * 
   * 1. Luồng hoạt động:
   *    - Xác định tọa độ con trỏ chuột (x, y) từ click event để làm tâm của vòng tròn lan tỏa.
   *    - Tính bán kính cực đại (endRadius) từ tâm (x, y) tới góc xa nhất của màn hình bằng định lý Pytago.
   *    - Gọi document.startViewTransition(): Trình duyệt chụp ảnh chụp tĩnh (snapshot) giao diện cũ.
   *    - Thực thi hàm updateFn(): DOM cập nhật theme mới (thêm/xóa class .dark, đổi màu Vuetify).
   *    - Áp dụng hoạt ảnh clip-path hình tròn mở rộng từ 0px đến endRadius trên pseudo-element ::view-transition-new(root).
   * 
   * 2. Tối ưu hiệu năng (Zero Lag / 60-120 FPS):
   *    - Thời lượng (duration) chuẩn là 220ms với đường cong 'ease-out': tạo cảm giác tức thì (snappy), không bị trễ.
   *    - Không can thiệp transition CSS lên toàn bộ DOM (*) để tránh ép GPU/CPU tính toán lại layout.
   *    - Tôn trọng thuộc tính accessibility prefers-reduced-motion của hệ điều hành.
   * 
   * @param {Function} updateFn Hàm thực thi thay đổi trạng thái theme (DOM mutation)
   * @param {MouseEvent|null} event Sự kiện click chuột để định vị tọa độ tâm sóng tròn
   */
  function runThemeTransition(updateFn, event = null) {
    if (typeof document === 'undefined') {
      updateFn()
      return
    }

    // Kiểm tra trình duyệt có hỗ trợ View Transitions API và người dùng không bật chế độ giảm chuyển động
    const isAppearanceTransition =
      'startViewTransition' in document &&
      window.matchMedia &&
      !window.matchMedia('(prefers-reduced-motion: reduce)').matches

    // Nếu không hỗ trợ hoặc người dùng bật giảm chuyển động: đổi theme ngay lập tức (không chạy animation clip-path)
    if (!isAppearanceTransition) {
      updateFn()
      return
    }

    // Tọa độ tâm lan tỏa (mặc định ở giữa màn hình nếu không có sự kiện chuột)
    const x = event?.clientX ?? window.innerWidth / 2
    const y = event?.clientY ?? window.innerHeight / 2

    // Bán kính xa nhất từ tâm chuột tới 4 góc màn hình để vòng tròn bao phủ trọn vẹn màn hình
    const endRadius = Math.hypot(
      Math.max(x, window.innerWidth - x),
      Math.max(y, window.innerHeight - y)
    )

    // Khởi tạo tiến trình chụp snapshot giao diện và chuyển đổi
    const transition = document.startViewTransition(() => {
      updateFn()
    })

    // Khi snapshot đã sẵn sàng trên GPU, chạy animation mở rộng vòng tròn clip-path
    transition.ready.then(() => {
      const clipPath = [
        `circle(0px at ${x}px ${y}px)`,
        `circle(${endRadius}px at ${x}px ${y}px)`,
      ]
      document.documentElement.animate(
        {
          clipPath: clipPath,
        },
        {
          duration: 220, // 220ms: Tốc độ vàng phản hồi giao diện, đảm bảo mượt mà và dứt khoát
          easing: 'ease-out',
          pseudoElement: '::view-transition-new(root)',
        }
      )
    })
  }

  /**
   * Đặt chế độ theme cụ thể ('light' | 'dark' | 'system')
   * @param {'light'|'dark'|'system'} newMode
   * @param {MouseEvent|null} event
   */
  function setMode(newMode, event = null) {
    if (!['light', 'dark', 'system'].includes(newMode)) return
    if (mode.value === newMode) return

    const executeChange = () => {
      mode.value = newMode
      storage.set(StorageKeys.THEME_MODE, newMode)
      applyTheme()
    }

    if (event) {
      runThemeTransition(executeChange, event)
    } else {
      executeChange()
    }
  }

  /**
   * Chuyển đổi nhanh qua lại giữa Sáng và Tối (Light <-> Dark)
   * @param {MouseEvent|null} event
   */
  function toggleTheme(event = null) {
    const nextMode = isDark.value ? 'light' : 'dark'
    setMode(nextMode, event)
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
