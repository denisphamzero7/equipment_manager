import axiosClient from '@/core/api/axiosClient'
import { Endpoints } from '@/core/api/endpoints'

export const roomService = {
  /**
   * Lấy danh sách phòng có phân trang và bộ lọc
   */
  async getRooms(params = {}) {
    const response = await axiosClient.get(Endpoints.ROOM.INDEX, { params })
    return response.data
  },

  /**
   * Lấy danh mục hạng phòng (Standard, Deluxe, VIP...)
   */
  async getRoomTypes() {
    const response = await axiosClient.get(Endpoints.ROOM.ROOM_TYPES)
    return response.data?.data || response.data
  },

  /**
   * Lấy chi tiết 1 phòng
   */
  async getRoom(id) {
    const response = await axiosClient.get(Endpoints.ROOM.SHOW(id))
    return response.data?.data || response.data
  },

  /**
   * Tạo phòng đơn mới
   */
  async createRoom(data) {
    const response = await axiosClient.post(Endpoints.ROOM.STORE, data)
    return response.data?.data || response.data
  },

  /**
   * Cập nhật thông tin phòng
   */
  async updateRoom(id, data) {
    const response = await axiosClient.put(Endpoints.ROOM.UPDATE(id), data)
    return response.data?.data || response.data
  },

  /**
   * Xóa phòng an toàn (thu hồi thiết bị về kho)
   */
  async deleteRoom(id, retractEquipments = true) {
    const response = await axiosClient.delete(Endpoints.ROOM.DESTROY(id), {
      params: { retract_equipments: retractEquipments },
    })
    return response.data
  },

  /**
   * Xóa nhiều phòng
   */
  async bulkDeleteRooms(ids, retractEquipments = true) {
    const response = await axiosClient.post(Endpoints.ROOM.BULK_DESTROY, {
      ids,
      retract_equipments: retractEquipments,
    })
    return response.data
  },

  /**
   * Tạo phòng nhanh hàng loạt theo tầng và block
   */
  async batchGenerate(data) {
    const response = await axiosClient.post(Endpoints.ROOM.BATCH_GENERATE, data)
    return response.data
  },

  /**
   * Đổi tên Block / Khu vực
   */
  async renameBlock(oldBlock, newBlock) {
    const response = await axiosClient.post(Endpoints.ROOM.RENAME_BLOCK, {
      old_block: oldBlock,
      new_block: newBlock,
    })
    return response.data
  },

  /**
   * Xóa toàn bộ phòng trong Block
   */
  async deleteBlock(block) {
    const response = await axiosClient.delete(Endpoints.ROOM.DELETE_BLOCK, {
      data: { block },
    })
    return response.data
  },

  /**
   * Lấy chi tiết trang thiết bị thuộc phòng
   */
  async getRoomEquipmentDetail(roomId) {
    const response = await axiosClient.get(Endpoints.EQUIPMENT.ROOM_DETAIL(roomId))
    return response.data?.data || response.data
  },
}
