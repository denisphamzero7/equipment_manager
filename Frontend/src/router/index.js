import { createRouter, createWebHistory } from 'vue-router'
import { storage, StorageKeys } from '@/core/helpers/storage'
import { ability } from '@/core/casl/ability'
import { toast } from '@/core/helpers/toast'

import authRoutes from '@/modules/auth/routes'
import dashboardRoutes from '@/modules/dashboard/routes'
import equipmentRoutes from '@/modules/equipment/routes'
import roomRoutes from '@/modules/room/routes'

/**
 * [1. MỤC ĐÍCH]
 * Định nghĩa cây điều hướng URL (Vue Router) cho toàn bộ ứng dụng:
 * Gom cụm các route từ các module độc lập (Auth, Dashboard, Equipment, Room).
 */
const routes = [
  {
    path: '/',
    redirect: '/dashboard',
  },
  ...authRoutes,
  ...dashboardRoutes,
  ...equipmentRoutes,
  ...roomRoutes,
  {
    path: '/:pathMatch(.*)*',
    redirect: '/dashboard',
  },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

/**
 * [NAVIGATION GUARDS - BEFORE EACH]
 * [1. MỤC ĐÍCH]
 * Kiểm soát quyền truy cập trước khi chuyển trang:
 * 1. Kiểm tra yêu cầu đăng nhập (`requiresAuth`): Nếu chưa có token -> Điều hướng về `/login`.
 * 2. Chặn trang Guest (`guestOnly`): Nếu đã đăng nhập mà truy cập `/login` -> Đưa về `/dashboard`.
 * 3. Kiểm tra quyền hạn CASL Ability (`to.meta.action` & `to.meta.subject`): Nếu không đủ quyền -> Cảnh báo lỗi và chặn truy cập.
 */
router.beforeEach((to, from, next) => {
  try {
    const token = storage.get(StorageKeys.TOKEN)

    // 1. Kiểm tra yêu cầu đăng nhập
    if (to.meta.requiresAuth && !token) {
      toast.warning('Vui lòng đăng nhập để tiếp tục.')
      return next({ name: 'login' })
    }

    // 2. Chặn trang guest (login/register) nếu đã đăng nhập
    if (to.meta.guestOnly && token) {
      return next({ name: 'dashboard' })
    }

    // 3. Phân quyền CASL Ability Check
    if (to.meta.action && to.meta.subject) {
      if (!ability.can(to.meta.action, to.meta.subject)) {
        toast.error('Bạn không có quyền truy cập vào trang này.')
        if (to.name !== 'dashboard') {
          return next({ name: 'dashboard' })
        }
        return next(false)
      }
    }

    next()
  } catch (err) {
    console.error('Lỗi navigation guard:', err)
    next()
  }
})

/**
 * [ROUTER ERROR HANDLER - TỰ ĐỘNG KHÔI PHỤC KHI DEPLOY MÃ MỚI]
 * [1. MỤC ĐÍCH]
 * Xử lý lỗi không tải được file chunk (`Failed to fetch dynamically imported module` / `ChunkLoadError`)
 * khi hệ thống vừa build bản mới trên server khiến mã hash cũ trên trình duyệt người dùng bị 404.
 *
 * [2. LUỒNG XỬ LÝ (FLOW)]
 * 1. Nhận diện lỗi ChunkLoadError.
 * 2. Kiểm tra `sessionStorage` để chống vòng lặp reload liên tục nếu mất mạng hoàn toàn.
 * 3. Tự động tải lại trang (`window.location.reload()`) để trình duyệt nạp manifest và chunk bundle mới nhất.
 */
router.onError((error, to) => {
  console.error('Lỗi Vue Router:', error)

  const errorMsg = error?.message || String(error)
  const isChunkLoadFailed =
    errorMsg.includes('Failed to fetch dynamically imported module') ||
    errorMsg.includes('Importing a module script failed') ||
    errorMsg.includes('error loading dynamically imported module') ||
    error?.name === 'ChunkLoadError'

  if (isChunkLoadFailed) {
    const targetPath = to?.fullPath || window.location.pathname
    const reloadKey = `chunk_reload_${targetPath}`
    const lastReload = sessionStorage.getItem(reloadKey)
    const now = Date.now()

    // Tránh reload lặp vô hạn nếu rớt mạng hoàn toàn (trong vòng 10 giây)
    if (lastReload && now - Number(lastReload) < 10000) {
      sessionStorage.removeItem(reloadKey)
      toast.error('Phiên bản hệ thống đã được cập nhật hoặc kết nối mạng bị gián đoạn. Vui lòng bấm Ctrl + F5 để tải lại.')
      return
    }

    sessionStorage.setItem(reloadKey, String(now))
    if (to?.fullPath) {
      window.location.href = to.fullPath
    } else {
      window.location.reload()
    }
  }
})

export default router
