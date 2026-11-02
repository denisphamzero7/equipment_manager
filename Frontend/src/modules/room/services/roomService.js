import axiosClient from '@/core/api/axiosClient'
import { Endpoints } from '@/core/api/endpoints'
import { getCachedRequest, invalidateRequestCache } from '@/core/services/requestCache'

/**
 * [Mục đích]: Chuẩn hóa các tham số lọc thành chuỗi JSON duy nhất để làm cache key.
 * @param {Object} params Tham số lọc
 * @returns {string}
 */
function stableParams(params) {
  return JSON.stringify(Object.fromEntries(
    Object.entries(params).sort(([left], [right]) => left.localeCompare(right))
  ))
}

/**
 * [Mục đích]: Xóa sạch toàn bộ bộ nhớ đệm (Cache) trên Client liên quan đến phòng, Map View và thiết bị.
 */
function invalidateRoomData() {
  invalidateRequestCache(
    'room-list',
    'room-detail',
    'equipment-room-options',
    'equipment-map',
    'equipment-room-detail',
    'dashboard-stats'
  )
}

/**
 * [Mục đích]: Bọc thực thi các thao tác ghi dữ liệu phòng (tạo, sửa, xóa) để luôn dọn dẹp cache trước và sau khi gọi API.
 * @param {Function} request
 */
async function runRoomMutation(request) {
  invalidateRoomData()
  try {
    return await request()
  } finally {
    invalidateRoomData()
  }
}

/**
 * [1. MỤC ĐÍCH]
 * Tầng dịch vụ API phía Frontend xử lý các thao tác về Phòng khách sạn:
 * Truy vấn danh sách phòng, chi tiết phòng, hạng phòng, tạo hàng loạt, đổi tên/xóa khu vực và xuất nhập Excel.
 */
export const roomService = {
  /**
   * [1. MỤC ĐÍCH]
   * Lấy danh sách phòng có phân trang và bộ lọc linh hoạt (block, floor, status, room_type_id), có cache 10 giây.
   *
   * [2. LUỒNG XỬ LÝ (FLOW)]
   * 1. Sinh cache key từ `stableParams(params)`.
   * 2. Kiểm tra bộ nhớ đệm client với TTL 10.000ms.
   * 3. Gọi GET `Endpoints.ROOM.INDEX`.
   * 4. Trả về mảng dữ liệu phòng kèm phân trang.
   *
   * [3. NGUỒN GỌI (CALLERS)]
   * - View: `Frontend/src/modules/room/views/room-list/index.vue`
   * - View: `Frontend/src/modules/room/views/room-equipments/index.vue` (danh sách phòng để đối soát thiết bị)
   *
   * [4. THAM SỐ (PARAMETERS)]
   * @param {Object} [params={}] Chứa: page, limit, block, floor, status, search, room_type_id
   * @returns {Promise<Object>} Danh sách phòng và metadata
   */
  async getRooms(params = {}) {
    return getCachedRequest('room-list', stableParams(params), async () => {
      const response = await axiosClient.get(Endpoints.ROOM.INDEX, { params })
      return response.data
    }, { ttl: 10000 })
  },

  /**
   * [1. MỤC ĐÍCH]
   * Lấy danh sách danh mục hạng phòng (Standard, Superior, Deluxe, VIP...), có cache 5 phút.
   *
   * [3. NGUỒN GỌI (CALLERS)]
   * - Dropdown chọn hạng phòng trong `RoomFormModal.vue`, `RoomBatchModal.vue`
   * - Bộ lọc hạng phòng trong `room-list` và `room-equipments`
   *
   * [4. THAM SỐ (PARAMETERS)]
   * @param {boolean} [forceRefresh=false] Bỏ qua cache
   * @returns {Promise<Array<Object>>}
   */
  async getRoomTypes(forceRefresh = false) {
    return getCachedRequest('room-types', 'all', async () => {
      const response = await axiosClient.get(Endpoints.ROOM.ROOM_TYPES)
      return response.data?.data || response.data
    }, { ttl: 300000, force: forceRefresh })
  },

  /**
   * [1. MỤC ĐÍCH]
   * Lấy thông tin chi tiết của một phòng theo ID.
   *
   * [4. THAM SỐ (PARAMETERS)]
   * @param {number|string} id ID phòng
   * @returns {Promise<Object>}
   */
  async getRoom(id) {
    return getCachedRequest('room-detail', String(id), async () => {
      const response = await axiosClient.get(Endpoints.ROOM.SHOW(id))
      return response.data?.data || response.data
    })
  },

  /**
   * [1. MỤC ĐÍCH]
   * Tạo một phòng đơn lẻ mới trong hệ thống.
   *
   * [3. NGUỒN GỌI (CALLERS)]
   * - Modal: `RoomFormModal.vue`
   *
   * [4. THAM SỐ (PARAMETERS)]
   * @param {Object} data Thông tin phòng (room_number, block, floor, room_type_id, wifi_mode...)
   */
  async createRoom(data) {
    return runRoomMutation(async () => {
      const response = await axiosClient.post(Endpoints.ROOM.STORE, data)
      return response.data?.data || response.data
    })
  },

  /**
   * [1. MỤC ĐÍCH]
   * Cập nhật thông tin chi tiết hoặc cơ cấu của một phòng.
   *
   * [3. NGUỒN GỌI (CALLERS)]
   * - Modal: `RoomFormModal.vue`
   *
   * [4. THAM SỐ (PARAMETERS)]
   * @param {number|string} id ID phòng
   * @param {Object} data Dữ liệu cập nhật
   */
  async updateRoom(id, data) {
    return runRoomMutation(async () => {
      const response = await axiosClient.put(Endpoints.ROOM.UPDATE(id), data)
      return response.data?.data || response.data
    })
  },

  /**
   * [1. MỤC ĐÍCH]
   * Xóa một phòng khỏi hệ thống, tự động thu hồi toàn bộ thiết bị trong phòng về kho để bảo toàn tài sản.
   *
   * [3. NGUỒN GỌI (CALLERS)]
   * - Thao tác xóa phòng trên bảng danh sách phòng
   *
   * [4. THAM SỐ (PARAMETERS)]
   * @param {number|string} id ID phòng cần xóa
   * @param {boolean} [retractEquipments=true] Tự động thu hồi thiết bị về kho
   */
  async deleteRoom(id, retractEquipments = true) {
    return runRoomMutation(async () => {
      const response = await axiosClient.delete(Endpoints.ROOM.DESTROY(id), {
        params: { retract_equipments: retractEquipments },
      })
      return response.data
    })
  },

  /**
   * [1. MỤC ĐÍCH]
   * Xóa đồng thời nhiều phòng đã chọn.
   *
   * [4. THAM SỐ (PARAMETERS)]
   * @param {Array<number>} ids Mảng ID các phòng
   * @param {boolean} [retractEquipments=true]
   */
  async bulkDeleteRooms(ids, retractEquipments = true) {
    return runRoomMutation(async () => {
      const response = await axiosClient.post(Endpoints.ROOM.BULK_DESTROY, {
        ids,
        retract_equipments: retractEquipments,
      })
      return response.data
    })
  },

  /**
   * [1. MỤC ĐÍCH]
   * Cập nhật trạng thái kinh doanh hàng loạt cho các phòng được chọn.
   *
   * [4. THAM SỐ (PARAMETERS)]
   * @param {Array<number>} ids
   * @param {string} status ('ready', 'occupied', 'not_ready', 'maintenance')
   */
  async bulkUpdateStatusRooms(ids, status) {
    return runRoomMutation(async () => {
      const response = await axiosClient.patch(Endpoints.ROOM.BULK_STATUS, {
        ids,
        status,
      })
      return response.data
    })
  },

  /**
   * [1. MỤC ĐÍCH]
   * Tải về file Excel danh sách phòng hoặc file mẫu nhập liệu.
   *
   * [4. THAM SỐ (PARAMETERS)]
   * @param {Object} [params={}]
   * @returns {Promise<Blob>}
   */
  async exportExcel(params = {}) {
    const response = await axiosClient.get(Endpoints.ROOM.EXPORT, {
      params,
      responseType: 'blob',
    })
    return response.data
  },

  /**
   * [1. MỤC ĐÍCH]
   * Nhập hàng loạt phòng từ bảng tính Excel.
   *
   * [4. THAM SỐ (PARAMETERS)]
   * @param {File} file File Excel
   */
  async importExcel(file) {
    return runRoomMutation(async () => {
      const payload = new FormData()
      payload.append('file', file)
      const response = await axiosClient.post(Endpoints.ROOM.IMPORT, payload, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })
      return response.data
    })
  },

  /**
   * [1. MỤC ĐÍCH]
   * Sinh phòng nhanh hàng loạt theo dải số phòng (Batch Generator) cho một tầng và block.
   *
   * [3. NGUỒN GỌI (CALLERS)]
   * - Modal: `RoomBatchModal.vue`
   *
   * [4. THAM SỐ (PARAMETERS)]
   * @param {Object} data { block, floor, start_number, end_number, room_type_id, wifi_mode... }
   */
  async batchGenerate(data) {
    return runRoomMutation(async () => {
      const response = await axiosClient.post(Endpoints.ROOM.BATCH_GENERATE, data)
      return response.data
    })
  },

  /**
   * [1. MỤC ĐÍCH]
   * Đổi tên một Block / Khu vực và tự động cập nhật toàn bộ các phòng thuộc Block đó.
   *
   * [3. NGUỒN GỌI (CALLERS)]
   * - Modal: `ManageStructureModal.vue`
   */
  async renameBlock(oldBlock, newBlock) {
    return runRoomMutation(async () => {
      const response = await axiosClient.post(Endpoints.ROOM.RENAME_BLOCK, {
        old_block: oldBlock,
        new_block: newBlock,
      })
      return response.data
    })
  },

  /**
   * [1. MỤC ĐÍCH]
   * Xóa toàn bộ phòng trong một Block và tự động thu hồi tài sản về kho.
   *
   * [3. NGUỒN GỌI (CALLERS)]
   * - Modal: `ManageStructureModal.vue`
   */
  async deleteBlock(block) {
    return runRoomMutation(async () => {
      const response = await axiosClient.delete(Endpoints.ROOM.DELETE_BLOCK, {
        data: { block },
      })
      return response.data
    })
  },

  /**
   * [1. MỤC ĐÍCH]
   * Lấy chi tiết trang thiết bị, định mức và sơ đồ chia sẻ wifi thuộc một phòng cụ thể.
   *
   * [3. NGUỒN GỌI (CALLERS)]
   * - View: `Frontend/src/modules/room/views/room-equipments/index.vue`
   * - Component: `RoomAuditDetailCard.vue`
   *
   * [4. THAM SỐ (PARAMETERS)]
   * @param {number|string} roomId ID phòng
   * @returns {Promise<Object>}
   */
  async getRoomEquipmentDetail(roomId) {
    return getCachedRequest('equipment-room-detail', String(roomId), async () => {
      const response = await axiosClient.get(Endpoints.EQUIPMENT.ROOM_DETAIL(roomId))
      return response.data?.data || response.data
    }, { ttl: 30000 })
  },
}
