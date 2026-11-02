import { defineStore } from 'pinia'
import { authService } from '../services/authService'
import { storage, StorageKeys } from '@/core/helpers/storage'
import { updateAbility } from '@/core/casl/ability'
import { clearRequestCache } from '@/core/services/requestCache'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    // Access token xác thực (Sanctum Bearer Token)
    token: storage.get(StorageKeys.TOKEN, null),
    // Hồ sơ người dùng hiện tại
    user: storage.get(StorageKeys.USER, null),
    // Danh sách quyền hạn (permissions) của user tại tổ chức hiện tại
    permissions: storage.get(StorageKeys.PERMISSIONS, []),
    // ID tổ chức / cơ sở đang làm việc
    organizationId: storage.get(StorageKeys.ORGANIZATION_ID, null),
    // Danh sách các tổ chức user được phép truy cập
    availableOrganizations: storage.get('eq_available_orgs', []),
    // Trạng thái đang tải
    loading: false,
  }),

  getters: {
    /**
     * [Mục đích]: Kiểm tra người dùng đã đăng nhập hay chưa (có token hợp lệ).
     * @returns {boolean}
     */
    isAuthenticated: state => !!state.token,

    /**
     * [Mục đích]: Lấy danh sách các vai trò (roles) của người dùng hiện tại.
     * @returns {Array<string>}
     */
    userRoles: state => state.user?.roles || [],

    /**
     * [Mục đích]: Lấy thông tin đối tượng tổ chức hiện tại đang làm việc.
     * @returns {Object}
     */
    currentOrganization: state => {
      return state.availableOrganizations.find(o => o.id === state.organizationId) || {
        id: state.organizationId,
        name: 'Tổ chức hiện tại',
      }
    },
  },

  actions: {
    /**
     * [1. MỤC ĐÍCH]
     * Xử lý luồng đăng nhập của người dùng: Gọi API, lưu trữ token, khởi tạo quyền hạn CASL và lưu ngữ cảnh tổ chức.
     *
     * [2. LUỒNG XỬ LÝ (FLOW)]
     * 1. Bật cờ `loading = true`.
     * 2. Gọi API `authService.login(credentials)`.
     * 3. Xóa cache dữ liệu cũ (`clearRequestCache()`) để tránh hiển thị dữ liệu của phiên đăng nhập trước.
     * 4. Trích xuất thông tin: `token`, `user`, `roles`, `permissions`, `available_organizations`, `current_organization_id`.
     * 5. Lưu vào state của Pinia Store.
     * 6. Lưu đồng bộ vào LocalStorage qua `storage.set(...)` để duy trì phiên khi F5 hoặc mở tab mới.
     * 7. Cập nhật quyền hạn thời gian thực cho CASL (`updateAbility(user, permissions)`).
     * 8. Tắt `loading = false` và trả về kết quả cho component điều hướng (ví dụ: chuyển tới trang Dashboard).
     *
     * [3. NGUỒN GỌI (CALLERS)]
     * - Form đăng nhập: `Frontend/src/modules/auth/views/login/index.vue`
     *
     * [4. THAM SỐ (PARAMETERS)]
     * @param {Object} credentials Chứa { email, password }
     *
     * [5. GIÁ TRỊ TRẢ VỀ]
     * @returns {Promise<Object>} Object gồm { res, availableOrganizations, currentOrganizationId }
     */
    async login(credentials) {
      this.loading = true
      try {
        const res = await authService.login(credentials)
        const data = res.data || {}
        clearRequestCache()

        const token = data.access_token || data.token || res.access_token || res.token
        const user = data.user || res.user
        const roles = data.roles || []
        if (user && roles.length) {
          user.roles = roles
        }
        const permissions = data.permissions || user?.permissions || ['*']
        const availableOrgs = data.available_organizations || []
        const currentOrgId = data.current_organization_id || (availableOrgs[0]?.id ?? null)

        this.token = token
        this.user = user
        this.permissions = permissions
        this.availableOrganizations = availableOrgs
        this.organizationId = currentOrgId

        storage.set(StorageKeys.TOKEN, token)
        storage.set(StorageKeys.USER, user)
        storage.set(StorageKeys.PERMISSIONS, permissions)
        storage.set(StorageKeys.ORGANIZATION_ID, currentOrgId)
        storage.set('eq_available_orgs', availableOrgs)

        // Cập nhật CASL abilities
        updateAbility(user, permissions)

        return {
          res,
          availableOrganizations: availableOrgs,
          currentOrganizationId: currentOrgId,
        }
      } finally {
        this.loading = false
      }
    },

    /**
     * [1. MỤC ĐÍCH]
     * Chuyển đổi tổ chức làm việc: Cập nhật header X-Organization-Id và tải lại quyền hạn CASL tương ứng.
     *
     * [2. LUỒNG XỬ LÝ (FLOW)]
     * 1. Bật `loading = true`.
     * 2. Gọi `authService.switchOrganization(orgId)`.
     * 3. Xóa sạch cache request (`clearRequestCache()`) để tải lại toàn bộ danh sách phòng và thiết bị của tổ chức mới.
     * 4. Cập nhật `this.organizationId` và lưu vào LocalStorage.
     * 5. Cập nhật lại danh sách `permissions` và `roles` mới trả về từ backend.
     * 6. Cập nhật lại CASL abilities (`updateAbility`) để các nút chức năng ẩn/hiện chính xác theo quyền của tổ chức mới.
     *
     * [3. NGUỒN GỌI (CALLERS)]
     * - Menu dropdown chọn khách sạn/cơ sở trên thanh Header
     *
     * [4. THAM SỐ (PARAMETERS)]
     * @param {number|string} orgId ID tổ chức cần chuyển đổi
     */
    async selectOrganization(orgId) {
      this.loading = true
      try {
        const res = await authService.switchOrganization(orgId)
        clearRequestCache()
        const data = res.data || {}

        this.organizationId = Number(orgId)
        storage.set(StorageKeys.ORGANIZATION_ID, Number(orgId))

        if (data.roles && this.user) {
          this.user.roles = data.roles
        }
        if (this.user) {
          storage.set(StorageKeys.USER, this.user)
        }

        if (data.permissions) {
          this.permissions = data.permissions
          storage.set(StorageKeys.PERMISSIONS, data.permissions)
        }

        updateAbility(this.user, this.permissions)

        return res
      } finally {
        this.loading = false
      }
    },

    /**
     * [1. MỤC ĐÍCH]
     * Đăng ký tài khoản người dùng mới.
     *
     * [3. NGUỒN GỌI (CALLERS)]
     * - Form đăng ký: `Frontend/src/modules/auth/views/register/index.vue`
     */
    async register(payload) {
      this.loading = true
      try {
        const res = await authService.register(payload)
        return res
      } finally {
        this.loading = false
      }
    },

    /**
     * [1. MỤC ĐÍCH]
     * Khôi phục hồ sơ người dùng và quyền hạn khi người dùng mở lại trang hoặc ấn F5.
     *
     * [2. LUỒNG XỬ LÝ (FLOW)]
     * 1. Kiểm tra nếu chưa có token thì bỏ qua.
     * 2. Gửi request GET `/api/auth/me` để lấy thông tin mới nhất từ máy chủ.
     * 3. Cập nhật user, permissions và đồng bộ CASL abilities.
     * 4. Nếu token đã hết hạn hoặc server trả lỗi 401 -> Tự động gọi `this.logout()`.
     *
     * [3. NGUỒN GỌI (CALLERS)]
     * - Router Navigation Guard (`beforeEach`) khi khởi động ứng dụng
     */
    async fetchMe() {
      if (!this.token) return
      try {
        const res = await authService.getMe()
        const data = res.data || {}
        this.user = data.user || data
        this.permissions = data.permissions || this.permissions
        storage.set(StorageKeys.USER, this.user)
        storage.set(StorageKeys.PERMISSIONS, this.permissions)
        updateAbility(this.user, this.permissions)
      } catch {
        this.logout()
      }
    },

    /**
     * [1. MỤC ĐÍCH]
     * Cập nhật nhanh ID tổ chức hiện tại vào Store và LocalStorage.
     * @param {number|string} id ID tổ chức
     */
    setOrganizationId(id) {
      this.organizationId = Number(id)
      storage.set(StorageKeys.ORGANIZATION_ID, Number(id))
    },

    /**
     * [1. MỤC ĐÍCH]
     * Đăng xuất người dùng: Gọi API hủy token, xóa sạch dữ liệu trong LocalStorage và reset CASL.
     *
     * [2. LUỒNG XỬ LÝ (FLOW)]
     * 1. Gọi `authService.logout()`.
     * 2. Dọn sạch toàn bộ bộ nhớ tạm `clearRequestCache()`.
     * 3. Reset các biến trong state: `token = null`, `user = null`, `permissions = []`.
     * 4. Gọi `storage.clearAuth()` xóa token và quyền khỏi LocalStorage.
     * 5. Reset CASL abilities về rỗng: `updateAbility(null, [])`.
     *
     * [3. NGUỒN GỌI (CALLERS)]
     * - Nút "Đăng xuất" trên menu tài khoản Header
     * - Axios Interceptor khi gặp lỗi 401 Unauthorized
     */
    logout() {
      authService.logout()
      clearRequestCache()
      this.token = null
      this.user = null
      this.permissions = []
      this.availableOrganizations = []
      storage.clearAuth()
      storage.remove('eq_available_orgs')
      updateAbility(null, [])
    },
  },
})
