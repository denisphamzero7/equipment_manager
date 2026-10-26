import axiosClient from '@/core/api/axiosClient'
import { Endpoints } from '@/core/api/endpoints'

export const accessManagementService = {
  async getUsers(params) {
    return (await axiosClient.get(Endpoints.CORE.USERS, { params })).data
  },
  async getUserStats(params) {
    return (await axiosClient.get(Endpoints.CORE.USER_STATS, { params })).data?.data
  },
  async createUser(payload) {
    return (await axiosClient.post(Endpoints.CORE.USERS, payload)).data
  },
  async updateUser(id, payload) {
    return (await axiosClient.put(Endpoints.CORE.USER(id), payload)).data
  },
  async deleteUser(id) {
    return (await axiosClient.delete(Endpoints.CORE.USER(id))).data
  },
  async bulkDestroyUsers(ids) {
    return (await axiosClient.post(Endpoints.CORE.USERS_BULK_DESTROY, { ids })).data
  },
  async bulkUpdateStatusUsers(ids, status) {
    return (await axiosClient.patch(Endpoints.CORE.USERS_BULK_STATUS, { ids, status })).data
  },
  async getRoles(params = { limit: 100 }) {
    return (await axiosClient.get(Endpoints.CORE.ROLES, { params })).data
  },
  async createRole(payload) {
    return (await axiosClient.post(Endpoints.CORE.ROLES, payload)).data
  },
  async updateRole(id, payload) {
    return (await axiosClient.put(Endpoints.CORE.ROLE(id), payload)).data
  },
  async deleteRole(id) {
    return (await axiosClient.delete(Endpoints.CORE.ROLE(id))).data
  },
  async bulkDestroyRoles(ids) {
    return (await axiosClient.post(Endpoints.CORE.ROLES_BULK_DESTROY, { ids })).data
  },
  async getPermissionTree() {
    return (await axiosClient.get(Endpoints.CORE.PERMISSION_TREE)).data
  },
  async createPermission(payload) {
    return (await axiosClient.post(Endpoints.CORE.PERMISSIONS, payload)).data
  },
  async updatePermission(id, payload) {
    return (await axiosClient.put(Endpoints.CORE.PERMISSION(id), payload)).data
  },
  async deletePermission(id) {
    return (await axiosClient.delete(Endpoints.CORE.PERMISSION(id))).data
  },
  async bulkDestroyPermissions(ids) {
    return (await axiosClient.post(Endpoints.CORE.PERMISSIONS_BULK_DESTROY, { ids })).data
  },
}
