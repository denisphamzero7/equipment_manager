import axiosClient from '@/core/api/axiosClient'
import { Endpoints } from '@/core/api/endpoints'
import { getCachedRequest, invalidateRequestCache } from '@/core/services/requestCache'

/**
 * [Mục đích]: Chuẩn hóa tham số filter thành chuỗi JSON sắp xếp theo key để làm cache key duy nhất.
 * [Luồng]: Lấy object entries -> sắp xếp theo thứ tự alphabet của key -> chuyển thành JSON string.
 * @param {Object} params Tham số truy vấn
 * @returns {string} Chuỗi JSON đồng nhất
 */
function stableParams(params) {
  return JSON.stringify(Object.fromEntries(
    Object.entries(params).sort(([left], [right]) => left.localeCompare(right))
  ))
}

/**
 * [Mục đích]: Xóa toàn bộ bộ nhớ tạm (Client-side Cache) liên quan đến thiết bị khi có thao tác ghi dữ liệu.
 * [Luồng]: Gọi `invalidateRequestCache` xóa các key cache danh sách, chi tiết, sơ đồ map, thống kê.
 */
function invalidateEquipmentData() {
  invalidateRequestCache(
    'equipment-list',
    'equipment-detail',
    'equipment-map',
    'equipment-room-detail',
    'dashboard-stats',
    'equipment-stats'
  )
}

/**
 * [Mục đích]: Bọc thực thi API thay đổi cấu hình danh mục / model và tự động dọn cache cấu hình.
 * @param {Function} request Hàm async gọi axios
 */
async function runEquipmentConfigurationMutation(request) {
  invalidateRequestCache('equipment-models', 'equipment-categories')
  try {
    return await runEquipmentMutation(request)
  } finally {
    invalidateRequestCache('equipment-models', 'equipment-categories')
  }
}

/**
 * [Mục đích]: Bọc thực thi các thao tác ghi dữ liệu thiết bị (thêm, sửa, xóa, đổi trạng thái) để luôn xóa cache trước và sau khi gọi API.
 * @param {Function} request Hàm async gọi axios
 */
async function runEquipmentMutation(request) {
  invalidateEquipmentData()
  try {
    return await request()
  } finally {
    invalidateEquipmentData()
  }
}

export const equipmentService = {
  /**
   * [1. MỤC ĐÍCH]
   * Lấy danh sách thiết bị có phân trang và bộ lọc, có cơ chế bộ nhớ đệm 10 giây (Request Cache).
   *
   * [2. LUỒNG XỬ LÝ (FLOW)]
   * 1. Sinh cache key từ `stableParams(params)`.
   * 2. Kiểm tra bộ nhớ đệm trong RAM với TTL 10.000ms (10 giây).
   * 3. Nếu cache miss: Gửi GET request tới `Endpoints.EQUIPMENT.INDEX`.
   * 4. Trả về payload response.data (danh sách thiết bị kèm meta phân trang).
   *
   * [3. NGUỒN GỌI (CALLERS)]
   * - Store: `Frontend/src/modules/equipment/stores/equipmentStore.js` -> `fetchEquipments()`
   * - View: `Frontend/src/modules/equipment/views/equipment-list/index.vue`
   *
   * [4. THAM SỐ (PARAMETERS)]
   * @param {Object} [params={}] Bộ lọc: page, per_page, status, block, floor, room_id, search, sort_by...
   *
   * [5. GIÁ TRỊ TRẢ VỀ & LỖI]
   * @returns {Promise<Object>} Object chứa mảng `data` và metadata `meta`
   * @throws {AxiosError} Khi mất kết nối hoặc server 500
   *
   * [6. VÍ DỤ SỬ DỤNG]
   * @example
   * ```javascript
   * const res = await equipmentService.getEquipments({ page: 1, status: 'broken' });
   * console.log(res.data, res.meta.total);
   * ```
   */
  async getEquipments(params = {}) {
    return getCachedRequest('equipment-list', stableParams(params), async () => {
      const response = await axiosClient.get(Endpoints.EQUIPMENT.INDEX, { params })
      return response.data
    }, { ttl: 10000 })
  },

  /**
   * [1. MỤC ĐÍCH]
   * Lấy dữ liệu báo cáo thống kê thiết bị (tổng số, tình trạng tốt/hỏng/bảo trì/kho, phân rã theo block và danh mục).
   *
   * [2. LUỒNG XỬ LÝ (FLOW)]
   * 1. Kiểm tra cache `equipment-stats` với TTL 15 giây.
   * 2. Gọi GET `Endpoints.EQUIPMENT.STATS`.
   * 3. Trích xuất `response.data?.data || response.data`.
   *
   * [3. NGUỒN GỌI (CALLERS)]
   * - Component: `EquipmentStatHeader.vue`, `MapStatHeader.vue`
   * - View: Dashboard trang chủ, Map View
   *
   * [4. THAM SỐ (PARAMETERS)]
   * @param {Object} [params={}] Chứa `block` (lọc theo tòa nhà hoặc '__stock__')
   *
   * [5. GIÁ TRỊ TRẢ VỀ]
   * @returns {Promise<Object>} Dữ liệu thống kê: total_equipments, summary, by_category, by_block...
   */
  async getStats(params = {}) {
    return getCachedRequest('equipment-stats', stableParams(params), async () => {
      const response = await axiosClient.get(Endpoints.EQUIPMENT.STATS, { params })
      return response.data?.data || response.data
    }, { ttl: 15000 })
  },

  /**
   * [1. MỤC ĐÍCH]
   * Lấy thông tin chi tiết một cá thể thiết bị theo ID.
   *
   * [2. LUỒNG XỬ LÝ (FLOW)]
   * 1. Kiểm tra cache theo String(id).
   * 2. Gọi GET `Endpoints.EQUIPMENT.SHOW(id)`.
   * 3. Trả về đối tượng thiết bị.
   *
   * [3. NGUỒN GỌI (CALLERS)]
   * - Store: `useEquipmentStore::fetchEquipment()`
   * - View: `Frontend/src/modules/equipment/views/equipment-detail/index.vue`
   *
   * [4. THAM SỐ (PARAMETERS)]
   * @param {number|string} id ID thiết bị
   *
   * [5. GIÁ TRỊ TRẢ VỀ]
   * @returns {Promise<Object>} Chi tiết thiết bị kèm quan hệ model, room, logs, media
   */
  async getEquipment(id) {
    return getCachedRequest('equipment-detail', String(id), async () => {
      const response = await axiosClient.get(Endpoints.EQUIPMENT.SHOW(id))
      return response.data?.data || response.data
    })
  },

  /**
   * [1. MỤC ĐÍCH]
   * Thêm mới một thiết bị vào hệ thống.
   *
   * [2. LUỒNG XỬ LÝ (FLOW)]
   * 1. Bọc qua `runEquipmentMutation` để tự động dọn sạch cache cũ.
   * 2. Kiểm tra nếu `data` là FormData -> cấu hình header `multipart/form-data`.
   * 3. Gửi POST `Endpoints.EQUIPMENT.STORE`.
   * 4. Trả về kết quả thiết bị vừa tạo.
   *
   * [3. NGUỒN GỌI (CALLERS)]
   * - Store: `useEquipmentStore::createEquipment()`
   * - Modal: `EquipmentFormModal.vue`
   *
   * [4. THAM SỐ (PARAMETERS)]
   * @param {FormData|Object} data Dữ liệu thiết bị (equipment_model_id, serial_number, room_id, photo...)
   *
   * [5. GIÁ TRỊ TRẢ VỀ]
   * @returns {Promise<Object>} Bản ghi thiết bị mới từ server
   */
  async createEquipment(data) {
    return runEquipmentMutation(async () => {
      const isFormData = data instanceof FormData
      const response = await axiosClient.post(Endpoints.EQUIPMENT.STORE, data, {
        headers: isFormData ? { 'Content-Type': 'multipart/form-data' } : {},
      })
      return response.data
    })
  },

  /**
   * [1. MỤC ĐÍCH]
   * Cập nhật thông tin chi tiết thiết bị.
   *
   * [2. LUỒNG XỬ LÝ (FLOW)]
   * 1. Nếu `data` là FormData (có ảnh mới), tự động append `_method = 'PUT'` và gửi POST để PHP parse được file.
   * 2. Nếu là JSON thuần, gửi PUT trực tiếp.
   * 3. Xóa cache và trả về dữ liệu mới.
   *
   * [3. NGUỒN GỌI (CALLERS)]
   * - Store: `useEquipmentStore::updateEquipment()`
   * - Modal: `EquipmentFormModal.vue`
   *
   * [4. THAM SỐ (PARAMETERS)]
   * @param {number|string} id ID thiết bị
   * @param {FormData|Object} data Dữ liệu cập nhật
   */
  async updateEquipment(id, data) {
    return runEquipmentMutation(async () => {
      const isFormData = data instanceof FormData
      if (isFormData) {
        data.append('_method', 'PUT')
        const response = await axiosClient.post(Endpoints.EQUIPMENT.UPDATE(id), data, {
          headers: { 'Content-Type': 'multipart/form-data' },
        })
        return response.data
      }
      const response = await axiosClient.put(Endpoints.EQUIPMENT.UPDATE(id), data)
      return response.data
    })
  },

  /**
   * [1. MỤC ĐÍCH]
   * Xóa một thiết bị khỏi hệ thống.
   *
   * [2. LUỒNG XỬ LÝ (FLOW)]
   * 1. Gửi DELETE `Endpoints.EQUIPMENT.DESTROY(id)`.
   * 2. Xóa sạch cache thiết bị.
   *
   * [3. NGUỒN GỌI (CALLERS)]
   * - Store: `useEquipmentStore::deleteEquipment()`
   *
   * [4. THAM SỐ (PARAMETERS)]
   * @param {number|string} id ID thiết bị cần xóa
   */
  async deleteEquipment(id) {
    return runEquipmentMutation(async () => {
      const response = await axiosClient.delete(Endpoints.EQUIPMENT.DESTROY(id))
      return response.data
    })
  },

  /**
   * [1. MỤC ĐÍCH]
   * Đổi trạng thái hoạt động của thiết bị (tốt, hỏng, bảo trì, về kho...).
   *
   * [2. LUỒNG XỬ LÝ (FLOW)]
   * 1. Gửi PATCH `Endpoints.EQUIPMENT.CHANGE_STATUS(id)` kèm `status` và `note`.
   * 2. Tự động xóa cache và trả về dữ liệu.
   *
   * [3. NGUỒN GỌI (CALLERS)]
   * - Store: `useEquipmentStore::changeStatus()`
   *
   * [4. THAM SỐ (PARAMETERS)]
   * @param {number|string} id ID thiết bị
   * @param {string} status Trạng thái mới
   * @param {string} [note=''] Lý do đổi trạng thái
   */
  async changeStatus(id, status, note = '') {
    return runEquipmentMutation(async () => {
      const response = await axiosClient.patch(Endpoints.EQUIPMENT.CHANGE_STATUS(id), { status, note })
      return response.data
    })
  },

  /**
   * [1. MỤC ĐÍCH]
   * Cập nhật trạng thái cho hàng loạt thiết bị cùng một thao tác.
   *
   * [2. LUỒNG XỬ LÝ (FLOW)]
   * 1. Gửi PATCH `Endpoints.EQUIPMENT.BULK_STATUS` với mảng `ids`, `status`, `note`.
   * 2. Xóa cache và trả về kết quả số lượng cập nhật.
   *
   * [3. NGUỒN GỌI (CALLERS)]
   * - Store: `useEquipmentStore::bulkUpdateStatus()`
   *
   * [4. THAM SỐ (PARAMETERS)]
   * @param {Array<number>} ids Mảng ID các thiết bị
   * @param {string} status Trạng thái mới
   * @param {string} [note=''] Ghi chú
   */
  async bulkUpdateStatus(ids, status, note = '') {
    return runEquipmentMutation(async () => {
      const response = await axiosClient.patch(Endpoints.EQUIPMENT.BULK_STATUS, { ids, status, note })
      return response.data
    })
  },

  /**
   * [1. MỤC ĐÍCH]
   * Xóa hàng loạt thiết bị theo danh sách ID đã chọn.
   *
   * [2. LUỒNG XỬ LÝ (FLOW)]
   * 1. Gửi POST `Endpoints.EQUIPMENT.BULK_DESTROY` kèm mảng `{ ids }`.
   * 2. Xóa cache và trả về phản hồi từ server.
   *
   * [3. NGUỒN GỌI (CALLERS)]
   * - Store: `useEquipmentStore::bulkDestroy()`
   *
   * [4. THAM SỐ (PARAMETERS)]
   * @param {Array<number>} ids Mảng ID thiết bị cần xóa
   */
  async bulkDestroy(ids) {
    return runEquipmentMutation(async () => {
      const response = await axiosClient.post(Endpoints.EQUIPMENT.BULK_DESTROY, { ids })
      return response.data
    })
  },

  /**
   * [1. MỤC ĐÍCH]
   * Điều chuyển thiết bị: Gán thiết bị vào phòng hoặc thu hồi về kho dự phòng (hỗ trợ cả gán đơn lẻ và gán hàng loạt).
   *
   * [2. LUỒNG XỬ LÝ (FLOW)]
   * 1. Gửi POST `Endpoints.EQUIPMENT.ASSIGN_ROOM` với payload chứa `room_id` và `equipment_id` hoặc mảng `equipment_ids`.
   * 2. Xóa cache hệ thống để cập nhật danh sách thiết bị và sơ đồ phòng.
   *
   * [3. NGUỒN GỌI (CALLERS)]
   * - Store: `useEquipmentStore::assignRoom()`
   * - Modal: `AssignRoomModal.vue`, `RoomAssignWarehouseModal.vue`
   *
   * [4. THAM SỐ (PARAMETERS)]
   * @param {Object} payload Chứa { room_id, equipment_id, equipment_ids, position_note }
   */
  async assignToRoom(payload) {
    return runEquipmentMutation(async () => {
      const response = await axiosClient.post(Endpoints.EQUIPMENT.ASSIGN_ROOM, payload)
      return response.data
    })
  },

  /**
   * [1. MỤC ĐÍCH]
   * Kiểm tra đối soát định mức chuẩn của phòng đích trước khi thực hiện điều chuyển.
   *
   * [2. LUỒNG XỬ LÝ (FLOW)]
   * 1. Gửi GET `Endpoints.EQUIPMENT.CHECK_ASSIGN_TARGET(equipmentId)` kèm query `target_room_id`.
   * 2. Trả về phân tích: định mức yêu cầu, số lượng sau khi chuyển, cảnh báo thừa/thiếu.
   *
   * [3. NGUỒN GỌI (CALLERS)]
   * - Modal: `AssignRoomModal.vue` khi người dùng chọn phòng trên giao diện
   *
   * [4. THAM SỐ (PARAMETERS)]
   * @param {number|string} equipmentId ID thiết bị cần chuyển
   * @param {number|string|null} [targetRoomId=null] ID phòng dự kiến chuyển tới (null nếu về kho)
   * @returns {Promise<Object>} Phân tích định mức và cảnh báo tác động
   */
  async checkAssignTarget(equipmentId, targetRoomId = null) {
    const params = targetRoomId ? { target_room_id: targetRoomId } : {}
    const response = await axiosClient.get(Endpoints.EQUIPMENT.CHECK_ASSIGN_TARGET(equipmentId), { params })
    return response.data?.data || response.data
  },

  /**
   * [1. MỤC ĐÍCH]
   * Báo hỏng thiết bị, đính kèm mô tả triệu chứng và ảnh chụp hiện trường.
   *
   * [2. LUỒNG XỬ LÝ (FLOW)]
   * 1. Gửi POST `Endpoints.EQUIPMENT.REPORT_BROKEN(id)` với FormData chứa `issue_description` và `broken_photo`.
   * 2. Xóa cache và trả về dữ liệu thiết bị sau khi chuyển trạng thái sang hỏng.
   *
   * [3. NGUỒN GỌI (CALLERS)]
   * - Store: `useEquipmentStore::reportBroken()`
   * - Modal: `ReportBrokenModal.vue`
   *
   * [4. THAM SỐ (PARAMETERS)]
   * @param {number|string} id ID thiết bị
   * @param {FormData|Object} payload Dữ liệu báo hỏng
   */
  async reportBroken(id, payload) {
    return runEquipmentMutation(async () => {
      const isFormData = payload instanceof FormData
      const response = await axiosClient.post(Endpoints.EQUIPMENT.REPORT_BROKEN(id), payload, {
        headers: isFormData ? { 'Content-Type': 'multipart/form-data' } : {},
      })
      return response.data
    })
  },

  /**
   * [1. MỤC ĐÍCH]
   * Cập nhật tiến độ xử lý và chi phí sửa chữa của phiếu bảo trì.
   *
   * [2. LUỒNG XỬ LÝ (FLOW)]
   * 1. Gửi PATCH `Endpoints.EQUIPMENT.UPDATE_MAINTENANCE_LOG(logId)` kèm `status`, `cost`, `issue_description`.
   * 2. Xóa cache và trả về phiếu bảo trì cập nhật.
   *
   * [3. NGUỒN GỌI (CALLERS)]
   * - Form xử lý sự cố thiết bị tại trang Chi tiết thiết bị
   *
   * [4. THAM SỐ (PARAMETERS)]
   * @param {number|string} logId ID phiếu bảo trì
   * @param {Object} data Dữ liệu cập nhật
   */
  async updateMaintenanceLog(logId, data) {
    return runEquipmentMutation(async () => {
      const response = await axiosClient.patch(Endpoints.EQUIPMENT.UPDATE_MAINTENANCE_LOG(logId), data)
      return response.data?.data || response.data
    })
  },

  /**
   * [1. MỤC ĐÍCH]
   * Tải ảnh chụp thực tế hoặc ảnh hiện trường hư hỏng của thiết bị lên hệ thống.
   *
   * [2. LUỒNG XỬ LÝ (FLOW)]
   * 1. Gửi POST `Endpoints.EQUIPMENT.UPLOAD_PHOTO(id)` với FormData file ảnh.
   * 2. Xóa cache và trả về dữ liệu thiết bị đã gắn ảnh.
   *
   * [3. NGUỒN GỌI (CALLERS)]
   * - Component upload ảnh trong form thiết bị
   *
   * [4. THAM SỐ (PARAMETERS)]
   * @param {number|string} id ID thiết bị
   * @param {FormData} formData Chứa tệp file và collection
   */
  async uploadPhoto(id, formData) {
    return runEquipmentMutation(async () => {
      const response = await axiosClient.post(Endpoints.EQUIPMENT.UPLOAD_PHOTO(id), formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })
      return response.data
    })
  },

  /**
   * [1. MỤC ĐÍCH]
   * Xóa 1 ảnh đính kèm của thiết bị.
   *
   * [2. LUỒNG XỬ LÝ (FLOW)]
   * 1. Gửi DELETE `Endpoints.EQUIPMENT.DELETE_PHOTO(id, mediaId)`.
   * 2. Xóa cache và trả về thiết bị với danh sách ảnh mới.
   *
   * [3. NGUỒN GỌI (CALLERS)]
   * - Gallery ảnh tại trang chi tiết thiết bị
   *
   * [4. THAM SỐ (PARAMETERS)]
   * @param {number|string} id ID thiết bị
   * @param {number|string} mediaId ID của file ảnh
   */
  async deletePhoto(id, mediaId) {
    return runEquipmentMutation(async () => {
      const response = await axiosClient.delete(Endpoints.EQUIPMENT.DELETE_PHOTO(id, mediaId))
      return response.data
    })
  },

  /**
   * [1. MỤC ĐÍCH]
   * Lấy danh sách các Tòa nhà / Block hiện có của khách sạn để hiển thị bộ lọc sơ đồ mặt bằng.
   *
   * [2. NGUỒN GỌI (CALLERS)]
   * - Component: `MapFilterToolbar.vue`
   *
   * [5. GIÁ TRỊ TRẢ VỀ]
   * @returns {Promise<Array<string>>} Mảng tên các block (ví dụ: ['Block A', 'Block B'])
   */
  async getBlocks() {
    const response = await axiosClient.get(Endpoints.EQUIPMENT.MAP_VIEW_BLOCKS)
    return response.data?.data || []
  },

  /**
   * [1. MỤC ĐÍCH]
   * Lấy danh sách các Tầng hiện có (có thể lọc theo tòa nhà).
   *
   * [2. NGUỒN GỌI (CALLERS)]
   * - Component: `MapFilterToolbar.vue`
   *
   * [4. THAM SỐ (PARAMETERS)]
   * @param {string} [block=''] Tên block cần lọc tầng
   * @returns {Promise<Array<number>>} Mảng số tầng
   */
  async getFloors(block = '') {
    const params = block ? { block } : {}
    const response = await axiosClient.get(Endpoints.EQUIPMENT.MAP_VIEW_FLOORS, { params })
    return response.data?.data || []
  },

  /**
   * [1. MỤC ĐÍCH]
   * Lấy ma trận lưới sơ đồ phòng (Map View) kèm trạng thái thiết bị của từng phòng, có cache 30 giây.
   *
   * [2. NGUỒN GỌI (CALLERS)]
   * - View: `Frontend/src/modules/equipment/views/equipment-map/index.vue`
   *
   * [4. THAM SỐ (PARAMETERS)]
   * @param {Object} [params={}] Chứa `block`, `floor`
   * @returns {Promise<Array<Object>>} Danh sách phòng sắp xếp theo tọa độ hàng/cột
   */
  async getMapView(params = {}) {
    return getCachedRequest('equipment-map', stableParams(params), async () => {
      const response = await axiosClient.get(Endpoints.EQUIPMENT.MAP_VIEW, { params })
      return response.data?.data || []
    }, { ttl: 30000 })
  },

  /**
   * [1. MỤC ĐÍCH]
   * Lấy thông tin chi tiết thiết bị, định mức và sơ đồ chia sẻ wifi của một phòng cụ thể.
   *
   * [2. NGUỒN GỌI (CALLERS)]
   * - Modal: `MapRoomDetailModal.vue` khi click vào thẻ phòng trên sơ đồ Map
   *
   * [4. THAM SỐ (PARAMETERS)]
   * @param {number|string} roomId ID phòng
   * @returns {Promise<Object>} Model phòng kèm quan hệ thiết bị và định mức
   */
  async getRoomDetail(roomId) {
    return getCachedRequest('equipment-room-detail', String(roomId), async () => {
      const response = await axiosClient.get(Endpoints.EQUIPMENT.ROOM_DETAIL(roomId))
      return response.data?.data || response.data
    }, { ttl: 30000 })
  },

  /**
   * [1. MỤC ĐÍCH]
   * Tải về file bảng tính Excel danh sách thiết bị hoặc tải file mẫu nhập liệu.
   *
   * [2. LUỒNG XỬ LÝ (FLOW)]
   * 1. Gửi GET `Endpoints.EQUIPMENT.EXPORT` với `responseType = 'blob'`.
   * 2. Trả về Blob nhị phân để client tự động tải file về máy.
   *
   * [3. NGUỒN GỌI (CALLERS)]
   * - Nút "Xuất Excel" và "Tải file mẫu" trên thanh công cụ
   *
   * [4. THAM SỐ (PARAMETERS)]
   * @param {Object} [params={}] Tham số lọc hoặc `{ template: true }`
   * @returns {Promise<Blob>} Dữ liệu file nhị phân
   */
  async exportExcel(params = {}) {
    const response = await axiosClient.get(Endpoints.EQUIPMENT.EXPORT, {
      params,
      responseType: 'blob',
    })
    return response.data
  },

  /**
   * [1. MỤC ĐÍCH]
   * Nhập dữ liệu danh sách thiết bị hàng loạt từ file Excel.
   *
   * [2. LUỒNG XỬ LÝ (FLOW)]
   * 1. Tạo FormData đính kèm file Excel.
   * 2. Gửi POST `Endpoints.EQUIPMENT.IMPORT`.
   * 3. Xóa cache và trả về số lượng bản ghi thành công/thất bại.
   *
   * [3. NGUỒN GỌI (CALLERS)]
   * - Modal: `ImportEquipmentModal.vue`
   *
   * [4. THAM SỐ (PARAMETERS)]
   * @param {File} formData File Excel do người dùng chọn
   * @returns {Promise<Object>} Kết quả import
   */
  async importExcel(formData) {
    return runEquipmentMutation(async () => {
      const payload = new FormData()
      payload.append('file', formData)
      const response = await axiosClient.post(Endpoints.EQUIPMENT.IMPORT, payload, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })
      return response.data
    })
  },

  /**
   * [1. MỤC ĐÍCH]
   * Lấy danh sách các mẫu thiết bị (Equipment Models) cho dropdown chọn mẫu, có cache 5 phút.
   *
   * [2. NGUỒN GỌI (CALLERS)]
   * - Dropdown chọn model trong `EquipmentFormModal.vue`
   *
   * [4. THAM SỐ (PARAMETERS)]
   * @param {boolean} [forceRefresh=false] Bỏ qua cache để tải mới
   * @returns {Promise<Array<Object>>} Danh sách các mẫu thiết bị
   */
  async getModels(forceRefresh = false) {
    return getCachedRequest('equipment-models', 'all', async () => {
      const response = await axiosClient.get(Endpoints.EQUIPMENT.MODELS)
      return response.data?.data || []
    }, { ttl: 300000, force: forceRefresh })
  },

  /**
   * [1. MỤC ĐÍCH]
   * Tạo mới một mẫu thiết bị (Equipment Model).
   */
  async createModel(data) {
    return runEquipmentConfigurationMutation(async () => {
      const response = await axiosClient.post(Endpoints.EQUIPMENT.MODELS, data)
      return response.data?.data || response.data
    })
  },

  /**
   * [1. MỤC ĐÍCH]
   * Cập nhật thông tin mẫu thiết bị theo ID.
   */
  async updateModel(id, data) {
    return runEquipmentConfigurationMutation(async () => {
      const response = await axiosClient.put(Endpoints.EQUIPMENT.MODEL_DETAIL(id), data)
      return response.data?.data || response.data
    })
  },

  /**
   * [1. MỤC ĐÍCH]
   * Xóa một mẫu thiết bị khỏi hệ thống.
   */
  async deleteModel(id) {
    return runEquipmentConfigurationMutation(async () => {
      const response = await axiosClient.delete(Endpoints.EQUIPMENT.MODEL_DETAIL(id))
      return response.data
    })
  },

  /**
   * [1. MỤC ĐÍCH]
   * Lấy danh mục nhóm thiết bị (TV, Tủ lạnh, Máy lạnh, Wifi...) có cache 10 phút.
   *
   * [2. NGUỒN GỌI (CALLERS)]
   * - Component: `EquipmentCategorySelect.vue`, bộ lọc danh mục
   */
  async getCategories(forceRefresh = false) {
    return getCachedRequest('equipment-categories', 'all', async () => {
      const response = await axiosClient.get(Endpoints.EQUIPMENT.CATEGORIES)
      const categories = response.data?.data
      if (!Array.isArray(categories)) {
        throw new Error('Dữ liệu nhóm danh mục thiết bị không hợp lệ.')
      }
      return categories
    }, { ttl: 600000, force: forceRefresh })
  },

  /**
   * [1. MỤC ĐÍCH]
   * Thêm mới danh mục nhóm thiết bị.
   */
  async createCategory(data) {
    return runEquipmentConfigurationMutation(async () => {
      const response = await axiosClient.post(Endpoints.EQUIPMENT.CREATE_CATEGORY, data)
      return response.data?.data || response.data
    })
  },

  /**
   * [1. MỤC ĐÍCH]
   * Lấy danh sách phân cấp phòng (Block -> Tầng -> Phòng) phục vụ Cascader chọn phòng, có cache 2 phút.
   *
   * [2. NGUỒN GỌI (CALLERS)]
   * - Bộ chọn phòng trong `AssignRoomModal.vue`, `EquipmentFormModal.vue`
   */
  async getRoomOptions(forceRefresh = false) {
    return getCachedRequest('equipment-room-options', 'all', async () => {
      const response = await axiosClient.get(Endpoints.EQUIPMENT.ROOM_OPTIONS)
      return response.data?.data || []
    }, { ttl: 120000, force: forceRefresh })
  },

  /**
   * [1. MỤC ĐÍCH]
   * Xóa cache danh sách phòng để lần chọn kế tiếp sẽ tải lại dữ liệu mới nhất.
   */
  clearRoomOptionsCache() {
    invalidateRequestCache('equipment-room-options')
  },
}
