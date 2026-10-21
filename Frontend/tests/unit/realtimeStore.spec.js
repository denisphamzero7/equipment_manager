import { describe, it, expect, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useRealtimeStore } from '@/core/stores/realtimeStore'

describe('realtimeStore', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
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
})
