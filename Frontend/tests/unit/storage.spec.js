import { describe, it, expect, beforeEach } from 'vitest'
import { storage, StorageKeys } from '@/core/helpers/storage'

describe('Storage Helper', () => {
  beforeEach(() => {
    localStorage.clear()
  })

  it('stores and retrieves serialized objects', () => {
    const user = { id: 1, name: 'Admin', role: 'Super Admin' }
    storage.set(StorageKeys.USER, user)
    const retrieved = storage.get(StorageKeys.USER)
    expect(retrieved).toEqual(user)
  })

  it('returns default value when key does not exist', () => {
    const result = storage.get('non_existent_key', 22)
    expect(result).toBe(22)
  })

  it('removes item properly', () => {
    storage.set(StorageKeys.TOKEN, 'sample_token_xyz')
    expect(storage.get(StorageKeys.TOKEN)).toBe('sample_token_xyz')
    storage.remove(StorageKeys.TOKEN)
    expect(storage.get(StorageKeys.TOKEN)).toBeNull()
  })

  it('clears all auth data on clearAuth', () => {
    storage.set(StorageKeys.TOKEN, 'token123')
    storage.set(StorageKeys.USER, { name: 'Admin' })
    storage.set(StorageKeys.PERMISSIONS, ['*'])
    storage.set(StorageKeys.ORGANIZATION_ID, 22)

    storage.clearAuth()

    expect(storage.get(StorageKeys.TOKEN)).toBeNull()
    expect(storage.get(StorageKeys.USER)).toBeNull()
    expect(storage.get(StorageKeys.PERMISSIONS)).toBeNull()
    // OrganizationId should persist across logouts
    expect(storage.get(StorageKeys.ORGANIZATION_ID)).toBe(22)
  })
})
