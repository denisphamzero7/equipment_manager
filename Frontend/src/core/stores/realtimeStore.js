import { defineStore } from 'pinia'
import { ref } from 'vue'
import { getEcho, disconnectEcho } from '../services/echoService'
import { playNotificationSound } from '../services/audioNotification'
import { toast } from '../helpers/toast'
import { storage, StorageKeys } from '../helpers/storage'
import { invalidateRequestCache } from '../services/requestCache'

export const useRealtimeStore = defineStore('realtime', () => {
  const connected = ref(false)
  const isListening = ref(false)
  const currentOrgId = ref(null)
  const notifications = ref([])
  const unreadCount = ref(0)
  const soundEnabled = ref(true)
  const lastEvent = ref(null)

  let lastNotificationFingerprint = ''
  let lastNotificationTime = 0
  let initializationSequence = 0
  let pendingOrgId = null
  let initializationPromise = null

  function addNotification(notification) {
    const fingerprint = `${notification.type}_${notification.title}_${notification.message}`
    const now = Date.now()
    if (fingerprint === lastNotificationFingerprint && (now - lastNotificationTime) < 2000) {
      // Bỏ qua sự kiện trùng lặp trong vòng 2 giây
      return
    }
    lastNotificationFingerprint = fingerprint
    lastNotificationTime = now

    notifications.value.unshift({
      id: now + Math.random().toString(36).substring(2, 7),
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

  function invalidateChangedData(isRoomChange = false, roomEquipmentCountsChanged = false) {
    invalidateRequestCache(
      'equipment-list',
      'equipment-detail',
      'equipment-map',
      'equipment-room-detail',
      'dashboard-stats',
      ...(isRoomChange ? ['room-list', 'room-detail', 'equipment-room-options'] : []),
      ...(roomEquipmentCountsChanged ? ['room-list'] : [])
    )
  }

  function initRealtime(orgId) {
    const targetOrgId = Number(orgId || storage.get(StorageKeys.ORGANIZATION_ID) || 1)
    if (!targetOrgId) return

    // Nếu đã đăng ký listener cho chính xác tổ chức này, không đăng ký trùng lặp
    if (isListening.value && currentOrgId.value === targetOrgId) {
      return
    }
    if (initializationPromise && pendingOrgId === targetOrgId) {
      return initializationPromise
    }

    const sequence = ++initializationSequence
    pendingOrgId = targetOrgId
    const initialization = (async () => {
      const echo = await getEcho()
      if (!echo || sequence !== initializationSequence) return

      // Rời kênh cũ nếu đang lắng nghe để tránh gắn đè listeners
      if (currentOrgId.value) {
        echo.leave(`organization.${currentOrgId.value}`)
      }

      currentOrgId.value = targetOrgId
      const channelName = `organization.${targetOrgId}`

      const channel = echo.private(channelName)

    // 1. Thiết bị mới được tạo
    channel.listen('.equipment.created', (event) => {
      invalidateChangedData(false, true)
      lastEvent.value = { type: 'created', ...event }
      addNotification({
        type: 'created',
        title: 'Thiết bị mới',
        message: event.message || `Thiết bị #${event.equipment?.id} vừa được tạo`,
        equipment: event.equipment,
      })
      toast.info(event.message || 'Một thiết bị mới vừa được thêm vào hệ thống.')
    })

    channel.listen('.equipment.imported', (event) => {
      invalidateChangedData()
      lastEvent.value = { type: 'imported', ...event }
      addNotification({
        type: 'imported',
        title: 'Nhập thiết bị từ Excel',
        message: event.message || `Đã nhập ${event.imported_count || 0} thiết bị.`,
      })
    })

    // 2. Thiết bị được cập nhật
    channel.listen('.equipment.updated', (event) => {
      invalidateChangedData()
      lastEvent.value = { type: 'updated', ...event }
      const modelName = event.equipment?.model_name || 'Thiết bị'
      const msg = event.message || `Thiết bị #${event.equipment?.id} (${modelName}) vừa được cập nhật tiến độ.`
      addNotification({
        type: 'updated',
        title: 'Cập nhật thiết bị',
        message: msg,
        equipment: event.equipment,
      })
      toast.info(msg)
    })

    // 3. Thiết bị bị xóa
    channel.listen('.equipment.deleted', (event) => {
      invalidateChangedData(false, true)
      lastEvent.value = { type: 'deleted', ...event }
      addNotification({
        type: 'deleted',
        title: 'Xóa thiết bị',
        message: event.message || `Thiết bị #${event.equipment_id} đã bị xóa khỏi hệ thống.`,
        equipment_id: event.equipment_id,
        room_id: event.room_id,
      })
      toast.warning(event.message || `Thiết bị #${event.equipment_id} đã bị xóa.`)
    })

    // 4. Trạng thái thiết bị thay đổi (Tốt / Hỏng / Bảo trì / Trong kho)
    channel.listen('.equipment.status_changed', (event) => {
      invalidateChangedData()
      lastEvent.value = { type: 'status_changed', ...event }
      const statusLabels = {
        good: 'Hoạt động tốt',
        broken: 'Hư hỏng',
        maintenance: 'Đang bảo trì / sửa chữa',
        in_stock: 'Lưu trong kho',
        disposed: 'Đã thanh lý',
      }
      const rawStatus = event.equipment?.new_status
      const label = statusLabels[rawStatus] || rawStatus || 'mới'
      const modelName = event.equipment?.model_name || 'Thiết bị'
      const msg = `Thiết bị #${event.equipment?.id} (${modelName}) chuyển sang trạng thái: "${label}".`

      addNotification({
        type: 'status_changed',
        title: 'Đổi trạng thái thiết bị',
        message: event.message || msg,
        equipment: event.equipment,
      })
      toast.info(msg)
    })

    // 5. Điều chuyển phòng / Thu hồi về kho
    channel.listen('.equipment.assigned', (event) => {
      invalidateChangedData(false, true)
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
      invalidateChangedData()
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
      invalidateChangedData(true)
      lastEvent.value = { ...event, type: 'room_' + (event.type || event.action) }
      addNotification({
        type: 'room_changed',
        title: 'Cập nhật phòng',
        message: event.message || 'Dữ liệu phòng vừa được cập nhật',
        room: event.room,
      })
      toast.info(event.message || 'Dữ liệu phòng vừa được cập nhật.')
    })

      isListening.value = true
      connected.value = true
    })()

    initializationPromise = initialization
      .catch((error) => {
        if (sequence === initializationSequence) {
          console.error('Failed to initialize realtime connection:', error)
          toast.warning('Không thể khởi tạo kết nối cập nhật trực tiếp. Vui lòng tải lại trang.')
          connected.value = false
          isListening.value = false
          currentOrgId.value = null
        }
      })
      .finally(() => {
        if (sequence === initializationSequence) {
          initializationPromise = null
          pendingOrgId = null
        }
      })

    return initializationPromise
  }

  function cleanup() {
    initializationSequence += 1
    initializationPromise = null
    pendingOrgId = null
    currentOrgId.value = null
    isListening.value = false
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
