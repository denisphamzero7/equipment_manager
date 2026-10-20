/**
 * Trạng thái của phòng trong khách sạn.
 */
export const RoomStatus = Object.freeze({
  AVAILABLE: 'available',
  OCCUPIED: 'occupied',
  MAINTENANCE: 'maintenance',
  CLEANING: 'cleaning',
  DIRTY: 'dirty',
})

export const RoomStatusMeta = Object.freeze({
  [RoomStatus.AVAILABLE]: {
    value: RoomStatus.AVAILABLE,
    label: 'Phòng trống',
    color: 'success',
    badgeClass: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    icon: 'mdi-door-open',
  },
  [RoomStatus.OCCUPIED]: {
    value: RoomStatus.OCCUPIED,
    label: 'Có khách',
    color: 'primary',
    badgeClass: 'bg-blue-50 text-blue-700 border-blue-200',
    icon: 'mdi-account-check',
  },
  [RoomStatus.MAINTENANCE]: {
    value: RoomStatus.MAINTENANCE,
    label: 'Đang sửa chữa',
    color: 'warning',
    badgeClass: 'bg-amber-50 text-amber-700 border-amber-200',
    icon: 'mdi-hammer-wrench',
  },
  [RoomStatus.CLEANING]: {
    value: RoomStatus.CLEANING,
    label: 'Đang dọn phòng',
    color: 'info',
    badgeClass: 'bg-sky-50 text-sky-700 border-sky-200',
    icon: 'mdi-broom',
  },
  [RoomStatus.DIRTY]: {
    value: RoomStatus.DIRTY,
    label: 'Chưa dọn',
    color: 'error',
    badgeClass: 'bg-rose-50 text-rose-700 border-rose-200',
    icon: 'mdi-alert-box-outline',
  },
})
