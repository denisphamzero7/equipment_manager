/**
 * Danh sách và thứ tự các Block / Tòa nhà trong khách sạn.
 * Khi thêm tòa nhà mới hoặc đổi tên block, chỉ cần cập nhật tại file này.
 */
export const HotelBlocks = Object.freeze({
  BLOCK_A: 'Block A',
  BLOCK_B: 'Block B',
  BLOCK_C: 'Block C',
  BLOCK_D: 'Block D',
  CONDOS_1: 'Condos 1',
  CONDOS_2: 'Condos 2',
  PENTHOUSE_VILLA: 'Penthouse-spa+Villa',
})

/**
 * Thứ tự chuẩn để sắp xếp các Block khi hiển thị danh sách, tab, dropdown, bản đồ.
 */
export const HotelBlockOrder = Object.freeze([
  HotelBlocks.BLOCK_A,
  HotelBlocks.BLOCK_B,
  HotelBlocks.BLOCK_C,
  HotelBlocks.BLOCK_D,
  HotelBlocks.CONDOS_1,
  HotelBlocks.CONDOS_2,
  HotelBlocks.PENTHOUSE_VILLA,
])

/**
 * Metadata cho từng Block (Tên rút gọn, màu sắc đại diện, icon)
 */
export const HotelBlockMeta = Object.freeze({
  [HotelBlocks.BLOCK_A]: {
    value: HotelBlocks.BLOCK_A,
    label: 'Khu A',
    color: 'primary',
    badgeClass: 'bg-blue-50 text-blue-700 border-blue-200',
    icon: 'mdi-office-building',
  },
  [HotelBlocks.BLOCK_B]: {
    value: HotelBlocks.BLOCK_B,
    label: 'Khu B',
    color: 'indigo',
    badgeClass: 'bg-indigo-50 text-indigo-700 border-indigo-200',
    icon: 'mdi-office-building',
  },
  [HotelBlocks.BLOCK_C]: {
    value: HotelBlocks.BLOCK_C,
    label: 'Khu C',
    color: 'purple',
    badgeClass: 'bg-purple-50 text-purple-700 border-purple-200',
    icon: 'mdi-office-building',
  },
  [HotelBlocks.BLOCK_D]: {
    value: HotelBlocks.BLOCK_D,
    label: 'Khu D',
    color: 'teal',
    badgeClass: 'bg-teal-50 text-teal-700 border-teal-200',
    icon: 'mdi-office-building',
  },
  [HotelBlocks.CONDOS_1]: {
    value: HotelBlocks.CONDOS_1,
    label: 'Condos 1',
    color: 'amber',
    badgeClass: 'bg-amber-50 text-amber-700 border-amber-200',
    icon: 'mdi-home-modern',
  },
  [HotelBlocks.CONDOS_2]: {
    value: HotelBlocks.CONDOS_2,
    label: 'Condos 2',
    color: 'orange',
    badgeClass: 'bg-orange-50 text-orange-700 border-orange-200',
    icon: 'mdi-home-modern',
  },
  [HotelBlocks.PENTHOUSE_VILLA]: {
    value: HotelBlocks.PENTHOUSE_VILLA,
    label: 'Penthouse & Villa',
    color: 'rose',
    badgeClass: 'bg-rose-50 text-rose-700 border-rose-200',
    icon: 'mdi-home-city',
  },
})

/**
 * Mảng Options phục vụ select / combobox
 */
export const HotelBlockOptions = Object.freeze(
  HotelBlockOrder.map(block => ({
    value: block,
    title: block,
  }))
)

/**
 * Hàm lấy metadata của Block kèm fallback an toàn
 */
export function getHotelBlockMeta(block) {
  return HotelBlockMeta[block] || {
    value: block,
    label: block || 'Khu vực chung',
    color: 'secondary',
    badgeClass: 'bg-slate-100 text-slate-700 border-slate-300',
    icon: 'mdi-map-marker',
  }
}
