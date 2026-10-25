import { createRouter, createWebHistory } from 'vue-router'
import { storage, StorageKeys } from '@/core/helpers/storage'
import { ability } from '@/core/casl/ability'
import { toast } from '@/core/helpers/toast'

import authRoutes from '@/modules/auth/routes'
import dashboardRoutes from '@/modules/dashboard/routes'
import equipmentRoutes from '@/modules/equipment/routes'
import roomRoutes from '@/modules/room/routes'

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

// Navigation Guards
router.beforeEach((to, from, next) => {
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
})

export default router
