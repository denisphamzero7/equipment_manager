import { storage, StorageKeys } from '@/core/helpers/storage'

// Lưu trữ giá trị cache và thời điểm hết hạn (RAM cache)
const values = new Map()
// Lưu trữ các Promise request đang chạy để chống gọi trùng (Request deduplication)
const requests = new Map()
// Lưu số thế hệ (generation) của từng namespace để quản lý vòng đời invalidate
const generations = new Map()

/**
 * [Mục đích]: Sinh cache key duy nhất kết hợp giữa namespace, tổ chức hiện tại và param key.
 * @param {string} namespace Không gian tên cache (ví dụ: 'equipment-list', 'room-list')
 * @param {string} key Chuỗi định danh bộ lọc hoặc ID
 * @returns {string} Key đầy đủ (ví dụ: 'equipment-list|1|{"page":1}')
 */
function cacheKey(namespace, key) {
  const organizationId = storage.get(StorageKeys.ORGANIZATION_ID) || 'global'
  return `${namespace}|${organizationId}|${key}`
}

/**
 * [1. MỤC ĐÍCH]
 * Bộ nhớ đệm request phía Client (In-memory Request Cache) kèm cơ chế chống gọi trùng (Request Deduplication):
 * - Nếu nhiều component cùng gọi 1 API với tham số giống hệt nhau cùng lúc -> chỉ gửi 1 HTTP request duy nhất lên máy chủ.
 * - Lưu kết quả trong RAM theo thời gian sống (TTL).
 *
 * [2. LUỒNG XỬ LÝ (FLOW)]
 * 1. Dọn dẹp các cache entry đã hết hạn (`expiresAt <= now`).
 * 2. Nếu không có cờ `force = true` và dữ liệu trong cache còn hạn -> Trả về kết quả ngay lập tức (0ms).
 * 3. Nếu đang có một request tương tự đang chờ kết quả từ máy chủ (`pending`) -> Tái sử dụng Promise đó mà không gửi thêm request mới.
 * 4. Gọi hàm nạp dữ liệu (`loader()`).
 * 5. Lưu kết quả vào `values` kèm hạn dùng `Date.now() + ttl`.
 * 6. Xóa request khỏi bảng `requests` khi hoàn tất.
 *
 * [3. NGUỒN GỌI (CALLERS)]
 * - `equipmentService.getEquipments()`, `roomService.getRooms()`, `equipmentService.getStats()`...
 *
 * [4. THAM SỐ (PARAMETERS)]
 * @param {string} namespace Không gian tên cache
 * @param {string} key Khóa định danh
 * @param {Function} loader Hàm async gọi API
 * @param {Object} [options]
 * @param {number} [options.ttl=30000] Thời gian sống của cache (milliseconds, mặc định 30s)
 * @param {boolean} [options.force=false] Ép buộc bỏ qua cache để gọi mới
 *
 * [5. GIÁ TRỊ TRẢ VỀ]
 * @returns {Promise<any>} Kết quả từ cache hoặc từ API
 */
export function getCachedRequest(namespace, key, loader, { ttl = 30000, force = false } = {}) {
  const fullKey = cacheKey(namespace, key)
  const now = Date.now()
  for (const [cacheEntryKey, entry] of values) {
    if (entry.expiresAt <= now) values.delete(cacheEntryKey)
  }
  const cached = values.get(fullKey)
  if (!force && cached && cached.expiresAt > now) {
    return Promise.resolve(cached.value)
  }

  const pending = requests.get(fullKey)
  if (!force && pending) return pending

  const generation = generations.get(namespace) || 0
  generations.set(namespace, generation)
  const request = Promise.resolve()
    .then(loader)
    .then(value => {
      if (
        (generations.get(namespace) || 0) === generation &&
        requests.get(fullKey) === request
      ) {
        values.set(fullKey, { value, expiresAt: Date.now() + ttl })
      }
      return value
    })
    .finally(() => {
      if (requests.get(fullKey) === request) requests.delete(fullKey)
    })

  requests.set(fullKey, request)
  return request
}

/**
 * [1. MỤC ĐÍCH]
 * Hủy bỏ cache của một hoặc nhiều namespace cụ thể (ví dụ: khi vừa thêm mới hoặc sửa thiết bị).
 * @param  {...string} namespaces Danh sách namespace cần xóa
 */
export function invalidateRequestCache(...namespaces) {
  for (const namespace of namespaces) {
    generations.set(namespace, (generations.get(namespace) || 0) + 1)
    const prefix = `${namespace}|`
    for (const key of values.keys()) {
      if (key.startsWith(prefix)) values.delete(key)
    }
    for (const key of requests.keys()) {
      if (key.startsWith(prefix)) requests.delete(key)
    }
  }
}

/**
 * [1. MỤC ĐÍCH]
 * Xóa sạch toàn bộ cache trong RAM (thường dùng khi đăng xuất hoặc chuyển đổi cơ sở khách sạn).
 */
export function clearRequestCache() {
  values.clear()
  requests.clear()
  for (const namespace of generations.keys()) {
    generations.set(namespace, (generations.get(namespace) || 0) + 1)
  }
}
