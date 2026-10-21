import axiosClient from '@/core/api/axiosClient'
import { Endpoints } from '@/core/api/endpoints'

export const dashboardService = {
  async getStats(block = '') {
    const params = block ? { block } : {}
    const response = await axiosClient.get(Endpoints.EQUIPMENT.STATS, { params })
    return response.data?.data || response.data
  },

  async getMapOverview() {
    const response = await axiosClient.get(Endpoints.EQUIPMENT.MAP_VIEW)
    return response.data?.data || []
  },
}
