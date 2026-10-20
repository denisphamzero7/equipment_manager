import { defineStore } from 'pinia'
import { authService } from '../services/authService'
import { storage, StorageKeys } from '@/core/helpers/storage'
import { updateAbility } from '@/core/casl/ability'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    token: storage.get(StorageKeys.TOKEN, null),
    user: storage.get(StorageKeys.USER, null),
    permissions: storage.get(StorageKeys.PERMISSIONS, []),
    organizationId: storage.get(StorageKeys.ORGANIZATION_ID, 22),
    loading: false,
  }),

  getters: {
    isAuthenticated: state => !!state.token,
    userRoles: state => state.user?.roles || [],
  },

  actions: {
    async login(credentials) {
      this.loading = true
      try {
        const res = await authService.login(credentials)
        const token = res.data?.token || res.token || res.access_token
        const user = res.data?.user || res.user
        const permissions = res.data?.permissions || user?.permissions || ['*']

        this.token = token
        this.user = user
        this.permissions = permissions

        storage.set(StorageKeys.TOKEN, token)
        storage.set(StorageKeys.USER, user)
        storage.set(StorageKeys.PERMISSIONS, permissions)

        // Cập nhật CASL abilities
        updateAbility(user, permissions)

        return res
      } finally {
        this.loading = false
      }
    },

    async register(payload) {
      this.loading = true
      try {
        const res = await authService.register(payload)
        return res
      } finally {
        this.loading = false
      }
    },

    async fetchMe() {
      if (!this.token) return
      try {
        const res = await authService.getMe()
        const user = res.data || res
        this.user = user
        storage.set(StorageKeys.USER, user)
        updateAbility(user, this.permissions)
      } catch {
        this.logout()
      }
    },

    setOrganizationId(id) {
      this.organizationId = id
      storage.set(StorageKeys.ORGANIZATION_ID, id)
    },

    logout() {
      authService.logout()
      this.token = null
      this.user = null
      this.permissions = []
      storage.clearAuth()
      updateAbility(null, [])
    },
  },
})
