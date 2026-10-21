import { defineStore } from 'pinia'
import { ref } from 'vue'
import { getEcho, disconnectEcho } from '../services/echoService'
import { playNotificationSound } from '../services/audioNotification'
import { toast } from '../helpers/toast'
import { storage, StorageKeys } from '../helpers/storage'

export const useRealtimeStore = defineStore('realtime', () => {
  const connected = ref(false)
  const currentOrgId = ref(null)
  const notifications = ref([])
  const unreadCount = ref(0)
  const soundEnabled = ref(true)
  const lastEvent = ref(null)

  function addNotification(notification) {
    notifications.value.unshift({
      id: Date.now() + Math.random().toString(36).substring(2, 7),
      read: false,
      timestamp: new Date().toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit', second: '2-digit' }),
      ...notification,
    })

    // Giữ tối đa 30 thông báo gần nhất
    if (notifications.value.length > 30) {
      notifications.value.pop()
    }

    unreadCount.value += 1

    if (soundEnabled.value) {
      playNotificationSound(notification.urgent ? 'urgent' : 'info')
    }
  }

  function markAllAsRead() {
    notifications.value.forEach((n) => (n.read = true))
    unreadCount.value = 0
  }

  function clearNotifications() {
    notifications.value = []
    unreadCount.value = 0
  }

  function toggleSound() {
    soundEnabled.value = !soundEnabled.value
  }

  function initRealtime(orgId) {
    const targetOrgId = Number(orgId || storage.get(StorageKeys.ORGANIZATION_ID, 22))
    if (!targetOrgId) return

    const echo = getEcho()

    // Nếu đang lắng nghe kênh khác, rời kênh cũ trước
    if (currentOrgId.value && currentOrgId.value !== targetOrgId) {
      echo.leave(`organization.${currentOrgId.value}`)
    }

    currentOrgId.value = targetOrgId
    const channelName = `organization.${targetOrgId}`

    const channel = echo.private(channelName)

    // 1. Thiết bị mới được tạo
    channel.listen('.equipment.created', (event) => {
      lastEvent.value = { type: 'created', ...event }
      addNotification({
        type: 'created',
        title: 'Thiết bị mới',
        message: event.message || `Thiết bị #${event.equipment?.id} vừa được tạo`,
        equipment: event.equipment,
      })
      toast.info(event.message || 'Một thiết bị mới vừa được thêm vào hệ thống.')
    })

    // 2. Thiết bị được cập nhật
    channel.listen('.equipment.updated', (event) => {
      lastEvent.value = { type: 'updated', ...event }
      addNotification({
        type: 'updated',
        title: 'Cập nhật thiết bị',
        message: event.message || `Thiết bị #${event.equipment?.id} vừa được cập nhật`,
        equipment: event.equipment,
      })
    })

    // 3. Thiết bị bị xóa
    channel.listen('.equipment.deleted', (event) => {
      lastEvent.value = { type: 'deleted', ...event }
      addNotification({
        type: 'deleted',
        title: 'Xóa thiết bị',
        message: event.message || `Thiết bị #${event.equipment_id} đã bị xóa`,
        equipment_id: event.equipment_id,
        room_id: event.room_id,
      })
    })

    // 4. Trạng thái thiết bị thay đổi (Tốt / Hỏng / Bảo trì / Trong kho)
    channel.listen('.equipment.status_changed', (event) => {
      lastEvent.value = { type: 'status_changed', ...event }
      addNotification({
        type: 'status_changed',
        title: 'Đổi trạng thái thiết bị',
        message: event.message || `Thiết bị #${event.equipment?.id} đã đổi trạng thái`,
        equipment: event.equipment,
      })
      toast.info(`Thiết bị #${event.equipment?.id} vừa chuyển sang trạng thái "${event.equipment?.new_status}"`)
    })

    // 5. Điều chuyển phòng / Thu hồi về kho
    channel.listen('.equipment.assigned', (event) => {
      lastEvent.value = { type: 'assigned', ...event }
      addNotification({
        type: 'assigned',
        title: 'Điều chuyển thiết bị',
        message: event.message,
        equipment: event.equipment,
      })
      toast.info(event.message)
    })

    // 6. Báo hỏng khẩn cấp
    channel.listen('.equipment.broken_reported', (event) => {
      lastEvent.value = { type: 'broken_reported', ...event }
      addNotification({
        type: 'broken_reported',
        urgent: true,
        title: event.title || `Báo hỏng phòng ${event.equipment?.room_number}`,
        message: event.message,
        equipment: event.equipment,
      })
      toast.error(event.message || `Khẩn cấp: Thiết bị phòng ${event.equipment?.room_number} vừa báo hỏng!`)
    })

    // 7. Thay đổi phòng, tầng, block (Room CRUD)
    channel.listen('.room.changed', (event) => {
      lastEvent.value = { type: 'room_' + (event.type || event.action), ...event }
      addNotification({
        type: 'room_changed',
        title: 'Cập nhật phòng',
        message: event.message || 'Dữ liệu phòng vừa được cập nhật',
        room: event.room,
      })
      toast.info(event.message || 'Dữ liệu phòng vừa được cập nhật.')
    })

    connected.value = true
  }

  function cleanup() {
    if (currentOrgId.value) {
      try {
        const echo = getEcho()
        echo.leave(`organization.${currentOrgId.value}`)
      } catch (e) {
        console.warn('Leave channel error:', e)
      }
      currentOrgId.value = null
    }
    connected.value = false
    disconnectEcho()
  }

  return {
    connected,
    currentOrgId,
    notifications,
    unreadCount,
    soundEnabled,
    lastEvent,
    initRealtime,
    addNotification,
    markAllAsRead,
    clearNotifications,
    toggleSound,
    cleanup,
  }
})
