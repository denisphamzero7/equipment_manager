import axiosClient from '@/core/api/axiosClient'
import { Endpoints } from '@/core/api/endpoints'
import { getCachedRequest, invalidateRequestCache } from '@/core/services/requestCache'

function stableParams(params) {
  return JSON.stringify(Object.fromEntries(
    Object.entries(params).sort(([left], [right]) => left.localeCompare(right))
  ))
}

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

async function runRoomMutation(request) {
  invalidateRoomData()
  try {
    return await request()
  } finally {
    invalidateRoomData()
  }
}

export const roomService = {
  /**
   * Lấy danh sách phòng có phân trang và bộ lọc
   */
  async getRooms(params = {}) {
    return getCachedRequest('room-list', stableParams(params), async () => {
      const response = await axiosClient.get(Endpoints.ROOM.INDEX, { params })
      return response.data
    }, { ttl: 10000 })
  },

  /**
   * Lấy danh mục hạng phòng (Standard, Deluxe, VIP...)
   */
  async getRoomTypes(forceRefresh = false) {
    return getCachedRequest('room-types', 'all', async () => {
      const response = await axiosClient.get(Endpoints.ROOM.ROOM_TYPES)
      return response.data?.data || response.data
    }, { ttl: 300000, force: forceRefresh })
  },

  /**
   * Lấy chi tiết 1 phòng
   */
  async getRoom(id) {
    return getCachedRequest('room-detail', String(id), async () => {
      const response = await axiosClient.get(Endpoints.ROOM.SHOW(id))
      return response.data?.data || response.data
    })
  },

  /**
   * Tạo phòng đơn mới
   */
  async createRoom(data) {
    return runRoomMutation(async () => {
      const response = await axiosClient.post(Endpoints.ROOM.STORE, data)
      return response.data?.data || response.data
    })
  },

  /**
   * Cập nhật thông tin phòng
   */
  async updateRoom(id, data) {
    return runRoomMutation(async () => {
      const response = await axiosClient.put(Endpoints.ROOM.UPDATE(id), data)
      return response.data?.data || response.data
    })
  },

  /**
   * Xóa phòng an toàn (thu hồi thiết bị về kho)
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
   * Xóa nhiều phòng
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

  async exportExcel(params = {}) {
    const response = await axiosClient.get(Endpoints.ROOM.EXPORT, {
      params,
      responseType: 'blob',
    })
    return response.data
  },

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
   * Tạo phòng nhanh hàng loạt theo tầng và block
   */
  async batchGenerate(data) {
    return runRoomMutation(async () => {
      const response = await axiosClient.post(Endpoints.ROOM.BATCH_GENERATE, data)
      return response.data
    })
  },

  /**
   * Đổi tên Block / Khu vực
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
   * Xóa toàn bộ phòng trong Block
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
   * Lấy chi tiết trang thiết bị thuộc phòng
   */
  async getRoomEquipmentDetail(roomId) {
    return getCachedRequest('equipment-room-detail', String(roomId), async () => {
      const response = await axiosClient.get(Endpoints.EQUIPMENT.ROOM_DETAIL(roomId))
      return response.data?.data || response.data
    }, { ttl: 30000 })
  },
}
