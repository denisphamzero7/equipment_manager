import axios from 'axios'
import { storage, StorageKeys } from '@/core/helpers/storage'
import { toast } from '@/core/helpers/toast'

/**
 * [1. MỤC ĐÍCH]
 * Cấu hình Axios HTTP Client trung tâm cho toàn bộ ứng dụng Frontend:
 * - Tự động đính kèm Sanctum Bearer Token và Header X-Organization-Id.
 * - Cơ chế tự động thử lại (Silent Auto-Retry) khi gặp sự cố mạng/gateway timeout.
 * - Xử lý tập trung các mã lỗi HTTP (401, 403, 422, 500, 502/503/504).
 */
const axiosClient = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || '',
  headers: {
    Accept: 'application/json',
  },
  timeout: 30000,
})

/**
 * [REQUEST INTERCEPTOR]
 * [Luồng]:
 *   1. Đọc Token xác thực từ LocalStorage (`StorageKeys.TOKEN`). Nếu có, gắn `Authorization: Bearer <token>`.
 *   2. Đọc ID cơ sở làm việc (`StorageKeys.ORGANIZATION_ID`). Nếu có, gắn `X-Organization-Id: <orgId>`.
 *   3. Trả về cấu hình `config` để tiếp tục gửi request.
 */
axiosClient.interceptors.request.use(
  config => {
    // 1. Gắn Bearer Token nếu có
    const token = storage.get(StorageKeys.TOKEN)
    if (token) {
      config.headers.Authorization = `Bearer ${token}`
    }

    // 2. Gắn X-Organization-Id của tổ chức người dùng đang làm việc
    const orgId = storage.get(StorageKeys.ORGANIZATION_ID)
    if (orgId) {
      config.headers['X-Organization-Id'] = orgId
    }

    return config
  },
  error => Promise.reject(error)
)

let lastGatewayWarning = 0

/**
 * [RESPONSE INTERCEPTOR]
 * [Luồng xử lý lỗi]:
 *   1. Cơ chế Auto-Retry: Với lỗi 502/503/504 hoặc timeout (ECONNABORTED), tự động thử lại tối đa 2 lần với khoảng trễ (delay) lũy tiến 600ms, 1200ms.
 *   2. Mã 401 (Unauthorized): Xóa session đăng nhập, nếu không phải trang login thì điều hướng về `/login` kèm thông báo hết hạn.
 *   3. Mã 403 (Forbidden): Hiển thị thông báo Toast cảnh báo không có quyền thực hiện.
 *   4. Mã 422 (Unprocessable Entity / Validation Error): Trích xuất lỗi đầu tiên từ mảng `errors` và hiển thị Toast.
 *   5. Mã 500+ (Server Error): Cảnh báo lỗi máy chủ nội bộ.
 *   6. Lỗi mất mạng: Hiển thị cảnh báo kết nối gateway/tunnel.
 */
axiosClient.interceptors.response.use(
  response => response,
  async error => {
    const config = error.config

    // Tự động thử lại (Silent Auto-Retry) tối đa 2 lần với lỗi 502/503/504 hoặc rớt kết nối
    if (config && !config._skipRetry) {
      const status = error.response?.status
      const isRetriable =
        !error.response ||
        status === 502 ||
        status === 503 ||
        status === 504 ||
        error.code === 'ECONNABORTED'

      config._retryCount = config._retryCount || 0
      const maxRetries = 2

      if (isRetriable && config._retryCount < maxRetries) {
        config._retryCount += 1
        const delay = config._retryCount * 600 // Thử lại sau 600ms, 1200ms
        await new Promise(resolve => setTimeout(resolve, delay))
        return axiosClient(config)
      }
    }

    const { response } = error

    if (response) {
      const status = response.status
      const message = response.data?.message || 'Có lỗi xảy ra, vui lòng thử lại.'

      if (status === 401) {
        // Hết hạn phiên đăng nhập hoặc thông tin đăng nhập sai
        storage.clearAuth()
        if (window.location.pathname !== '/login') {
          toast.warning('Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.')
          window.location.href = '/login'
        } else {
          toast.error(message || 'Thông tin tài khoản hoặc mật khẩu không chính xác.')
        }
      } else if (status === 403) {
        toast.error(message || 'Bạn không có quyền thực hiện hành động này.')
      } else if (status === 422) {
        // Validation errors
        const errors = response.data?.errors
        if (errors) {
          const firstError = Object.values(errors)[0]?.[0]
          toast.error(firstError || message)
        } else {
          toast.error(message)
        }
      } else if (status >= 500) {
        if (status === 502 || status === 503 || status === 504) {
          const now = Date.now()
          if (now - lastGatewayWarning > 5000) {
            lastGatewayWarning = now
            toast.warning('Đường truyền mạng tạm thời gián đoạn (Tunnel/Gateway). Đang tự động kết nối lại...')
          }
        } else {
          toast.error(message && message !== 'Server Error' ? message : 'Lỗi máy chủ nội bộ. Vui lòng thử lại sau.')
        }
      } else {
        toast.error(message)
      }
    } else {
      const now = Date.now()
      if (now - lastGatewayWarning > 5000) {
        lastGatewayWarning = now
        toast.error('Không thể kết nối đến máy chủ. Vui lòng kiểm tra mạng hoặc backend!')
      }
    }

    return Promise.reject(error)
  }
)

export default axiosClient
