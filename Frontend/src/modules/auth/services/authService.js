import axiosClient from '@/core/api/axiosClient'
import { Endpoints } from '@/core/api/endpoints'

export const authService = {
  async login(credentials) {
    const response = await axiosClient.post(Endpoints.AUTH.LOGIN, credentials)
    return response.data
  },

  async register(data) {
    const response = await axiosClient.post(Endpoints.AUTH.REGISTER, data)
    return response.data
  },

  async getMe() {
    const response = await axiosClient.get(Endpoints.AUTH.ME)
    return response.data
  },

  async logout() {
    try {
      await axiosClient.post(Endpoints.AUTH.LOGOUT)
    } catch {
      // Bỏ qua lỗi server khi logout để dọn sạch client
    }
  },
}
