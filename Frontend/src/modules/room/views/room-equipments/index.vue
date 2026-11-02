<script setup>
import { ref, reactive, computed, onMounted, watch, defineAsyncComponent } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { roomService } from '../../services/roomService'
import { equipmentService } from '@/modules/equipment/services/equipmentService'
import { useRealtimeStore } from '@/core/stores/realtimeStore'
import { toast } from '@/core/helpers/toast'
import { HotelBlockOrder } from '@/core/enums'
import { resolveMediaUrl } from '@/core/helpers/formatters'
import DefaultLayout from '@/core/layouts/DefaultLayout.vue'
import AppPageHeader from '@/core/components/AppPageHeader.vue'
import AppButton from '@/core/components/AppButton.vue'
import AppConfirmDialog from '@/core/components/AppConfirmDialog.vue'

// Domain Child Components
import RoomAuditStats from '../../components/RoomAuditStats.vue'
import RoomAuditFilterBar from '../../components/RoomAuditFilterBar.vue'
import RoomAuditSidebarList from '../../components/RoomAuditSidebarList.vue'
import RoomAuditDetailCard from '../../components/RoomAuditDetailCard.vue'
import RoomAssignWarehouseModal from '../../components/RoomAssignWarehouseModal.vue'

// Reused Async Action Modals
const AssignRoomModal = defineAsyncComponent(() => import('@/modules/equipment/components/AssignRoomModal.vue'))
const ReportBrokenModal = defineAsyncComponent(() => import('@/modules/equipment/components/ReportBrokenModal.vue'))
const EquipmentFormModal = defineAsyncComponent(() => import('@/modules/equipment/components/EquipmentFormModal.vue'))
const RoomFormModal = defineAsyncComponent(() => import('../../components/RoomFormModal.vue'))

const route = useRoute()
const router = useRouter()
const realtimeStore = useRealtimeStore()

const loading = ref(false)
const actionLoading = ref(false)
const rooms = ref([])
const selectedRoomId = ref(null)
const roomDetail = ref(null)
let roomDetailRequestId = 0

// Standard hotel block order
const hotelBlockOrder = HotelBlockOrder

// Mobile active view tab: 'detail' | 'list'
const mobileActiveTab = ref('detail')

// Filter States
const filters = reactive({
  search: '',
  block: '',
  floor: null,
  status: '', // '' | 'compliant' | 'missing' | 'maintenance'
  room_type_id: null,
})

// Room types for filter & edit
const roomTypesList = ref([])

// Action Modals State
const assignModalOpen = ref(false)
const selectedEquipmentForAssign = ref(null)

const reportBrokenModalOpen = ref(false)
const selectedEquipmentForReport = ref(null)

const retractDialogOpen = ref(false)
const equipmentToRetract = ref(null)

const createModalOpen = ref(false)

const warehouseModalOpen = ref(false)
const warehouseLoading = ref(false)
const warehouseEquipments = ref([])

// Sửa nhanh cơ cấu phòng
const editRoomModalOpen = ref(false)

// Lightbox state
const lightboxOpen = ref(false)
const lightboxImage = ref('')
const lightboxTitle = ref('')
const lightboxSubtitle = ref('')

onMounted(async () => {
  await Promise.allSettled([
    loadRooms(),
    fetchRoomTypes(),
  ])
})

/**
 * [1. MỤC ĐÍCH]
 * Tải danh sách tất cả loại phòng (Room Types) của khách sạn để phục vụ bộ lọc tìm kiếm
 * và cung cấp dữ liệu cấu hình khi người dùng mở modal cập nhật phòng.
 *
 * [2. LUỒNG XỬ LÝ (FLOW)]
 * 1. Gọi `roomService.getRoomTypes()` lấy danh sách loại phòng qua API.
 * 2. Lưu kết quả vào biến phản ứng `roomTypesList`.
 * 3. Bắt lỗi ngoại lệ và ghi log nếu quá trình tải thất bại (không chặn UI).
 *
 * [3. NGUỒN GỌI (CALLERS / TRIGGERED FROM)]
 * - Hook `onMounted()` khi màn hình Đối soát thiết bị phòng vừa khởi tạo.
 * - Hàm `openEditRoomModal()` kiểm tra nạp bổ sung nếu mảng loại phòng chưa có dữ liệu.
 *
 * [4. THAM SỐ (PARAMETERS)]
 * Không có.
 *
 * [5. GIÁ TRỊ TRẢ VỀ & NGOẠI LỆ]
 * @return {Promise<void>}
 *
 * [6. VÍ DỤ SỬ DỤNG]
 * @example
 * await fetchRoomTypes()
 */
async function fetchRoomTypes() {
  try {
    const types = await roomService.getRoomTypes()
    roomTypesList.value = Array.isArray(types) ? types : []
  } catch (err) {
    console.error('Failed to load room types:', err)
  }
}

// Blocks extraction & sorting
const existingBlocks = computed(() => {
  const set = new Set(rooms.value.map(r => r.block).filter(Boolean))
  hotelBlockOrder.forEach(b => set.add(b))
  return Array.from(set).sort((a, b) => {
    const idxA = hotelBlockOrder.indexOf(a)
    const idxB = hotelBlockOrder.indexOf(b)
    if (idxA !== -1 && idxB !== -1) return idxA - idxB
    if (idxA !== -1) return -1
    if (idxB !== -1) return 1
    return a.localeCompare(b, 'vi', { numeric: true })
  })
})

const blockCounts = computed(() => {
  const map = {}
  rooms.value.forEach(r => {
    const b = r.block || 'Block A'
    map[b] = (map[b] || 0) + 1
  })
  return map
})

const floorOptions = computed(() => {
  let list = rooms.value
  if (filters.block) {
    list = list.filter(r => r.block === filters.block)
  }
  const floors = Array.from(new Set(list.map(r => r.floor).filter(f => f !== null && f !== undefined)))
    .sort((a, b) => Number(a) - Number(b))
  return [
    { value: null, title: 'Tất cả Tầng' },
    ...floors.map(f => ({ value: f, title: `Tầng ${f}` })),
  ]
})

const complianceOptions = [
  { value: '', title: 'Tất cả định mức' },
  { value: 'compliant', title: '🟢 Đạt chuẩn 100% (Đủ thiết bị)' },
  { value: 'missing', title: '🔴 Chưa đạt chuẩn (Thiếu thiết bị)' },
  { value: 'maintenance', title: '🟡 Có thiết bị bảo trì' },
]

const roomTypeOptions = computed(() => {
  const types = roomTypesList.value.length > 0
    ? roomTypesList.value
    : Array.from(new Set(rooms.value.map(r => r.room_type).filter(Boolean))).map((t, idx) => ({ id: idx + 1, name: t }))
  return [
    { value: null, title: 'Tất cả loại phòng' },
    ...types.map(t => ({ value: t.id, title: t.name })),
  ]
})

// Overview stats
const auditStats = computed(() => {
  const total = rooms.value.length
  const compliant = rooms.value.filter(r => r.badge_color === 'green').length
  const missing = rooms.value.filter(r => r.badge_color === 'red' || (r.stats?.missing_count > 0)).length
  const maintenance = rooms.value.filter(r => r.badge_color === 'yellow' || (r.stats?.maintenance_count > 0)).length
  const rawRate = total > 0 ? (compliant / total) * 100 : 0
  const rate = rawRate === 0 || rawRate === 100 ? rawRate.toFixed(0) : rawRate.toFixed(1)
  return { total, compliant, missing, maintenance, rate }
})

// Filtered rooms list
const filteredRooms = computed(() => {
  return rooms.value.filter(r => {
    if (filters.search) {
      const kw = filters.search.trim().toLowerCase()
      const matchNum = String(r.room_number || '').toLowerCase().includes(kw)
      const matchName = String(r.name || '').toLowerCase().includes(kw)
      if (!matchNum && !matchName) return false
    }
    if (filters.block && r.block !== filters.block) {
      return false
    }
    if (filters.floor !== null && filters.floor !== undefined && r.floor !== filters.floor) {
      return false
    }
    if (filters.status) {
      if (filters.status === 'compliant' && r.badge_color !== 'green') return false
      if (filters.status === 'missing' && (r.badge_color !== 'red' && !(r.stats?.missing_count > 0))) return false
      if (filters.status === 'maintenance' && (r.badge_color !== 'yellow' && !(r.stats?.maintenance_count > 0))) return false
    }
    if (filters.room_type_id) {
      if (r.room_type_id !== filters.room_type_id) return false
    }
    return true
  })
})

const hasActiveFilters = computed(() => {
  return !!(filters.search || filters.block || filters.floor !== null || filters.status || filters.room_type_id)
})

/**
 * [1. MỤC ĐÍCH]
 * Chuyển đổi bộ lọc theo tòa nhà (Block), đồng thời reset lại tầng về "Tất cả tầng"
 * để tránh trường hợp tầng cũ không tồn tại trong Block mới chọn.
 *
 * [2. LUỒNG XỬ LÝ (FLOW)]
 * 1. Gán `filters.block` bằng giá trị `b` được chọn từ UI tab hoặc danh sách block.
 * 2. Đặt `filters.floor = null` để hiển thị tất cả tầng của tòa nhà này.
 *
 * [3. NGUỒN GỌI (CALLERS / TRIGGERED FROM)]
 * - Sự kiện click chọn Block tại component `RoomAuditFilterBar.vue`.
 *
 * [4. THAM SỐ (PARAMETERS)]
 * @param {string} b Tên tòa nhà (Block A, Block B, v.v.).
 *
 * [5. GIÁ TRỊ TRẢ VỀ & NGOẠI LỆ]
 * @return {void}
 *
 * [6. VÍ DỤ SỬ DỤNG]
 * @example
 * selectBlock('Block A')
 */
function selectBlock(b) {
  filters.block = b
  filters.floor = null
}

/**
 * [1. MỤC ĐÍCH]
 * Đặt lại toàn bộ các tiêu chí tìm kiếm và bộ lọc về trạng thái mặc định ban đầu.
 *
 * [2. LUỒNG XỬ LÝ (FLOW)]
 * 1. Reset chuỗi từ khóa `filters.search = ''`.
 * 2. Reset tòa nhà `filters.block = ''`.
 * 3. Reset tầng `filters.floor = null`.
 * 4. Reset trạng thái định mức `filters.status = ''`.
 * 5. Reset loại phòng `filters.room_type_id = null`.
 *
 * [3. NGUỒN GỌI (CALLERS / TRIGGERED FROM)]
 * - Nút "Đặt lại bộ lọc" tại giao diện hoặc `RoomAuditFilterBar.vue`.
 *
 * [4. THAM SỐ (PARAMETERS)]
 * Không có.
 *
 * [5. GIÁ TRỊ TRẢ VỀ & NGOẠI LỆ]
 * @return {void}
 *
 * [6. VÍ DỤ SỬ DỤNG]
 * @example
 * resetFilters()
 */
function resetFilters() {
  filters.search = ''
  filters.block = ''
  filters.floor = null
  filters.status = ''
  filters.room_type_id = null
}

// Auto select first room if current selected is filtered out
watch(filteredRooms, (newList) => {
  if (newList.length > 0) {
    const exists = newList.some(r => r.id === selectedRoomId.value)
    if (!exists) {
      selectedRoomId.value = newList[0].id
      loadRoomDetail(newList[0].id)
    }
  } else {
    selectedRoomId.value = null
    roomDetail.value = null
  }
})

/**
 * [1. MỤC ĐÍCH]
 * Mở modal chỉnh sửa nhanh thông tin cơ cấu phòng (loại phòng, số phòng khách, phòng ngủ)
 * cho phòng đang được chọn trong giao diện đối soát.
 *
 * [2. LUỒNG XỬ LÝ (FLOW)]
 * 1. Kiểm tra nếu chưa có phòng nào được chọn (`!roomDetail.value`) thì bỏ qua.
 * 2. Nạp trước danh sách loại phòng nếu mảng `roomTypesList` đang trống.
 * 3. Kích hoạt cờ `editRoomModalOpen = true` để hiển thị modal `RoomFormModal`.
 *
 * [3. NGUỒN GỌI (CALLERS / TRIGGERED FROM)]
 * - Nút "Sửa cơ cấu phòng" từ component `RoomAuditDetailCard.vue`.
 *
 * [4. THAM SỐ (PARAMETERS)]
 * Không có.
 *
 * [5. GIÁ TRỊ TRẢ VỀ & NGOẠI LỆ]
 * @return {Promise<void>}
 *
 * [6. VÍ DỤ SỬ DỤNG]
 * @example
 * await openEditRoomModal()
 */
async function openEditRoomModal() {
  if (!roomDetail.value) return
  if (roomTypesList.value.length === 0) {
    await fetchRoomTypes()
  }
  editRoomModalOpen.value = true
}

/**
 * [1. MỤC ĐÍCH]
 * Cập nhật lại dữ liệu phòng trong danh sách và nạp lại thông số định mức đối soát
 * ngay sau khi người dùng lưu thành công thay đổi từ modal cấu hình phòng.
 *
 * [2. LUỒNG XỬ LÝ (FLOW)]
 * 1. Nếu có object `savedRoom` với `id`, cập nhật trực tiếp item tương ứng trong mảng `rooms`.
 * 2. Nếu không có object cụ thể, gọi nạp lại danh sách phòng ngầm `loadRooms({ silent: true })`.
 * 3. Tải lại chi tiết đối soát định mức ngầm của phòng đang chọn `loadRoomDetail(selectedRoomId.value, { silent: true })`.
 *
 * [3. NGUỒN GỌI (CALLERS / TRIGGERED FROM)]
 * - Sự kiện `@saved="handleRoomSaved"` của component `RoomFormModal`.
 *
 * [4. THAM SỐ (PARAMETERS)]
 * @param {Object} [savedRoom] Dữ liệu phòng đã cập nhật từ API.
 *
 * [5. GIÁ TRỊ TRẢ VỀ & NGOẠI LỆ]
 * @return {Promise<void>}
 *
 * [6. VÍ DỤ SỬ DỤNG]
 * @example
 * await handleRoomSaved(updatedRoomData)
 */
async function handleRoomSaved(savedRoom) {
  if (savedRoom?.id) {
    rooms.value = rooms.value.map(room =>
      Number(room.id) === Number(savedRoom.id) ? { ...room, ...savedRoom } : room
    )
  } else {
    await loadRooms({ silent: true })
  }
  if (selectedRoomId.value) {
    await loadRoomDetail(selectedRoomId.value, { silent: true })
  }
}

// Tự động đối soát lại phòng khi có thiết bị thay đổi Real-time
watch(
  () => realtimeStore.lastEvent,
  async (event) => {
    if (!event) return
    if (selectedRoomId.value) {
      await loadRoomDetail(selectedRoomId.value, { silent: true })
    }
    void loadRooms({ silent: true })
  }
)

/**
 * [1. MỤC ĐÍCH]
 * Tải toàn bộ danh sách phòng và trạng thái trang thiết bị theo sơ đồ (Map View).
 * Hỗ trợ tự động định vị và chọn phòng được truyền qua query params URL `?room_id=...`.
 *
 * [2. LUỒNG XỬ LÝ (FLOW)]
 * 1. Gọi `equipmentService.getMapView()` lấy dữ liệu các block, tầng và phòng.
 * 2. Cập nhật mảng phản ứng `rooms`.
 * 3. Kiểm tra query param `room_id`:
 *    - Nếu có và tồn tại trong danh sách: chọn phòng đó làm `selectedRoomId`.
 *    - Nếu không có và danh sách không rỗng: mặc định chọn phòng đầu tiên.
 * 4. Kích hoạt `loadRoomDetail(selectedRoomId)` để đối soát ngay phòng được chọn.
 * 5. Bắt lỗi và hiển thị thông báo lỗi Toast nếu không chạy ở chế độ silent.
 *
 * [3. NGUỒN GỌI (CALLERS / TRIGGERED FROM)]
 * - Hook `onMounted()` khi người dùng mở trang.
 * - Watcher `realtimeStore.lastEvent` khi có thay đổi thiết bị theo thời gian thực (chế độ silent).
 * - Sau các thao tác điều chuyển, thu hồi, gán mới thiết bị để đồng bộ lại danh sách phòng.
 *
 * [4. THAM SỐ (PARAMETERS)]
 * @param {Object} [options]
 * @param {boolean} [options.silent=false] Nếu true, chạy ngầm không hiển thị loader và thông báo lỗi.
 *
 * [5. GIÁ TRỊ TRẢ VỀ & NGOẠI LỆ]
 * @return {Promise<void>}
 *
 * [6. VÍ DỤ SỬ DỤNG]
 * @example
 * await loadRooms()
 * await loadRooms({ silent: true })
 */
async function loadRooms({ silent = false } = {}) {
  try {
    const data = await equipmentService.getMapView()
    rooms.value = data || []
    
    // Nếu có query param room_id từ URL thì ưu tiên chọn phòng đó
    const queryRoomId = Number(route.query.room_id)
    if (!selectedRoomId.value) {
      if (queryRoomId && rooms.value.some(r => r.id === queryRoomId)) {
        selectedRoomId.value = queryRoomId
      } else if (rooms.value.length > 0) {
        selectedRoomId.value = rooms.value[0].id
      }

      if (selectedRoomId.value) {
        await loadRoomDetail(selectedRoomId.value, { silent })
      }
    }
  } catch (e) {
    console.error('Failed to load rooms:', e)
    if (!silent) toast.error(e.response?.data?.message || 'Không thể tải danh sách phòng.')
  }
}

/**
 * [1. MỤC ĐÍCH]
 * Cập nhật số liệu thống kê (badge màu, số lượng thiếu, đang bảo trì, hỏng) tại chỗ
 * cho một phòng trong mảng `rooms` mà không cần reload lại toàn bộ danh sách phòng qua API.
 *
 * [2. LUỒNG XỬ LÝ (FLOW)]
 * 1. Tìm phần tử phòng có `id` khớp trong `rooms.value`.
 * 2. Cập nhật `badge_color`: 'green' (đạt chuẩn), 'red' (thiếu đồ hoặc phụ thuộc wifi), 'yellow' (đang bảo trì).
 * 3. Đồng bộ cảnh báo dùng chung wifi `shared_wifi_issue` và danh sách phòng phụ thuộc `dependent_rooms`.
 * 4. Tính toán lại tổng thiết bị, số thiết bị hỏng, và thiết bị đang bảo trì từ danh sách categories.
 *
 * [3. NGUỒN GỌI (CALLERS / TRIGGERED FROM)]
 * - Hàm `loadRoomDetail()` ngay sau khi nhận được dữ liệu chi tiết đối soát phòng.
 *
 * [4. THAM SỐ (PARAMETERS)]
 * @param {number|string} id ID của phòng cần đồng bộ số liệu.
 * @param {Object} detail Object chi tiết trả về từ API `roomService.getRoomEquipmentDetail()`.
 *
 * [5. GIÁ TRỊ TRẢ VỀ & NGOẠI LỆ]
 * @return {void}
 *
 * [6. VÍ DỤ SỬ DỤNG]
 * @example
 * syncRoomStatsInPlace(101, detailResponse)
 */
function syncRoomStatsInPlace(id, detail) {
  if (!detail) return
  const r = rooms.value.find(item => Number(item.id) === Number(id))
  if (!r) return

  r.badge_color = detail.compliance_status === 'compliant'
    ? 'green'
    : (detail.missing_equipments?.length > 0 || detail.shared_wifi_issue ? 'red' : 'yellow')

  r.shared_wifi_issue = detail.shared_wifi_issue || null
  r.dependent_rooms = detail.dependent_rooms || []

  if (!r.stats) r.stats = {}
  
  const missingCount = detail.missing_equipments?.reduce((acc, m) => acc + (m.missing_count || 1), 0) || 0
  r.stats.missing_count = missingCount
  
  const allItems = detail.categories?.flatMap(c => c.items || []) || []
  r.stats.total_equipments = allItems.length
  r.stats.broken_count = allItems.filter(i => i.status === 'broken').length
  r.stats.maintenance_count = allItems.filter(i => i.status === 'maintenance').length
}

/**
 * [1. MỤC ĐÍCH]
 * Tải thông tin chi tiết các thiết bị hiện có và đối soát bảng định mức tiêu chuẩn
 * cho một phòng cụ thể. Quản lý race-condition bằng biến đếm `roomDetailRequestId`.
 *
 * [2. LUỒNG XỬ LÝ (FLOW)]
 * 1. Kiểm tra tham số `id`. Nếu rỗng thì thoát.
 * 2. Tăng `roomDetailRequestId` để đánh dấu lượt request mới nhất (chống response đến sau đè response mới).
 * 3. Bật cờ `loading = true` nếu không chạy ở chế độ silent.
 * 4. Gọi API `roomService.getRoomEquipmentDetail(id)`.
 * 5. So sánh `requestId === roomDetailRequestId`:
 *    - Đúng: gán `roomDetail.value = detail` và gọi `syncRoomStatsInPlace(id, detail)`.
 * 6. Bắt lỗi ngoại lệ và tắt cờ `loading`.
 *
 * [3. NGUỒN GỌI (CALLERS / TRIGGERED FROM)]
 * - Hàm `loadRooms()` khi khởi tạo phòng đầu tiên.
 * - Sự kiện người dùng nhấp chọn một phòng từ thanh danh sách phòng `RoomAuditSidebarList.vue`.
 * - Sau các hành động điều chuyển, báo hỏng, thu hồi, gán kho, tạo mới.
 * - Watcher sự kiện Real-time Pusher khi có biến động thiết bị trong phòng.
 *
 * [4. THAM SỐ (PARAMETERS)]
 * @param {number|string} id ID của phòng cần tải thông tin đối soát.
 * @param {Object} [options]
 * @param {boolean} [options.silent=false] Có tắt hiển thị loader spinner và thông báo lỗi không.
 *
 * [5. GIÁ TRỊ TRẢ VỀ & NGOẠI LỆ]
 * @return {Promise<void>}
 *
 * [6. VÍ DỤ SỬ DỤNG]
 * @example
 * await loadRoomDetail(105)
 * await loadRoomDetail(105, { silent: true })
 */
async function loadRoomDetail(id, { silent = false } = {}) {
  if (!id) return
  const requestId = ++roomDetailRequestId
  if (!silent) {
    if (Number(roomDetail.value?.id) !== Number(id)) roomDetail.value = null
    loading.value = true
  }
  try {
    const detail = await roomService.getRoomEquipmentDetail(id)
    if (requestId === roomDetailRequestId) {
      roomDetail.value = detail
      syncRoomStatsInPlace(id, detail)
    }
  } catch (err) {
    console.error('Failed to load room detail:', err)
    if (requestId === roomDetailRequestId && !silent) {
      toast.error(err.response?.data?.message || 'Không thể tải chi tiết thiết bị của phòng.')
    }
  } finally {
    if (requestId === roomDetailRequestId && !silent) loading.value = false
  }
}

/**
 * [1. MỤC ĐÍCH]
 * Mở modal xem trước ảnh phóng to (Lightbox) hình ảnh thực tế của thiết bị trong phòng.
 *
 * [2. LUỒNG XỬ LÝ (FLOW)]
 * 1. Trích xuất URL ảnh từ `item.photo_url` hoặc `item.photos[0].url`.
 * 2. Nếu không có ảnh thì dừng lại.
 * 3. Chuẩn hóa đường dẫn bằng `resolveMediaUrl(url)`.
 * 4. Gán thông tin tiêu đề `lightboxTitle` (tên model), phụ đề `lightboxSubtitle` (serial, ID).
 * 5. Mở modal qua cờ `lightboxOpen = true`.
 *
 * [3. NGUỒN GỌI (CALLERS / TRIGGERED FROM)]
 * - Sự kiện nhấp vào ảnh thu nhỏ của thiết bị trong `RoomAuditDetailCard.vue`.
 *
 * [4. THAM SỐ (PARAMETERS)]
 * @param {Object} item Thông tin thiết bị có trường ảnh `photo_url` hoặc `photos`.
 *
 * [5. GIÁ TRỊ TRẢ VỀ & NGOẠI LỆ]
 * @return {void}
 *
 * [6. VÍ DỤ SỬ DỤNG]
 * @example
 * openLightbox(equipmentItem)
 */
function openLightbox(item) {
  const url = item.photo_url || item.photos?.[0]?.url
  if (!url) return
  lightboxImage.value = resolveMediaUrl(url)
  lightboxTitle.value = item.model_name || 'Hình ảnh thiết bị'
  lightboxSubtitle.value = `Mã Serial: ${item.serial_number || 'Chưa gắn mã'} • ID #${item.id}`
  lightboxOpen.value = true
}

// 1. Thao tác Điều chuyển
/**
 * [1. MỤC ĐÍCH]
 * Mở modal điều chuyển thiết bị đang chọn sang phòng khác trong khách sạn.
 *
 * [2. LUỒNG XỬ LÝ (FLOW)]
 * 1. Đóng gói dữ liệu thiết bị kèm `room_id` hiện tại vào `selectedEquipmentForAssign`.
 * 2. Mở modal điều chuyển qua cờ `assignModalOpen = true`.
 *
 * [3. NGUỒN GỌI (CALLERS / TRIGGERED FROM)]
 * - Sự kiện `@assign="openAssignModal"` từ component `RoomAuditDetailCard.vue`.
 *
 * [4. THAM SỐ (PARAMETERS)]
 * @param {Object} item Thông tin thiết bị cần điều chuyển.
 *
 * [5. GIÁ TRỊ TRẢ VỀ & NGOẠI LỆ]
 * @return {void}
 *
 * [6. VÍ DỤ SỬ DỤNG]
 * @example
 * openAssignModal(equipmentItem)
 */
function openAssignModal(item) {
  selectedEquipmentForAssign.value = {
    ...item,
    room_id: selectedRoomId.value,
  }
  assignModalOpen.value = true
}

/**
 * [1. MỤC ĐÍCH]
 * Gửi yêu cầu điều chuyển thiết bị sang vị trí/phòng mới đến backend, sau đó đồng bộ lại UI.
 *
 * [2. LUỒNG XỬ LÝ (FLOW)]
 * 1. Bật trạng thái chờ `actionLoading = true`.
 * 2. Gọi API `equipmentService.assignToRoom(payload)`.
 * 3. Hiển thị thông báo thành công Toast.
 * 4. Đóng modal `assignModalOpen = false`.
 * 5. Tải lại chi tiết đối soát phòng hiện tại `loadRoomDetail` và danh sách phòng `loadRooms`.
 * 6. Bắt lỗi ngoại lệ và tắt cờ `actionLoading`.
 *
 * [3. NGUỒN GỌI (CALLERS / TRIGGERED FROM)]
 * - Sự kiện `@submit="handleAssignSubmit"` của component `AssignRoomModal.vue`.
 *
 * [4. THAM SỐ (PARAMETERS)]
 * @param {Object} payload Dữ liệu điều chuyển ({ equipment_id, room_id, position_note }).
 *
 * [5. GIÁ TRỊ TRẢ VỀ & NGOẠI LỆ]
 * @return {Promise<void>}
 *
 * [6. VÍ DỤ SỬ DỤNG]
 * @example
 * await handleAssignSubmit({ equipment_id: 12, room_id: 102, position_note: 'Phòng ngủ' })
 */
async function handleAssignSubmit(payload) {
  actionLoading.value = true
  try {
    await equipmentService.assignToRoom(payload)
    toast.success('Điều chuyển thiết bị thành công!')
    assignModalOpen.value = false
    await loadRoomDetail(selectedRoomId.value, { silent: true })
    void loadRooms({ silent: true })
  } catch (err) {
    toast.error(err.response?.data?.message || err.message || 'Điều chuyển thất bại.')
  } finally {
    actionLoading.value = false
  }
}

// 2. Thao tác Báo hỏng
/**
 * [1. MỤC ĐÍCH]
 * Mở modal lập phiếu báo hỏng thiết bị và yêu cầu bảo dưỡng kỹ thuật.
 *
 * [2. LUỒNG XỬ LÝ (FLOW)]
 * 1. Gán thiết bị cần báo hỏng vào `selectedEquipmentForReport`.
 * 2. Mở modal qua cờ `reportBrokenModalOpen = true`.
 *
 * [3. NGUỒN GỌI (CALLERS / TRIGGERED FROM)]
 * - Sự kiện `@report="openReportBrokenModal"` từ component `RoomAuditDetailCard.vue`.
 *
 * [4. THAM SỐ (PARAMETERS)]
 * @param {Object} item Dữ liệu thiết bị gặp sự cố.
 *
 * [5. GIÁ TRỊ TRẢ VỀ & NGOẠI LỆ]
 * @return {void}
 *
 * [6. VÍ DỤ SỬ DỤNG]
 * @example
 * openReportBrokenModal(equipmentItem)
 */
function openReportBrokenModal(item) {
  selectedEquipmentForReport.value = item
  reportBrokenModalOpen.value = true
}

/**
 * [1. MỤC ĐÍCH]
 * Gửi báo cáo hỏng hóc thiết bị kèm lý do, mức độ ưu tiên và ảnh sự cố lên hệ thống.
 *
 * [2. LUỒNG XỬ LÝ (FLOW)]
 * 1. Bật cờ `actionLoading = true`.
 * 2. Gọi API `equipmentService.reportBroken(id, formData)`.
 * 3. Hiển thị thông báo thành công Toast.
 * 4. Đóng modal `reportBrokenModalOpen = false`.
 * 5. Tải lại chi tiết đối soát phòng và danh sách phòng (silent).
 * 6. Bắt lỗi ngoại lệ và tắt cờ `actionLoading`.
 *
 * [3. NGUỒN GỌI (CALLERS / TRIGGERED FROM)]
 * - Sự kiện `@submit="handleReportBrokenSubmit"` của component `ReportBrokenModal.vue`.
 *
 * [4. THAM SỐ (PARAMETERS)]
 * @param {Object} param
 * @param {number|string} param.id ID thiết bị hỏng.
 * @param {FormData} param.formData Form data chứa mô tả lỗi và file đính kèm.
 *
 * [5. GIÁ TRỊ TRẢ VỀ & NGOẠI LỆ]
 * @return {Promise<void>}
 *
 * [6. VÍ DỤ SỬ DỤNG]
 * @example
 * await handleReportBrokenSubmit({ id: 5, formData: myFormData })
 */
async function handleReportBrokenSubmit({ id, formData }) {
  actionLoading.value = true
  try {
    await equipmentService.reportBroken(id, formData)
    toast.success('Báo hỏng thiết bị thành công! Đã gửi thông báo bảo trì.')
    reportBrokenModalOpen.value = false
    await loadRoomDetail(selectedRoomId.value, { silent: true })
    void loadRooms({ silent: true })
  } catch (err) {
    toast.error(err.response?.data?.message || err.message || 'Báo hỏng thất bại.')
  } finally {
    actionLoading.value = false
  }
}

// 3. Thao tác Thu hồi về kho
/**
 * [1. MỤC ĐÍCH]
 * Mở hộp thoại xác nhận trước khi thu hồi thiết bị từ phòng về lại kho lưu trữ.
 *
 * [2. LUỒNG XỬ LÝ (FLOW)]
 * 1. Gán thiết bị cần thu hồi vào `equipmentToRetract`.
 * 2. Mở dialog xác nhận qua cờ `retractDialogOpen = true`.
 *
 * [3. NGUỒN GỌI (CALLERS / TRIGGERED FROM)]
 * - Nút "Thu hồi về kho" hoặc sự kiện `@retract` từ `RoomAuditDetailCard.vue`.
 *
 * [4. THAM SỐ (PARAMETERS)]
 * @param {Object} item Thiết bị cần thu hồi.
 *
 * [5. GIÁ TRỊ TRẢ VỀ & NGOẠI LỆ]
 * @return {void}
 *
 * [6. VÍ DỤ SỬ DỤNG]
 * @example
 * confirmRetract(equipmentItem)
 */
function confirmRetract(item) {
  equipmentToRetract.value = item
  retractDialogOpen.value = true
}

/**
 * [1. MỤC ĐÍCH]
 * Thực thi lệnh thu hồi thiết bị từ phòng về kho (`room_id = null`) sau khi người dùng xác nhận.
 *
 * [2. LUỒNG XỬ LÝ (FLOW)]
 * 1. Kiểm tra tồn tại `equipmentToRetract.value`.
 * 2. Bật cờ `actionLoading = true`.
 * 3. Gọi `equipmentService.assignToRoom({ equipment_id, room_id: null, position_note })`.
 * 4. Hiển thị Toast thông báo thành công.
 * 5. Đóng dialog `retractDialogOpen = false` và reset `equipmentToRetract = null`.
 * 6. Tải lại chi tiết đối soát phòng và danh sách phòng (silent).
 * 7. Bắt lỗi ngoại lệ và tắt cờ `actionLoading`.
 *
 * [3. NGUỒN GỌI (CALLERS / TRIGGERED FROM)]
 * - Sự kiện `@confirm="handleRetract"` của `AppConfirmDialog.vue`.
 *
 * [4. THAM SỐ (PARAMETERS)]
 * Không có.
 *
 * [5. GIÁ TRỊ TRẢ VỀ & NGOẠI LỆ]
 * @return {Promise<void>}
 *
 * [6. VÍ DỤ SỬ DỤNG]
 * @example
 * await handleRetract()
 */
async function handleRetract() {
  if (!equipmentToRetract.value) return
  actionLoading.value = true
  try {
    await equipmentService.assignToRoom({
      equipment_id: equipmentToRetract.value.id,
      room_id: null,
      position_note: 'Thu hồi về kho từ phòng ' + (roomDetail.value?.room_number || ''),
    })
    toast.success(`Đã thu hồi thiết bị #${equipmentToRetract.value.id} về kho thành công!`)
    retractDialogOpen.value = false
    equipmentToRetract.value = null
    await loadRoomDetail(selectedRoomId.value, { silent: true })
    void loadRooms({ silent: true })
  } catch (err) {
    toast.error(err.response?.data?.message || err.message || 'Thu hồi thất bại.')
  } finally {
    actionLoading.value = false
  }
}

// 4. Thao tác Gán thiết bị từ kho vào phòng
const targetCategoryCode = ref('')

/**
 * [1. MỤC ĐÍCH]
 * Mở modal chọn thiết bị đang có sẵn trong kho (`status = in_stock`) để bổ sung vào phòng.
 *
 * [2. LUỒNG XỬ LÝ (FLOW)]
 * 1. Gán mã loại thiết bị mục tiêu `targetCategoryCode` nếu được truyền vào từ danh sách thiếu.
 * 2. Mở modal qua cờ `warehouseModalOpen = true` và bật cờ `warehouseLoading = true`.
 * 3. Gọi API `equipmentService.getEquipments({ status: 'in_stock', limit: 100 })`.
 * 4. Gán danh sách kết quả vào `warehouseEquipments`.
 * 5. Bắt lỗi ngoại lệ và tắt cờ `warehouseLoading`.
 *
 * [3. NGUỒN GỌI (CALLERS / TRIGGERED FROM)]
 * - Nút "Gán từ kho" tại Header trang hoặc nút "Bổ sung" cạnh mỗi loại thiết bị thiếu trong `RoomAuditDetailCard.vue`.
 *
 * [4. THAM SỐ (PARAMETERS)]
 * @param {string} [categoryCode=''] Mã nhóm thiết bị gợi ý lọc trước trong kho.
 *
 * [5. GIÁ TRỊ TRẢ VỀ & NGOẠI LỆ]
 * @return {Promise<void>}
 *
 * [6. VÍ DỤ SỬ DỤNG]
 * @example
 * await openWarehouseModal('tivi')
 */
async function openWarehouseModal(categoryCode = '') {
  targetCategoryCode.value = typeof categoryCode === 'string' ? categoryCode : ''
  warehouseModalOpen.value = true
  warehouseLoading.value = true
  try {
    const res = await equipmentService.getEquipments({ status: 'in_stock', limit: 100 })
    warehouseEquipments.value = res.data || res || []
  } catch (err) {
    console.error('Failed to load in-stock equipments:', err)
    toast.error(err.response?.data?.message || 'Không thể tải danh sách thiết bị trong kho.')
  } finally {
    warehouseLoading.value = false
  }
}

const suggestedPositions = computed(() => {
  const list = []
  const living = parseInt(roomDetail.value?.living_room_count, 10) || 0
  const bedroom = parseInt(roomDetail.value?.bedroom_count, 10) || 1

  if (living > 0) {
    list.push('Phòng khách')
  }
  if (bedroom === 1) {
    list.push('Phòng ngủ')
  } else {
    for (let i = 1; i <= bedroom; i++) {
      list.push(`Phòng ngủ ${i}`)
    }
  }
  list.push('Bàn làm việc')
  list.push('Lối vào / Ban công')
  return list
})

/**
 * [1. MỤC ĐÍCH]
 * Thực hiện gán một hoặc nhiều thiết bị đang trong kho vào phòng hiện tại.
 *
 * [2. LUỒNG XỬ LÝ (FLOW)]
 * 1. Kiểm tra tồn tại phòng đang chọn `selectedRoomId.value`.
 * 2. Gom danh sách ID thiết bị (`equipment_ids` hoặc `equipment_id`).
 * 3. Bật cờ `actionLoading = true`.
 * 4. Gọi API `equipmentService.assignToRoom` với danh sách ID và ghi chú vị trí đặt.
 * 5. Hiển thị Toast thông báo thành công (số lượng thiết bị đã gán).
 * 6. Đóng modal kho `warehouseModalOpen = false`.
 * 7. Tải lại chi tiết đối soát phòng và danh sách phòng (silent).
 * 8. Bắt lỗi ngoại lệ và tắt cờ `actionLoading`.
 *
 * [3. NGUỒN GỌI (CALLERS / TRIGGERED FROM)]
 * - Sự kiện `@assign="handleAssignFromWarehouse"` từ component `RoomAssignWarehouseModal.vue`.
 *
 * [4. THAM SỐ (PARAMETERS)]
 * @param {Object} param
 * @param {number} [param.equipment_id] ID thiết bị đơn lẻ.
 * @param {number[]} [param.equipment_ids] Mảng ID các thiết bị gán hàng loạt.
 * @param {string} [param.position_note] Vị trí lắp đặt trong phòng (Phòng ngủ, Phòng khách...).
 *
 * [5. GIÁ TRỊ TRẢ VỀ & NGOẠI LỆ]
 * @return {Promise<void>}
 *
 * [6. VÍ DỤ SỬ DỤNG]
 * @example
 * await handleAssignFromWarehouse({ equipment_ids: [1, 2], position_note: 'Phòng khách' })
 */
async function handleAssignFromWarehouse({ equipment_id, equipment_ids, position_note }) {
  if (!selectedRoomId.value) return
  const ids = equipment_ids && equipment_ids.length ? equipment_ids : (equipment_id ? [equipment_id] : [])
  if (!ids.length) return

  actionLoading.value = true
  try {
    if (ids.length === 1) {
      await equipmentService.assignToRoom({
        equipment_id: ids[0],
        room_id: selectedRoomId.value,
        position_note,
      })
      toast.success('Đã gán thiết bị từ kho vào phòng thành công!')
    } else {
      await equipmentService.assignToRoom({
        equipment_ids: ids,
        room_id: selectedRoomId.value,
        position_note,
      })
      toast.success(`Đã gán thành công ${ids.length} thiết bị từ kho vào phòng!`)
    }
    warehouseModalOpen.value = false
    await loadRoomDetail(selectedRoomId.value, { silent: true })
    void loadRooms({ silent: true })
  } catch (err) {
    toast.error(err.response?.data?.message || err.message || 'Gán thiết bị thất bại.')
  } finally {
    actionLoading.value = false
  }
}

// 5. Thao tác Tạo mới thiết bị gắn trực tiếp vào phòng
/**
 * [1. MỤC ĐÍCH]
 * Tạo mới một thiết bị trong cơ sở dữ liệu và tự động gán trực tiếp vào phòng đang đối soát.
 *
 * [2. LUỒNG XỬ LÝ (FLOW)]
 * 1. Bật cờ `actionLoading = true`.
 * 2. Gộp thuộc tính `room_id: selectedRoomId.value` vào form dữ liệu `data`.
 * 3. Gọi API `equipmentService.createEquipment(payload)`.
 * 4. Hiển thị Toast thông báo thành công.
 * 5. Đóng modal thêm mới `createModalOpen = false`.
 * 6. Tải lại chi tiết đối soát phòng và danh sách phòng (silent).
 * 7. Bắt lỗi ngoại lệ và tắt cờ `actionLoading`.
 *
 * [3. NGUỒN GỌI (CALLERS / TRIGGERED FROM)]
 * - Sự kiện `@submit="handleCreateSubmit"` của component `EquipmentFormModal.vue`.
 *
 * [4. THAM SỐ (PARAMETERS)]
 * @param {Object} param
 * @param {Object} param.data Form dữ liệu thiết bị mới từ form modal.
 *
 * [5. GIÁ TRỊ TRẢ VỀ & NGOẠI LỆ]
 * @return {Promise<void>}
 *
 * [6. VÍ DỤ SỬ DỤNG]
 * @example
 * await handleCreateSubmit({ data: formValues })
 */
async function handleCreateSubmit({ data }) {
  actionLoading.value = true
  try {
    await equipmentService.createEquipment({
      ...data,
      room_id: selectedRoomId.value,
    })
    toast.success('Đã tạo thiết bị mới và gán vào phòng thành công!')
    createModalOpen.value = false
    await loadRoomDetail(selectedRoomId.value, { silent: true })
    void loadRooms({ silent: true })
  } catch (err) {
    toast.error(err.response?.data?.message || err.message || 'Thêm thiết bị thất bại.')
  } finally {
    actionLoading.value = false
  }
}
</script>

<template>
  <DefaultLayout>
    <div class="p-4 md:p-6 space-y-6 max-w-7xl mx-auto">
      <!-- 1. Page Header -->
      <AppPageHeader
        title="Đối soát Định mức Thiết bị Phòng"
        subtitle="Kiểm tra mức độ đáp ứng định mức trang thiết bị tiêu chuẩn cho từng phòng khách sạn"
        icon="mdi-clipboard-check-outline"
        :breadcrumbs="[
          { title: 'Trang chủ', to: '/dashboard' },
          { title: 'Quản lý phòng & tầng', to: '/rooms' },
          { title: 'Đối soát định mức' },
        ]"
      >
        <template #actions>
          <div class="flex items-center gap-2 flex-wrap">
            <router-link to="/equipments/map-view">
              <AppButton variant="secondary">
                <v-icon start icon="mdi-floor-plan" size="18" />
                Sơ đồ Map View
              </AppButton>
            </router-link>
            <AppButton
              v-if="$can('assign', 'Equipment')"
              variant="primary"
              @click="openWarehouseModal"
            >
              <v-icon start icon="mdi-package-down" size="18" />
              Gán thiết bị từ kho
            </AppButton>
            <AppButton
              v-if="$can('create', 'Equipment')"
              variant="secondary"
              @click="createModalOpen = true"
            >
              <v-icon start icon="mdi-plus" size="18" />
              Tạo thiết bị mới
            </AppButton>
          </div>
        </template>
      </AppPageHeader>

      <!-- 2. Overview Stats Badges Cards (Child Component) -->
      <RoomAuditStats :stats="auditStats" :loading="loading" />

      <!-- 3. Modern Filter & Navigation Control Card (Child Component) -->
      <RoomAuditFilterBar
        :filters="filters"
        :total-rooms="rooms.length"
        :filtered-rooms-count="filteredRooms.length"
        :existing-blocks="existingBlocks"
        :block-counts="blockCounts"
        :floor-options="floorOptions"
        :compliance-options="complianceOptions"
        :room-type-options="roomTypeOptions"
        :has-active-filters="hasActiveFilters"
        @select-block="selectBlock"
        @reset-filters="resetFilters"
      />

      <!-- Mobile View Mode Segmented Tab (Chỉ hiển thị trên Mobile / Tablet < lg) -->
      <div class="lg:hidden flex items-center p-1 bg-slate-200/70 rounded-xl text-xs font-bold shadow-2xs">
        <button
          type="button"
          class="flex-1 py-2 px-3 rounded-lg flex items-center justify-center gap-1.5 transition-all cursor-pointer"
          :class="mobileActiveTab === 'detail' ? 'bg-white text-primary shadow-xs font-black' : 'text-slate-600 hover:text-slate-900'"
          @click="mobileActiveTab = 'detail'"
        >
          <v-icon icon="mdi-clipboard-text-outline" size="16" />
          <span>Chi tiết đối soát {{ roomDetail?.room_number ? `(P.${roomDetail.room_number})` : '' }}</span>
        </button>

        <button
          type="button"
          class="flex-1 py-2 px-3 rounded-lg flex items-center justify-center gap-1.5 transition-all cursor-pointer"
          :class="mobileActiveTab === 'list' ? 'bg-white text-primary shadow-xs font-black' : 'text-slate-600 hover:text-slate-900'"
          @click="mobileActiveTab = 'list'"
        >
          <v-icon icon="mdi-door" size="16" />
          <span>Danh sách phòng ({{ filteredRooms.length }})</span>
        </button>
      </div>

      <!-- 4. Main Content: Left Room Sidebar + Right Audit Detail -->
      <div class="grid grid-cols-1 lg:grid-cols-4 gap-6 items-start">
        <!-- Left: Room Selector Sidebar (Child Component) -->
        <div
          class="lg:col-span-1"
          :class="mobileActiveTab === 'list' ? 'block' : 'hidden lg:block'"
        >
          <RoomAuditSidebarList
            :rooms="filteredRooms"
            :total-rooms="rooms.length"
            :selected-room-id="selectedRoomId"
            @select-room="(id) => { selectedRoomId = id; loadRoomDetail(id); mobileActiveTab = 'detail' }"
            @reset-filters="resetFilters"
          />
        </div>

        <!-- Right: Detailed Audit Card (Child Component) -->
        <div
          class="lg:col-span-3"
          :class="mobileActiveTab === 'detail' ? 'block' : 'hidden lg:block'"
        >
          <RoomAuditDetailCard
            :room-detail="roomDetail"
            :loading="loading"
            @open-edit-room="openEditRoomModal"
            @open-warehouse-modal="openWarehouseModal"
            @open-assign-modal="openAssignModal"
            @open-report-broken-modal="openReportBrokenModal"
            @confirm-retract="confirmRetract"
            @open-lightbox="openLightbox"
          />
        </div>
      </div>
    </div>

    <!-- REUSED MODAL 1: Điều chuyển phòng -->
    <AssignRoomModal
      v-if="assignModalOpen"
      v-model="assignModalOpen"
      :equipment="selectedEquipmentForAssign"
      :loading="actionLoading"
      @submit="handleAssignSubmit"
    />

    <!-- REUSED MODAL 2: Báo hỏng -->
    <ReportBrokenModal
      v-if="reportBrokenModalOpen"
      v-model="reportBrokenModalOpen"
      :equipment="selectedEquipmentForReport"
      :loading="actionLoading"
      @submit="handleReportBrokenSubmit"
    />

    <!-- REUSED MODAL 3: Tạo thiết bị mới cho phòng -->
    <EquipmentFormModal
      v-if="createModalOpen"
      v-model="createModalOpen"
      :loading="actionLoading"
      @submit="handleCreateSubmit"
    />

    <!-- REUSED MODAL 4: Xác nhận thu hồi về kho -->
    <AppConfirmDialog
      v-model="retractDialogOpen"
      title="Thu hồi thiết bị về kho"
      :message="`Bạn có chắc chắn muốn thu hồi thiết bị #${equipmentToRetract?.id} (${equipmentToRetract?.serial_number || equipmentToRetract?.model_name}) về kho không? Trạng thái sẽ chuyển thành 'Trong kho' (in_stock).`"
      confirm-text="Xác nhận thu hồi"
      confirm-color="warning"
      :loading="actionLoading"
      @confirm="handleRetract"
    />

    <!-- MODAL 5: Gán thiết bị từ kho vào phòng hiện tại (Child Component) -->
    <RoomAssignWarehouseModal
      v-if="warehouseModalOpen"
      v-model="warehouseModalOpen"
      :room-number="roomDetail?.room_number || selectedRoomId || ''"
      :room-id="selectedRoomId"
      :warehouse-equipments="warehouseEquipments"
      :warehouse-loading="warehouseLoading"
      :action-loading="actionLoading"
      :suggested-positions="suggestedPositions"
      :target-category-code="targetCategoryCode"
      :missing-equipments="roomDetail?.missing_equipments || []"
      :standard-model-ids="(roomDetail?.standard_allocations || []).map(s => s.equipment_model_id).filter(Boolean)"
      @submit="handleAssignFromWarehouse"
    />

    <!-- Image Preview Lightbox Dialog -->
    <v-dialog v-model="lightboxOpen" max-width="700">
      <div class="bg-slate-900 rounded-2xl overflow-hidden p-4 relative">
        <button
          type="button"
          class="absolute top-3 right-3 z-10 w-8 h-8 rounded-full bg-white/20 hover:bg-white/40 text-white flex items-center justify-center transition-colors cursor-pointer"
          @click="lightboxOpen = false"
        >
          <v-icon icon="mdi-close" size="18" />
        </button>
        <div class="text-center mb-3 pr-8">
          <p class="text-sm font-bold text-white">{{ lightboxTitle }}</p>
          <p class="text-xs text-slate-400 font-mono mt-0.5">{{ lightboxSubtitle }}</p>
        </div>
        <div class="flex items-center justify-center max-h-[70vh]">
          <img :src="lightboxImage" alt="Preview" class="max-w-full max-h-[65vh] object-contain rounded-lg" />
        </div>
      </div>
    </v-dialog>

    <!-- Modal Chỉnh sửa thông tin & cơ cấu phòng -->
    <RoomFormModal
      v-if="editRoomModalOpen"
      v-model="editRoomModalOpen"
      :room="roomDetail ? { ...roomDetail, ...(roomDetail.room || {}) } : null"
      :room-types="roomTypesList"
      :existing-blocks="existingBlocks"
      @saved="handleRoomSaved"
    />
  </DefaultLayout>
</template>
