/**
 * Trạng thái của phòng trong khách sạn.
 */
export const RoomStatus = Object.freeze({
  READY: 'ready',
  OCCUPIED: 'occupied',
  NOT_READY: 'not_ready',
  MAINTENANCE: 'maintenance',
  AVAILABLE: 'ready', // alias
  CLEANING: 'not_ready', // alias
})

export const RoomStatusOptions = [
  { value: 'ready', label: 'Sẵn sàng đón khách' },
  { value: 'occupied', label: 'Đang có khách' },
  { value: 'not_ready', label: 'Chờ dọn dẹp' },
  { value: 'maintenance', label: 'Đang bảo trì' },
]

export const RoomStatusMeta = Object.freeze({
  ready: {
    value: 'ready',
    label: 'Sẵn sàng',
    color: 'success',
    badgeClass: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    icon: 'mdi-door-open',
  },
  available: {
    value: 'ready',
    label: 'Sẵn sàng',
    color: 'success',
    badgeClass: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    icon: 'mdi-door-open',
  },
  occupied: {
    value: 'occupied',
    label: 'Đang có khách',
    color: 'error',
    badgeClass: 'bg-rose-50 text-rose-700 border-rose-200',
    icon: 'mdi-account-check',
  },
  not_ready: {
    value: 'not_ready',
    label: 'Chờ dọn dẹp',
    color: 'warning',
    badgeClass: 'bg-amber-50 text-amber-700 border-amber-200',
    icon: 'mdi-broom',
  },
  cleaning: {
    value: 'not_ready',
    label: 'Chờ dọn dẹp',
    color: 'warning',
    badgeClass: 'bg-amber-50 text-amber-700 border-amber-200',
    icon: 'mdi-broom',
  },
  maintenance: {
    value: 'maintenance',
    label: 'Đang bảo trì',
    color: 'grey',
    badgeClass: 'bg-slate-100 text-slate-700 border-slate-300',
    icon: 'mdi-hammer-wrench',
  },
})

/**
 * Hàm lấy metadata trạng thái phòng có fallback an toàn
 */
export function getRoomStatusMeta(status) {
  return RoomStatusMeta[status] || {
    value: status,
    label: status || 'Không xác định',
    color: 'secondary',
    badgeClass: 'bg-slate-100 text-slate-700 border-slate-300',
    icon: 'mdi-help-circle',
  }
}

