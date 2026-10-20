export const BadgeColor = Object.freeze({
  GREEN: 'green',
  YELLOW: 'yellow',
  RED: 'red',
  GRAY: 'gray',
})

export const BadgeColorMeta = Object.freeze({
  [BadgeColor.GREEN]: {
    label: 'Đầy đủ & Hoạt động tốt',
    bg: 'bg-emerald-500',
    border: 'border-emerald-300',
    text: 'text-emerald-700',
    lightBg: 'bg-emerald-50',
  },
  [BadgeColor.YELLOW]: {
    label: 'Cảnh báo / Đang bảo trì',
    bg: 'bg-amber-500',
    border: 'border-amber-300',
    text: 'text-amber-700',
    lightBg: 'bg-amber-50',
  },
  [BadgeColor.RED]: {
    label: 'Có thiết bị hỏng hoặc thiếu',
    bg: 'bg-rose-500',
    border: 'border-rose-300',
    text: 'text-rose-700',
    lightBg: 'bg-rose-50',
  },
  [BadgeColor.GRAY]: {
    label: 'Chưa cấu hình thiết bị',
    bg: 'bg-slate-400',
    border: 'border-slate-300',
    text: 'text-slate-600',
    lightBg: 'bg-slate-100',
  },
})
