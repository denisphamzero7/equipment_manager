import { storage, StorageKeys } from '@/core/helpers/storage'

const values = new Map()
const requests = new Map()
const generations = new Map()

function cacheKey(namespace, key) {
  const organizationId = storage.get(StorageKeys.ORGANIZATION_ID, 22)
  return `${namespace}|${organizationId}|${key}`
}

export function getCachedRequest(namespace, key, loader, { ttl = 30000, force = false } = {}) {
  const fullKey = cacheKey(namespace, key)
  const now = Date.now()
  for (const [cacheEntryKey, entry] of values) {
    if (entry.expiresAt <= now) values.delete(cacheEntryKey)
  }
  const cached = values.get(fullKey)
  if (!force && cached && cached.expiresAt > now) {
    return Promise.resolve(cached.value)
  }

  const pending = requests.get(fullKey)
  if (!force && pending) return pending

  const generation = generations.get(namespace) || 0
  generations.set(namespace, generation)
  const request = Promise.resolve()
    .then(loader)
    .then(value => {
      if (
        (generations.get(namespace) || 0) === generation &&
        requests.get(fullKey) === request
      ) {
        values.set(fullKey, { value, expiresAt: Date.now() + ttl })
      }
      return value
    })
    .finally(() => {
      if (requests.get(fullKey) === request) requests.delete(fullKey)
    })

  requests.set(fullKey, request)
  return request
}

export function invalidateRequestCache(...namespaces) {
  for (const namespace of namespaces) {
    generations.set(namespace, (generations.get(namespace) || 0) + 1)
    const prefix = `${namespace}|`
    for (const key of values.keys()) {
      if (key.startsWith(prefix)) values.delete(key)
    }
    for (const key of requests.keys()) {
      if (key.startsWith(prefix)) requests.delete(key)
    }
  }
}

export function clearRequestCache() {
  values.clear()
  requests.clear()
  for (const namespace of generations.keys()) {
    generations.set(namespace, (generations.get(namespace) || 0) + 1)
  }
}
