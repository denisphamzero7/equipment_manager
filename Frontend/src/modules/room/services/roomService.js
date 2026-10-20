import axiosClient from '@/core/api/axiosClient'
import { Endpoints } from '@/core/api/endpoints'

export const roomService = {
  async getRooms(params = {}) {
    const response = await axiosClient.get(Endpoints.ROOM.INDEX, { params })
    return response.data?.data || response.data
  },

  async getRoomEquipmentDetail(roomId) {
    const response = await axiosClient.get(Endpoints.EQUIPMENT.ROOM_DETAIL(roomId))
    return response.data?.data || response.data
  },
}
