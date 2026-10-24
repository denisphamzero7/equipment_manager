import axios from 'axios'
import { storage, StorageKeys } from '@/core/helpers/storage'
import { toast } from '@/core/helpers/toast'

const axiosClient = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || '',
  headers: {
    Accept: 'application/json',
  },
  timeout: 30000,
})

// Request Interceptor
axiosClient.interceptors.request.use(
  config => {
    // Gắn Bearer Token nếu có
    const token = storage.get(StorageKeys.TOKEN)
    if (token) {
      config.headers.Authorization = `Bearer ${token}`
    }

    // Gắn X-Organization-Id (mặc định 22 hoặc tổ chức đang chọn)
    const orgId = storage.get(StorageKeys.ORGANIZATION_ID, 22)
    if (orgId) {
      config.headers['X-Organization-Id'] = orgId
    }

    return config
  },
  error => Promise.reject(error)
)

// Response Interceptor
axiosClient.interceptors.response.use(
  response => response,
  error => {
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
        toast.error('Lỗi máy chủ nội bộ. Vui lòng thử lại sau.')
      } else {
        toast.error(message)
      }
    } else {
      toast.error('Không thể kết nối đến máy chủ. Vui lòng kiểm tra mạng hoặc backend!')
    }

    return Promise.reject(error)
  }
)

export default axiosClient
