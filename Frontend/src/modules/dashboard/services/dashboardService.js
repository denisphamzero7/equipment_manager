import axiosClient from '@/core/api/axiosClient'
import { Endpoints } from '@/core/api/endpoints'
import { getCachedRequest } from '@/core/services/requestCache'
import { equipmentService } from '@/modules/equipment/services/equipmentService'

export const dashboardService = {
  async getStats(block = '', forceRefresh = false) {
    return getCachedRequest('dashboard-stats', block || 'all', async () => {
      const params = block ? { block } : {}
      const response = await axiosClient.get(Endpoints.EQUIPMENT.STATS, { params })
      return response.data?.data || response.data
    }, { ttl: 30000, force: forceRefresh })
  },

  async getMapOverview(forceRefresh = false) {
    return equipmentService.getMapView({}, forceRefresh)
  },
}
