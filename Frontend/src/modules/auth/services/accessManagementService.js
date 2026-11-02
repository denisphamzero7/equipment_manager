import axiosClient from '@/core/api/axiosClient'
import { Endpoints } from '@/core/api/endpoints'

/**
 * [1. MỤC ĐÍCH]
 * Tầng dịch vụ quản trị phân quyền (Access Management / RBAC):
 * Quản lý Người dùng (Users), Vai trò (Roles), Cây quyền hạn (Permission Tree) và Thao tác hàng loạt.
 */
export const accessManagementService = {
  // ==========================================
  // 1. QUẢN LÝ NGƯỜI DÙNG (USERS)
  // ==========================================

  /**
   * [1. MỤC ĐÍCH]: Lấy danh sách người dùng có phân trang và bộ lọc.
   * [3. NGUỒN GỌI]: `Frontend/src/modules/auth/views/users/index.vue`
   * @param {Object} params Tham số lọc: page, per_page, search, status, role_id...
   */
  async getUsers(params) {
    return (await axiosClient.get(Endpoints.CORE.USERS, { params })).data
  },

  /**
   * [1. MỤC ĐÍCH]: Thống kê số lượng người dùng theo trạng thái hoạt động/khóa.
   */
  async getUserStats(params) {
    return (await axiosClient.get(Endpoints.CORE.USER_STATS, { params })).data?.data
  },

  /**
   * [1. MỤC ĐÍCH]: Thêm mới người dùng vào hệ thống.
   * @param {Object} payload Dữ liệu: name, email, user_name, password, roles...
   */
  async createUser(payload) {
    return (await axiosClient.post(Endpoints.CORE.USERS, payload)).data
  },

  /**
   * [1. MỤC ĐÍCH]: Cập nhật thông tin hoặc vai trò của người dùng.
   * @param {number|string} id ID người dùng
   * @param {Object} payload Dữ liệu cập nhật
   */
  async updateUser(id, payload) {
    return (await axiosClient.put(Endpoints.CORE.USER(id), payload)).data
  },

  /**
   * [1. MỤC ĐÍCH]: Xóa người dùng theo ID.
   */
  async deleteUser(id) {
    return (await axiosClient.delete(Endpoints.CORE.USER(id))).data
  },

  /**
   * [1. MỤC ĐÍCH]: Xóa hàng loạt nhiều người dùng cùng lúc.
   * @param {Array<number>} ids Danh sách ID
   */
  async bulkDestroyUsers(ids) {
    return (await axiosClient.post(Endpoints.CORE.USERS_BULK_DESTROY, { ids })).data
  },

  /**
   * [1. MỤC ĐÍCH]: Đổi trạng thái (kích hoạt/khóa) hàng loạt cho nhiều người dùng.
   * @param {Array<number>} ids Danh sách ID
   * @param {string} status 'active' hoặc 'inactive'
   */
  async bulkUpdateStatusUsers(ids, status) {
    return (await axiosClient.patch(Endpoints.CORE.USERS_BULK_STATUS, { ids, status })).data
  },

  // ==========================================
  // 2. QUẢN LÝ VAI TRÒ (ROLES)
  // ==========================================

  /**
   * [1. MỤC ĐÍCH]: Lấy danh sách các vai trò (Admin, Quản lý, Kỹ thuật viên, Lễ tân...) trong tổ chức.
   * [3. NGUỒN GỌI]: `Frontend/src/modules/auth/views/roles/index.vue`
   */
  async getRoles(params = { limit: 100 }) {
    return (await axiosClient.get(Endpoints.CORE.ROLES, { params })).data
  },

  /**
   * [1. MỤC ĐÍCH]: Tạo mới một vai trò kèm danh sách quyền hạn gắn kèm.
   * @param {Object} payload { name, description, permissions: [] }
   */
  async createRole(payload) {
    return (await axiosClient.post(Endpoints.CORE.ROLES, payload)).data
  },

  /**
   * [1. MỤC ĐÍCH]: Cập nhật tên vai trò hoặc danh sách quyền hạn của vai trò.
   */
  async updateRole(id, payload) {
    return (await axiosClient.put(Endpoints.CORE.ROLE(id), payload)).data
  },

  /**
   * [1. MỤC ĐÍCH]: Xóa một vai trò khỏi hệ thống.
   */
  async deleteRole(id) {
    return (await axiosClient.delete(Endpoints.CORE.ROLE(id))).data
  },

  /**
   * [1. MỤC ĐÍCH]: Xóa đồng thời nhiều vai trò.
   */
  async bulkDestroyRoles(ids) {
    return (await axiosClient.post(Endpoints.CORE.ROLES_BULK_DESTROY, { ids })).data
  },

  // ==========================================
  // 3. CÂY QUYỀN HẠN (PERMISSIONS)
  // ==========================================

  /**
   * [1. MỤC ĐÍCH]: Lấy toàn bộ cây danh mục quyền hạn gom nhóm theo module và tính năng (Permission Tree).
   * [3. NGUỒN GỌI]: Bảng ma trận phân quyền trong `Frontend/src/modules/auth/views/permissions/index.vue`
   */
  async getPermissionTree() {
    return (await axiosClient.get(Endpoints.CORE.PERMISSION_TREE)).data
  },

  /**
   * [1. MỤC ĐÍCH]: Tạo mới một quyền hạn trong hệ thống.
   */
  async createPermission(payload) {
    return (await axiosClient.post(Endpoints.CORE.PERMISSIONS, payload)).data
  },

  /**
   * [1. MỤC ĐÍCH]: Cập nhật thông tin định danh của quyền hạn.
   */
  async updatePermission(id, payload) {
    return (await axiosClient.put(Endpoints.CORE.PERMISSION(id), payload)).data
  },

  /**
   * [1. MỤC ĐÍCH]: Xóa một quyền hạn.
   */
  async deletePermission(id) {
    return (await axiosClient.delete(Endpoints.CORE.PERMISSION(id))).data
  },

  /**
   * [1. MỤC ĐÍCH]: Xóa hàng loạt nhiều quyền hạn.
   */
  async bulkDestroyPermissions(ids) {
    return (await axiosClient.post(Endpoints.CORE.PERMISSIONS_BULK_DESTROY, { ids })).data
  },
}
