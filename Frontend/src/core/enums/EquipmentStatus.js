/**
 * Trạng thái của thiết bị trong khách sạn.
 * Đồng bộ chính xác với EquipmentStatusEnum.php ở Backend.
 */
export const EquipmentStatus = Object.freeze({
  GOOD: 'good',
  BROKEN: 'broken',
  MAINTENANCE: 'maintenance',
  LOST: 'lost',
  IN_STOCK: 'in_stock',
  DISPOSED: 'disposed',
})

export const EquipmentStatusMeta = Object.freeze({
  [EquipmentStatus.GOOD]: {
    value: EquipmentStatus.GOOD,
    label: 'Hoạt động tốt',
    color: 'success',
    badgeClass: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    icon: 'mdi-check-circle-outline',
  },
  [EquipmentStatus.BROKEN]: {
    value: EquipmentStatus.BROKEN,
    label: 'Hư hỏng',
    color: 'error',
    badgeClass: 'bg-rose-50 text-rose-700 border-rose-200',
    icon: 'mdi-alert-circle-outline',
  },
  [EquipmentStatus.MAINTENANCE]: {
    value: EquipmentStatus.MAINTENANCE,
    label: 'Đang bảo trì',
    color: 'warning',
    badgeClass: 'bg-amber-50 text-amber-700 border-amber-200',
    icon: 'mdi-wrench-outline',
  },
  [EquipmentStatus.LOST]: {
    value: EquipmentStatus.LOST,
    label: 'Thất lạc / Báo mất',
    color: 'secondary',
    badgeClass: 'bg-slate-100 text-slate-700 border-slate-300',
    icon: 'mdi-help-circle-outline',
  },
  [EquipmentStatus.IN_STOCK]: {
    value: EquipmentStatus.IN_STOCK,
    label: 'Trong kho',
    color: 'info',
    badgeClass: 'bg-sky-50 text-sky-700 border-sky-200',
    icon: 'mdi-package-variant-closed',
  },
  [EquipmentStatus.DISPOSED]: {
    value: EquipmentStatus.DISPOSED,
    label: 'Đã thanh lý',
    color: 'secondary',
    badgeClass: 'bg-zinc-100 text-zinc-600 border-zinc-300',
    icon: 'mdi-delete-sweep-outline',
  },
})

export const EquipmentStatusOptions = Object.values(EquipmentStatusMeta).map(item => ({
  value: item.value,
  title: item.label,
  color: item.color,
  icon: item.icon,
}))
