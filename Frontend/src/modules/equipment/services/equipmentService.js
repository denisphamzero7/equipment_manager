import axiosClient from '@/core/api/axiosClient'
import { Endpoints } from '@/core/api/endpoints'

export const equipmentService = {
  // 1. Danh sách thiết bị (phân trang & lọc)
  async getEquipments(params = {}) {
    const response = await axiosClient.get(Endpoints.EQUIPMENT.INDEX, { params })
    return response.data
  },

  // 2. Chi tiết 1 thiết bị
  async getEquipment(id) {
    const response = await axiosClient.get(Endpoints.EQUIPMENT.SHOW(id))
    return response.data?.data || response.data
  },

  // 3. Tạo mới thiết bị
  async createEquipment(data) {
    const isFormData = data instanceof FormData
    const response = await axiosClient.post(Endpoints.EQUIPMENT.STORE, data, {
      headers: isFormData ? { 'Content-Type': 'multipart/form-data' } : {},
    })
    return response.data
  },

  // 4. Cập nhật thiết bị
  async updateEquipment(id, data) {
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
  },

  // 5. Xóa thiết bị
  async deleteEquipment(id) {
    const response = await axiosClient.delete(Endpoints.EQUIPMENT.DESTROY(id))
    return response.data
  },

  // 6. Đổi trạng thái nhanh
  async changeStatus(id, status, note = '') {
    const response = await axiosClient.patch(Endpoints.EQUIPMENT.CHANGE_STATUS(id), { status, note })
    return response.data
  },

  // 7. Cập nhật trạng thái hàng loạt
  async bulkUpdateStatus(ids, status, note = '') {
    const response = await axiosClient.patch(Endpoints.EQUIPMENT.BULK_STATUS, { ids, status, note })
    return response.data
  },

  // 8. Xóa hàng loạt
  async bulkDestroy(ids) {
    const response = await axiosClient.post(Endpoints.EQUIPMENT.BULK_DESTROY, { ids })
    return response.data
  },

  // 9. Điều chuyển phòng / về kho
  async assignToRoom(payload) {
    // payload: { equipment_id, room_id, position_note }
    const response = await axiosClient.post(Endpoints.EQUIPMENT.ASSIGN_ROOM, payload)
    return response.data
  },

  // 9.1 Kiểm tra định mức phòng đích trước khi điều chuyển
  async checkAssignTarget(equipmentId, targetRoomId = null) {
    const params = targetRoomId ? { target_room_id: targetRoomId } : {}
    const response = await axiosClient.get(Endpoints.EQUIPMENT.CHECK_ASSIGN_TARGET(equipmentId), { params })
    return response.data?.data || response.data
  },

  // 10. Báo hỏng thiết bị
  async reportBroken(id, payload) {
    // payload: FormData with issue_description & optional broken_photo
    const isFormData = payload instanceof FormData
    const response = await axiosClient.post(Endpoints.EQUIPMENT.REPORT_BROKEN(id), payload, {
      headers: isFormData ? { 'Content-Type': 'multipart/form-data' } : {},
    })
    return response.data
  },

  // 11. Tải ảnh thiết bị
  async uploadPhoto(id, formData) {
    const response = await axiosClient.post(Endpoints.EQUIPMENT.UPLOAD_PHOTO(id), formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    return response.data
  },

  // 11.1 Xóa ảnh thiết bị
  async deletePhoto(id, mediaId) {
    const response = await axiosClient.delete(Endpoints.EQUIPMENT.DELETE_PHOTO(id, mediaId))
    return response.data
  },

  // 12. Sơ đồ mặt bằng - Blocks
  async getBlocks() {
    const response = await axiosClient.get(Endpoints.EQUIPMENT.MAP_VIEW_BLOCKS)
    return response.data?.data || []
  },

  // 13. Sơ đồ mặt bằng - Floors
  async getFloors(block = '') {
    const params = block ? { block } : {}
    const response = await axiosClient.get(Endpoints.EQUIPMENT.MAP_VIEW_FLOORS, { params })
    return response.data?.data || []
  },

  // 14. Sơ đồ mặt bằng - Rooms grid
  async getMapView(params = {}) {
    const response = await axiosClient.get(Endpoints.EQUIPMENT.MAP_VIEW, { params })
    return response.data?.data || []
  },

  // 15. Chi tiết thiết bị phòng
  async getRoomDetail(roomId) {
    const response = await axiosClient.get(Endpoints.EQUIPMENT.ROOM_DETAIL(roomId))
    return response.data?.data || response.data
  },

  // 16. Xuất Excel
  async exportExcel(params = {}) {
    const response = await axiosClient.get(Endpoints.EQUIPMENT.EXPORT, {
      params,
      responseType: 'blob',
    })
    return response.data
  },

  // 17. Nhập Excel
  async importExcel(formData) {
    const response = await axiosClient.post(Endpoints.EQUIPMENT.IMPORT, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    return response.data
  },

  // 18. Quản lý Mẫu thiết bị (Equipment Models)
  async getModels() {
    const response = await axiosClient.get(Endpoints.EQUIPMENT.MODELS)
    return response.data?.data || []
  },

  async createModel(data) {
    const response = await axiosClient.post(Endpoints.EQUIPMENT.MODELS, data)
    return response.data?.data || response.data
  },

  async updateModel(id, data) {
    const response = await axiosClient.put(Endpoints.EQUIPMENT.MODEL_DETAIL(id), data)
    return response.data?.data || response.data
  },

  async deleteModel(id) {
    const response = await axiosClient.delete(Endpoints.EQUIPMENT.MODEL_DETAIL(id))
    return response.data
  },

  // 19. Danh mục nhóm thiết bị (Categories)
  async getCategories() {
    const response = await axiosClient.get(Endpoints.EQUIPMENT.CATEGORIES)
    return response.data?.data || []
  },

  // 20. Danh sách phòng cho bộ chọn bậc thang (Room Options)
  async getRoomOptions() {
    const response = await axiosClient.get(Endpoints.EQUIPMENT.ROOM_OPTIONS)
    return response.data?.data || []
  },
}
