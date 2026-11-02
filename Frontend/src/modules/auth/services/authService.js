import axiosClient from '@/core/api/axiosClient'
import { Endpoints } from '@/core/api/endpoints'

/**
 * [1. MỤC ĐÍCH]
 * Tầng dịch vụ API thực hiện các tác vụ xác thực: Đăng nhập, Đăng ký, Lấy thông tin tài khoản (Me), Chuyển tổ chức, Đăng xuất.
 */
export const authService = {
  /**
   * [1. MỤC ĐÍCH]
   * Gửi thông tin đăng nhập lên máy chủ để xác thực và nhận Access Token.
   *
   * [2. LUỒNG XỬ LÝ (FLOW)]
   * 1. Gửi POST `Endpoints.AUTH.LOGIN` kèm `credentials` ({ email, password }).
   * 2. Nhận phản hồi chứa access_token, thông tin user, quyền hạn và danh sách tổ chức.
   * 3. Trả về `response.data`.
   *
   * [3. NGUỒN GỌI (CALLERS)]
   * - Store: `Frontend/src/modules/auth/stores/authStore.js` -> `login()`
   *
   * [4. THAM SỐ (PARAMETERS)]
   * @param {Object} credentials
   * @param {string} credentials.email Email hoặc Tên đăng nhập
   * @param {string} credentials.password Mật khẩu
   *
   * [5. GIÁ TRỊ TRẢ VỀ]
   * @returns {Promise<Object>} Payload đăng nhập từ backend
   */
  async login(credentials) {
    const response = await axiosClient.post(Endpoints.AUTH.LOGIN, credentials)
    return response.data
  },

  /**
   * [1. MỤC ĐÍCH]
   * Đăng ký tài khoản người dùng mới.
   *
   * [3. NGUỒN GỌI (CALLERS)]
   * - Store: `useAuthStore::register()`
   *
   * [4. THAM SỐ (PARAMETERS)]
   * @param {Object} data Thông tin đăng ký (name, email, password, password_confirmation...)
   * @returns {Promise<Object>}
   */
  async register(data) {
    const response = await axiosClient.post(Endpoints.AUTH.REGISTER, data)
    return response.data
  },

  /**
   * [1. MỤC ĐÍCH]
   * Chuyển đổi tổ chức / cơ sở làm việc hiện tại của phiên đăng nhập.
   *
   * [2. LUỒNG XỬ LÝ (FLOW)]
   * 1. Gửi POST `/api/auth/switch-organization` với payload `{ organization_id }`.
   * 2. Nhận về danh sách vai trò (roles), quyền hạn (permissions) và CASL abilities mới tương ứng với tổ chức này.
   *
   * [3. NGUỒN GỌI (CALLERS)]
   * - Store: `useAuthStore::selectOrganization()`
   *
   * [4. THAM SỐ (PARAMETERS)]
   * @param {number|string} organizationId ID cơ sở / tổ chức
   * @returns {Promise<Object>}
   */
  async switchOrganization(organizationId) {
    const response = await axiosClient.post('/api/auth/switch-organization', {
      organization_id: organizationId,
    })
    return response.data
  },

  /**
   * [1. MỤC ĐÍCH]
   * Lấy lại thông tin hồ sơ và danh sách quyền hạn của user hiện tại (thường dùng khi F5/reload trang).
   *
   * [2. LUỒNG XỬ LÝ (FLOW)]
   * 1. Gửi GET `Endpoints.AUTH.ME` (tự động kèm header Authorization Bearer và X-Organization-Id qua axiosClient).
   * 2. Trả về thông tin user và abilities.
   *
   * [3. NGUỒN GỌI (CALLERS)]
   * - Store: `useAuthStore::fetchMe()`
   * - Navigation Guard: Router beforeEach khi kiểm tra phiên đăng nhập
   *
   * [5. GIÁ TRỊ TRẢ VỀ]
   * @returns {Promise<Object>}
   */
  async getMe() {
    const response = await axiosClient.get(Endpoints.AUTH.ME)
    return response.data
  },

  /**
   * [1. MỤC ĐÍCH]
   * Gửi yêu cầu đăng xuất tới máy chủ để thu hồi Access Token.
   *
   * [2. LUỒNG XỬ LÝ (FLOW)]
   * 1. Gửi POST `Endpoints.AUTH.LOGOUT`.
   * 2. Bắt lỗi ngầm (catch) để tránh gián đoạn tiến trình đăng xuất phía client nếu mạng gặp sự cố.
   *
   * [3. NGUỒN GỌI (CALLERS)]
   * - Store: `useAuthStore::logout()`
   */
  async logout() {
    try {
      await axiosClient.post(Endpoints.AUTH.LOGOUT)
    } catch {
      // Bỏ qua lỗi server khi logout để client luôn xóa sạch session cục bộ
    }
  },
}
