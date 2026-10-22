import { beforeEach, describe, expect, it, vi } from 'vitest'
import {
  clearRequestCache,
  getCachedRequest,
  invalidateRequestCache,
} from '@/core/services/requestCache'

describe('requestCache', () => {
  beforeEach(() => {
    clearRequestCache()
    localStorage.removeItem('eq_active_org_id')
  })

  it('deduplicates concurrent requests and caches the result', async () => {
    const loader = vi.fn().mockResolvedValue({ value: 'cached' })

    const [first, second] = await Promise.all([
      getCachedRequest('sample', 'item', loader),
      getCachedRequest('sample', 'item', loader),
    ])

    expect(first).toEqual({ value: 'cached' })
    expect(second).toEqual(first)
    expect(loader).toHaveBeenCalledTimes(1)
    await expect(getCachedRequest('sample', 'item', loader)).resolves.toEqual(first)
    expect(loader).toHaveBeenCalledTimes(1)
  })

  it('keeps values isolated by organization', async () => {
    localStorage.setItem('eq_active_org_id', JSON.stringify(10))
    await getCachedRequest('sample', 'item', () => 'organization 10')

    localStorage.setItem('eq_active_org_id', JSON.stringify(20))
    await expect(getCachedRequest('sample', 'item', () => 'organization 20'))
      .resolves.toBe('organization 20')
  })

  it('does not cache a stale request after invalidation', async () => {
    let resolveStale
    const staleRequest = getCachedRequest(
      'sample',
      'item',
      () => new Promise(resolve => { resolveStale = resolve })
    )

    invalidateRequestCache('sample')
    await expect(getCachedRequest('sample', 'item', () => 'fresh')).resolves.toBe('fresh')
    resolveStale('stale')
    await staleRequest

    await expect(getCachedRequest('sample', 'item', () => 'unexpected')).resolves.toBe('fresh')
  })

  it('expires cached values after the configured TTL', async () => {
    const now = vi.spyOn(Date, 'now')
    now.mockReturnValue(1000)
    const loader = vi.fn()
      .mockResolvedValueOnce('first')
      .mockResolvedValueOnce('second')

    await expect(getCachedRequest('sample', 'item', loader, { ttl: 50 })).resolves.toBe('first')
    now.mockReturnValue(1051)
    await expect(getCachedRequest('sample', 'item', loader, { ttl: 50 })).resolves.toBe('second')
    expect(loader).toHaveBeenCalledTimes(2)
    now.mockRestore()
  })

  it('propagates request failures and retries instead of caching them', async () => {
    const loader = vi.fn()
      .mockRejectedValueOnce(new Error('network error'))
      .mockResolvedValueOnce('recovered')

    await expect(getCachedRequest('sample', 'item', loader)).rejects.toThrow('network error')
    await expect(getCachedRequest('sample', 'item', loader)).resolves.toBe('recovered')
    expect(loader).toHaveBeenCalledTimes(2)
  })
})
