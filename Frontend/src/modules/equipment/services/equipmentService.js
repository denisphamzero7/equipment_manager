import axiosClient from '@/core/api/axiosClient'
import { Endpoints } from '@/core/api/endpoints'
import { getCachedRequest, invalidateRequestCache } from '@/core/services/requestCache'

function stableParams(params) {
  return JSON.stringify(Object.fromEntries(
    Object.entries(params).sort(([left], [right]) => left.localeCompare(right))
  ))
}

function invalidateEquipmentData() {
  invalidateRequestCache(
    'equipment-list',
    'equipment-detail',
    'equipment-map',
    'equipment-room-detail',
    'dashboard-stats'
  )
}

async function runEquipmentConfigurationMutation(request) {
  invalidateRequestCache('equipment-models', 'equipment-categories')
  try {
    return await runEquipmentMutation(request)
  } finally {
    invalidateRequestCache('equipment-models', 'equipment-categories')
  }
}

async function runEquipmentMutation(request) {
  invalidateEquipmentData()
  try {
    return await request()
  } finally {
    invalidateEquipmentData()
  }
}

export const equipmentService = {
  // 1. Danh sách thiết bị (phân trang & lọc)
  async getEquipments(params = {}) {
    return getCachedRequest('equipment-list', stableParams(params), async () => {
      const response = await axiosClient.get(Endpoints.EQUIPMENT.INDEX, { params })
      return response.data
    }, { ttl: 10000 })
  },

  // 2. Chi tiết 1 thiết bị
  async getEquipment(id) {
    return getCachedRequest('equipment-detail', String(id), async () => {
      const response = await axiosClient.get(Endpoints.EQUIPMENT.SHOW(id))
      return response.data?.data || response.data
    })
  },

  // 3. Tạo mới thiết bị
  async createEquipment(data) {
    return runEquipmentMutation(async () => {
      const isFormData = data instanceof FormData
      const response = await axiosClient.post(Endpoints.EQUIPMENT.STORE, data, {
        headers: isFormData ? { 'Content-Type': 'multipart/form-data' } : {},
      })
      return response.data
    })
  },

  // 4. Cập nhật thiết bị
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

  // 5. Xóa thiết bị
  async deleteEquipment(id) {
    return runEquipmentMutation(async () => {
      const response = await axiosClient.delete(Endpoints.EQUIPMENT.DESTROY(id))
      return response.data
    })
  },

  // 6. Đổi trạng thái nhanh
  async changeStatus(id, status, note = '') {
    return runEquipmentMutation(async () => {
      const response = await axiosClient.patch(Endpoints.EQUIPMENT.CHANGE_STATUS(id), { status, note })
      return response.data
    })
  },

  // 7. Cập nhật trạng thái hàng loạt
  async bulkUpdateStatus(ids, status, note = '') {
    return runEquipmentMutation(async () => {
      const response = await axiosClient.patch(Endpoints.EQUIPMENT.BULK_STATUS, { ids, status, note })
      return response.data
    })
  },

  // 8. Xóa hàng loạt
  async bulkDestroy(ids) {
    return runEquipmentMutation(async () => {
      const response = await axiosClient.post(Endpoints.EQUIPMENT.BULK_DESTROY, { ids })
      return response.data
    })
  },

  // 9. Điều chuyển phòng / về kho
  async assignToRoom(payload) {
    return runEquipmentMutation(async () => {
      const response = await axiosClient.post(Endpoints.EQUIPMENT.ASSIGN_ROOM, payload)
      return response.data
    })
  },

  // 9.1 Kiểm tra định mức phòng đích trước khi điều chuyển
  async checkAssignTarget(equipmentId, targetRoomId = null) {
    const params = targetRoomId ? { target_room_id: targetRoomId } : {}
    const response = await axiosClient.get(Endpoints.EQUIPMENT.CHECK_ASSIGN_TARGET(equipmentId), { params })
    return response.data?.data || response.data
  },

  // 10. Báo hỏng thiết bị
  async reportBroken(id, payload) {
    return runEquipmentMutation(async () => {
      const isFormData = payload instanceof FormData
      const response = await axiosClient.post(Endpoints.EQUIPMENT.REPORT_BROKEN(id), payload, {
        headers: isFormData ? { 'Content-Type': 'multipart/form-data' } : {},
      })
      return response.data
    })
  },

  // 10.1 Cập nhật tiến độ xử lý và chi phí phiếu bảo trì
  async updateMaintenanceLog(logId, data) {
    return runEquipmentMutation(async () => {
      const response = await axiosClient.patch(Endpoints.EQUIPMENT.UPDATE_MAINTENANCE_LOG(logId), data)
      return response.data?.data || response.data
    })
  },

  // 11. Tải ảnh thiết bị
  async uploadPhoto(id, formData) {
    return runEquipmentMutation(async () => {
      const response = await axiosClient.post(Endpoints.EQUIPMENT.UPLOAD_PHOTO(id), formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })
      return response.data
    })
  },

  // 11.1 Xóa ảnh thiết bị
  async deletePhoto(id, mediaId) {
    return runEquipmentMutation(async () => {
      const response = await axiosClient.delete(Endpoints.EQUIPMENT.DELETE_PHOTO(id, mediaId))
      return response.data
    })
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
    return getCachedRequest('equipment-map', stableParams(params), async () => {
      const response = await axiosClient.get(Endpoints.EQUIPMENT.MAP_VIEW, { params })
      return response.data?.data || []
    }, { ttl: 30000 })
  },

  // 15. Chi tiết thiết bị phòng
  async getRoomDetail(roomId) {
    return getCachedRequest('equipment-room-detail', String(roomId), async () => {
      const response = await axiosClient.get(Endpoints.EQUIPMENT.ROOM_DETAIL(roomId))
      return response.data?.data || response.data
    }, { ttl: 30000 })
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
    return runEquipmentMutation(async () => {
      const payload = new FormData()
      payload.append('file', formData)
      const response = await axiosClient.post(Endpoints.EQUIPMENT.IMPORT, payload, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })
      return response.data
    })
  },

  // 18. Quản lý Mẫu thiết bị (Equipment Models)
  async getModels(forceRefresh = false) {
    return getCachedRequest('equipment-models', 'all', async () => {
      const response = await axiosClient.get(Endpoints.EQUIPMENT.MODELS)
      return response.data?.data || []
    }, { ttl: 300000, force: forceRefresh })
  },

  async createModel(data) {
    return runEquipmentConfigurationMutation(async () => {
      const response = await axiosClient.post(Endpoints.EQUIPMENT.MODELS, data)
      return response.data?.data || response.data
    })
  },

  async updateModel(id, data) {
    return runEquipmentConfigurationMutation(async () => {
      const response = await axiosClient.put(Endpoints.EQUIPMENT.MODEL_DETAIL(id), data)
      return response.data?.data || response.data
    })
  },

  async deleteModel(id) {
    return runEquipmentConfigurationMutation(async () => {
      const response = await axiosClient.delete(Endpoints.EQUIPMENT.MODEL_DETAIL(id))
      return response.data
    })
  },

  // 19. Danh mục nhóm thiết bị (Categories)
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

  async createCategory(data) {
    return runEquipmentConfigurationMutation(async () => {
      const response = await axiosClient.post(Endpoints.EQUIPMENT.CREATE_CATEGORY, data)
      return response.data?.data || response.data
    })
  },

  // 20. Danh sách phòng cho bộ chọn bậc thang (Room Options)
  async getRoomOptions(forceRefresh = false) {
    return getCachedRequest('equipment-room-options', 'all', async () => {
      const response = await axiosClient.get(Endpoints.EQUIPMENT.ROOM_OPTIONS)
      return response.data?.data || []
    }, { ttl: 120000, force: forceRefresh })
  },

  clearRoomOptionsCache() {
    invalidateRequestCache('equipment-room-options')
  },
}
