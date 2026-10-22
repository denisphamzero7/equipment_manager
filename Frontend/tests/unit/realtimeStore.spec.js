import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useRealtimeStore } from '@/core/stores/realtimeStore'

const { getEchoMock, disconnectEchoMock } = vi.hoisted(() => ({
  getEchoMock: vi.fn(),
  disconnectEchoMock: vi.fn(),
}))

vi.mock('@/core/services/echoService', () => ({
  getEcho: getEchoMock,
  disconnectEcho: disconnectEchoMock,
}))

describe('realtimeStore', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    getEchoMock.mockReset()
    disconnectEchoMock.mockReset()
  })

  it('initializes with default state', () => {
    const store = useRealtimeStore()
    expect(store.connected).toBe(false)
    expect(store.notifications).toEqual([])
    expect(store.unreadCount).toBe(0)
    expect(store.soundEnabled).toBe(true)
  })

  it('adds notifications and increments unread count', () => {
    const store = useRealtimeStore()
    store.addNotification({
      type: 'broken_reported',
      title: 'Báo hỏng phòng 101',
      message: 'Máy sấy tóc hỏng',
    })

    expect(store.notifications.length).toBe(1)
    expect(store.unreadCount).toBe(1)
    expect(store.notifications[0].title).toBe('Báo hỏng phòng 101')
    expect(store.notifications[0].read).toBe(false)
  })

  it('caps notification list at 30 items', () => {
    const store = useRealtimeStore()
    for (let i = 0; i < 35; i++) {
      store.addNotification({
        type: 'status_changed',
        title: `Notification ${i}`,
        message: `Message ${i}`,
      })
    }

    expect(store.notifications.length).toBe(30)
    expect(store.unreadCount).toBe(35)
  })

  it('marks all notifications as read', () => {
    const store = useRealtimeStore()
    store.addNotification({ type: 'created', title: 'A', message: 'A' })
    store.addNotification({ type: 'updated', title: 'B', message: 'B' })

    expect(store.unreadCount).toBe(2)
    store.markAllAsRead()
    expect(store.unreadCount).toBe(0)
    expect(store.notifications.every((n) => n.read === true)).toBe(true)
  })

  it('toggles sound notification setting', () => {
    const store = useRealtimeStore()
    expect(store.soundEnabled).toBe(true)
    store.toggleSound()
    expect(store.soundEnabled).toBe(false)
    store.toggleSound()
    expect(store.soundEnabled).toBe(true)
  })

  it('ignores realtime initialization that finishes after cleanup', async () => {
    let resolveEcho
    getEchoMock.mockReturnValue(new Promise(resolve => {
      resolveEcho = resolve
    }))

    const store = useRealtimeStore()
    const initialization = store.initRealtime(7)
    store.cleanup()

    const echo = { private: vi.fn() }
    resolveEcho(echo)
    await initialization

    expect(echo.private).not.toHaveBeenCalled()
    expect(store.connected).toBe(false)
    expect(store.currentOrgId).toBe(null)
  })

  it('shares an in-progress realtime initialization for the same organization', async () => {
    const channel = { listen: vi.fn() }
    const echo = {
      private: vi.fn(() => channel),
      leave: vi.fn(),
    }
    getEchoMock.mockResolvedValue(echo)

    const store = useRealtimeStore()
    const firstInitialization = store.initRealtime(7)
    const secondInitialization = store.initRealtime(7)

    await Promise.all([firstInitialization, secondInitialization])

    expect(getEchoMock).toHaveBeenCalledTimes(1)
    expect(echo.private).toHaveBeenCalledTimes(1)
    expect(echo.private).toHaveBeenCalledWith('organization.7')
    expect(store.connected).toBe(true)
  })
})
