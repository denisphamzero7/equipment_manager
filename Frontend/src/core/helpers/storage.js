/**
 * LocalStorage wrapper with JSON serialization & safety.
 */
export const StorageKeys = Object.freeze({
  TOKEN: 'eq_access_token',
  USER: 'eq_user_profile',
  ORGANIZATION_ID: 'eq_active_org_id',
  PERMISSIONS: 'eq_permissions',
})

export const storage = {
  get(key, defaultValue = null) {
    try {
      const item = localStorage.getItem(key)
      return item ? JSON.parse(item) : defaultValue
    } catch {
      return localStorage.getItem(key) || defaultValue
    }
  },

  set(key, value) {
    try {
      const serialized = typeof value === 'object' ? JSON.stringify(value) : value
      localStorage.setItem(key, serialized)
    } catch (e) {
      console.error('Storage set error:', e)
    }
  },

  remove(key) {
    try {
      localStorage.removeItem(key)
    } catch (e) {
      console.error('Storage remove error:', e)
    }
  },

  clearAuth() {
    this.remove(StorageKeys.TOKEN)
    this.remove(StorageKeys.USER)
    this.remove(StorageKeys.PERMISSIONS)
  },
}
