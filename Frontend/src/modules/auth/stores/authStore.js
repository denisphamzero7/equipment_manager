import { defineStore } from 'pinia'
import { authService } from '../services/authService'
import { storage, StorageKeys } from '@/core/helpers/storage'
import { updateAbility } from '@/core/casl/ability'
import { clearRequestCache } from '@/core/services/requestCache'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    token: storage.get(StorageKeys.TOKEN, null),
    user: storage.get(StorageKeys.USER, null),
    permissions: storage.get(StorageKeys.PERMISSIONS, []),
    organizationId: storage.get(StorageKeys.ORGANIZATION_ID, null),
    availableOrganizations: storage.get('eq_available_orgs', []),
    loading: false,
  }),

  getters: {
    isAuthenticated: state => !!state.token,
    userRoles: state => state.user?.roles || [],
    currentOrganization: state => {
      return state.availableOrganizations.find(o => o.id === state.organizationId) || {
        id: state.organizationId,
        name: 'Tổ chức hiện tại',
      }
    },
  },

  actions: {
    async login(credentials) {
      this.loading = true
      try {
        const res = await authService.login(credentials)
        const data = res.data || {}
        clearRequestCache()

        // Sửa lỗi lấy access_token chuẩn xác từ backend
        const token = data.access_token || data.token || res.access_token || res.token
        const user = data.user || res.user
        const permissions = data.permissions || user?.permissions || ['*']
        const availableOrgs = data.available_organizations || []
        const currentOrgId = data.current_organization_id || (availableOrgs[0]?.id ?? null)

        this.token = token
        this.user = user
        this.permissions = permissions
        this.availableOrganizations = availableOrgs
        this.organizationId = currentOrgId

        storage.set(StorageKeys.TOKEN, token)
        storage.set(StorageKeys.USER, user)
        storage.set(StorageKeys.PERMISSIONS, permissions)
        storage.set(StorageKeys.ORGANIZATION_ID, currentOrgId)
        storage.set('eq_available_orgs', availableOrgs)

        // Cập nhật CASL abilities
        updateAbility(user, permissions)

        return {
          res,
          availableOrganizations: availableOrgs,
          currentOrganizationId: currentOrgId,
        }
      } finally {
        this.loading = false
      }
    },

    async selectOrganization(orgId) {
      this.loading = true
      try {
        const res = await authService.switchOrganization(orgId)
        clearRequestCache()
        const data = res.data || {}

        this.organizationId = Number(orgId)
        storage.set(StorageKeys.ORGANIZATION_ID, Number(orgId))

        if (data.permissions) {
          this.permissions = data.permissions
          storage.set(StorageKeys.PERMISSIONS, data.permissions)
          updateAbility(this.user, data.permissions)
        }

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
        const data = res.data || {}
        this.user = data.user || data
        this.permissions = data.permissions || this.permissions
        storage.set(StorageKeys.USER, this.user)
        storage.set(StorageKeys.PERMISSIONS, this.permissions)
        updateAbility(this.user, this.permissions)
      } catch {
        this.logout()
      }
    },

    setOrganizationId(id) {
      this.organizationId = Number(id)
      storage.set(StorageKeys.ORGANIZATION_ID, Number(id))
    },

    logout() {
      authService.logout()
      clearRequestCache()
      this.token = null
      this.user = null
      this.permissions = []
      this.availableOrganizations = []
      storage.clearAuth()
      storage.remove('eq_available_orgs')
      updateAbility(null, [])
    },
  },
})
