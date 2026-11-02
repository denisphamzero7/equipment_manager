import { defineStore } from 'pinia'
import { equipmentService } from '../services/equipmentService'
import { toast } from '@/core/helpers/toast'

/**
 * [Mục đích]: Trích xuất thực thể Equipment (Object) từ phản hồi đa dạng cấu trúc của API.
 * [Luồng]: Kiểm tra `response?.data?.data` -> `response?.data` -> `response`, nếu có trường `id` thì trả về Object hợp lệ.
 * @param {Object} response Đối tượng phản hồi từ API
 * @returns {Object|null} Thực thể thiết bị hoặc null
 */
function extractEquipment(response) {
  const value = response?.data?.data || response?.data || response
  return value && typeof value === 'object' && !Array.isArray(value) && value.id ? value : null
}

/**
 * [Mục đích]: Kiểm tra một cá thể thiết bị có thỏa mãn các tiêu chí lọc hiện tại của Store hay không.
 * [Luồng]:
 *   1. So khớp trạng thái (`status`).
 *   2. So khớp block (`block === '__stock__'` cho kho, hoặc tên block tòa nhà).
 *   3. So khớp tầng (`floor`), phòng (`room_id`, `room_number`), danh mục (`equipment_category_id`).
 *   4. Tìm kiếm từ khóa trên các trường: serial, tên model, số phòng.
 * @param {Object} item Thiết bị cần kiểm tra
 * @param {Object} filters Bộ lọc hiện tại của Store
 * @returns {boolean} True nếu khớp toàn bộ bộ lọc
 */
function matchesFilters(item, filters) {
  if (filters.status && item.status !== filters.status) return false
  if (filters.block === '__stock__' && item.room_id != null) return false
  if (filters.block && filters.block !== '__stock__' && item.room?.block !== filters.block) return false
  if (filters.floor != null && filters.floor !== '' && Number(item.room?.floor) !== Number(filters.floor)) return false
  if (filters.room_id && Number(item.room_id) !== Number(filters.room_id)) return false
  if (filters.room_number && String(item.room?.room_number || '') !== String(filters.room_number)) return false

  const categoryId = item.equipment_category_id || item.model?.equipment_category_id || item.model?.category_id || item.model?.category?.id
  if (filters.equipment_category_id && Number(categoryId) !== Number(filters.equipment_category_id)) return false

  const query = String(filters.search || '').trim().toLowerCase()
  if (query) {
    const searchableValues = [
      item.serial_number,
      item.name,
      item.model_name,
      item.model?.name,
      item.room?.room_number,
    ]
    if (!searchableValues.some(value => String(value || '').toLowerCase().includes(query))) return false
  }

  return true
}

export const useEquipmentStore = defineStore('equipment', {
  state: () => ({
    // Danh sách thiết bị hiển thị trên bảng
    items: [],
    // Cấu hình phân trang
    pagination: {
      currentPage: 1,
      lastPage: 1,
      perPage: 15,
      total: 0,
    },
    // Trạng thái đang tải dữ liệu
    loading: false,
    // Bộ lọc dữ liệu hiện tại
    filters: {
      search: '',
      status: '',
      block: '',
      floor: null,
      room_id: null,
      room_number: '',
      equipment_category_id: null,
      sort_by: 'id',
      sort_order: 'desc',
    },
    // Thiết bị đang được chọn xem chi tiết
    activeItem: null,
  }),

  actions: {
    /**
     * [1. MỤC ĐÍCH]
     * Tải danh sách thiết bị từ API theo trang và bộ lọc hiện tại, cập nhật state của Store.
     *
     * [2. LUỒNG XỬ LÝ (FLOW)]
     * 1. Bật cờ `loading = true` (trừ khi bật chế độ `silent = true` chạy nền).
     * 2. Xóa các filter rỗng khỏi mảng tham số gọi API.
     * 3. Gọi `equipmentService.getEquipments(params)`.
     * 4. Trích xuất mảng danh sách `this.items` và cập nhật các thông số phân trang (`pagination`).
     * 5. Tắt `loading = false`.
     *
     * [3. NGUỒN GỌI (CALLERS)]
     * - View: `Frontend/src/modules/equipment/views/equipment-list/index.vue` (khi mounted, đổi trang, đổi filter)
     *
     * [4. THAM SỐ (PARAMETERS)]
     * @param {number} [page=1] Số thứ tự trang cần tải
     * @param {Object} [options]
     * @param {boolean} [options.silent=false] Nếu true sẽ không bật icon xoay loading (dùng cho polling ngầm)
     */
    async fetchEquipments(page = 1, { silent = false } = {}) {
      if (!silent) this.loading = true
      try {
        const params = {
          page,
          per_page: this.pagination.perPage,
          limit: this.pagination.perPage,
          ...this.filters,
        }
        // Remove empty filters
        Object.keys(params).forEach(k => {
          if (params[k] === '' || params[k] === null || params[k] === undefined) {
            delete params[k]
          }
        })

        const res = await equipmentService.getEquipments(params)

        // 1. Trích xuất danh sách items
        if (Array.isArray(res.data)) {
          this.items = res.data
        } else if (Array.isArray(res.data?.data)) {
          this.items = res.data.data
        } else if (Array.isArray(res)) {
          this.items = res
        } else {
          this.items = []
        }

        // 2. Trích xuất thông tin phân trang (hỗ trợ chuẩn ResourceCollection res.meta và LengthAwarePaginator res.total)
        const meta = res.meta || res.data?.meta || res
        this.pagination.currentPage = meta.current_page || res.current_page || res.data?.current_page || page
        this.pagination.lastPage = meta.last_page || res.last_page || res.data?.last_page || 1
        this.pagination.perPage = meta.per_page || res.per_page || res.data?.per_page || this.pagination.perPage
        this.pagination.total = meta.total ?? res.total ?? res.data?.total ?? this.items.length

        return res
      } finally {
        if (!silent) this.loading = false
      }
    },

    /**
     * [1. MỤC ĐÍCH]
     * Lấy thông tin chi tiết một thiết bị và gán vào `activeItem`.
     *
     * [3. NGUỒN GỌI (CALLERS)]
     * - View: `Frontend/src/modules/equipment/views/equipment-detail/index.vue`
     *
     * [4. THAM SỐ (PARAMETERS)]
     * @param {number|string} id ID thiết bị
     */
    async fetchEquipment(id) {
      this.loading = true
      try {
        const data = await equipmentService.getEquipment(id)
        this.activeItem = data
        return data
      } finally {
        this.loading = false
      }
    },

    /**
     * [1. MỤC ĐÍCH]
     * Tạo mới thiết bị qua API và tự động thêm vào đầu danh sách nếu thỏa mãn bộ lọc hiện tại.
     *
     * [2. LUỒNG XỬ LÝ (FLOW)]
     * 1. Gọi `equipmentService.createEquipment(payload)`.
     * 2. Hiển thị thông báo Toast thành công.
     * 3. Trích xuất thiết bị vừa tạo và gọi `this.addEquipment(created)` để cập nhật UI ngay lập tức không cần tải lại trang.
     *
     * [3. NGUỒN GỌI (CALLERS)]
     * - Modal: `EquipmentFormModal.vue` khi submit form thêm mới
     *
     * [4. THAM SỐ (PARAMETERS)]
     * @param {FormData|Object} payload Dữ liệu form tạo thiết bị
     */
    async createEquipment(payload) {
      const res = await equipmentService.createEquipment(payload)
      toast.success('Thêm thiết bị mới thành công!')
      const created = extractEquipment(res)
      if (!created) {
        await this.fetchEquipments(this.pagination.currentPage, { silent: true })
        return res
      }

      this.addEquipment(created)
      return res
    },

    /**
     * [1. MỤC ĐÍCH]
     * Thêm một thiết bị mới vào danh sách hiển thị hiện tại nếu khớp filter.
     *
     * [2. LUỒNG XỬ LÝ (FLOW)]
     * 1. Nếu thiết bị đã có trong danh sách -> cập nhật lại thông tin.
     * 2. Nếu thiết bị không khớp với bộ lọc hiện tại -> bỏ qua không thêm vào view.
     * 3. Tăng `total += 1`, chèn vào đầu mảng `items.unshift()`, nếu vượt quá số dòng/trang thì pop phần tử cuối.
     */
    addEquipment(equipment) {
      if (!equipment?.id) return
      const existingIndex = this.items.findIndex(item => Number(item.id) === Number(equipment.id))
      if (existingIndex !== -1) {
        this.items.splice(existingIndex, 1, { ...this.items[existingIndex], ...equipment })
        return
      }
      if (!matchesFilters(equipment, this.filters)) return

      this.pagination.total += 1
      if (this.pagination.currentPage === 1) {
        this.items.unshift(equipment)
        if (this.items.length > this.pagination.perPage) this.items.pop()
      }
    },

    /**
     * [1. MỤC ĐÍCH]
     * Cập nhật thông tin thiết bị và đồng bộ state cục bộ mà không cần reload trang.
     *
     * [2. LUỒNG XỬ LÝ (FLOW)]
     * 1. Gọi API `equipmentService.updateEquipment(id, payload)`.
     * 2. Giữ nguyên URL ảnh cũ nếu API không trả về ảnh mới.
     * 3. Gọi `this.patchEquipment(id, changes)` để sửa trực tiếp trong mảng `items`.
     * 4. Kiểm tra nếu trang hiện tại bị trống -> tự động lùi về trang trước.
     *
     * [3. NGUỒN GỌI (CALLERS)]
     * - Modal: `EquipmentFormModal.vue` khi submit form chỉnh sửa
     */
    async updateEquipment(id, payload) {
      const res = await equipmentService.updateEquipment(id, payload)
      toast.success('Cập nhật thiết bị thành công!')

      const updated = extractEquipment(res)
      const itemIndex = this.items.findIndex(item => Number(item.id) === Number(id))
      if (itemIndex !== -1 && updated) {
        const current = this.items[itemIndex]
        const changes = { ...updated }
        if (!updated.photo_url && current.photo_url) changes.photo_url = current.photo_url
        if (!updated.thumb_url && current.thumb_url) changes.thumb_url = current.thumb_url
        if (!updated.photos?.length && current.photos?.length) changes.photos = current.photos
        if (!updated.broken_photos?.length && current.broken_photos?.length) {
          changes.broken_photos = current.broken_photos
        }
        this.patchEquipment(id, changes)
        await this.refreshEmptyPage()
      }

      if (itemIndex === -1 || !updated) {
        try {
          await this.fetchEquipments(this.pagination.currentPage, { silent: true })
        } catch (error) {
          console.error('Equipment updated, but refreshing the list failed:', error)
          toast.warning('Đã lưu thay đổi nhưng danh sách chưa đồng bộ. Vui lòng làm mới trang.')
        }
      }
      return res
    },

    /**
     * [1. MỤC ĐÍCH]
     * Xóa 1 thiết bị và loại bỏ khỏi mảng hiển thị.
     *
     * [3. NGUỒN GỌI (CALLERS)]
     * - Nút Xóa tại bảng thiết bị hoặc trang chi tiết
     */
    async deleteEquipment(id) {
      await equipmentService.deleteEquipment(id)
      toast.success('Đã xóa thiết bị thành công.')
      await this.removeEquipment(id)
    },

    /**
     * [1. MỤC ĐÍCH]
     * Thay đổi nhanh trạng thái thiết bị và đồng bộ giao diện.
     */
    async changeStatus(id, status, note = '') {
      const res = await equipmentService.changeStatus(id, status, note)
      toast.success('Cập nhật trạng thái thành công!')
      const updated = extractEquipment(res)
      this.patchEquipment(id, updated || { status, note })
      await this.refreshEmptyPage()
    },

    /**
     * [1. MỤC ĐÍCH]
     * Cập nhật trạng thái hàng loạt cho nhiều thiết bị được chọn.
     */
    async bulkUpdateStatus(ids, status, note = '') {
      const res = await equipmentService.bulkUpdateStatus(ids, status, note)
      toast.success(res.message || 'Cập nhật trạng thái hàng loạt thành công!')
      ids.forEach(id => this.patchEquipment(id, { status, note }))
      await this.refreshEmptyPage()
    },

    /**
     * [1. MỤC ĐÍCH]
     * Xóa hàng loạt nhiều thiết bị cùng lúc và cập nhật tổng số lượng phân trang.
     */
    async bulkDestroy(ids) {
      const res = await equipmentService.bulkDestroy(ids)
      toast.success(res.message || 'Xóa các thiết bị đã chọn thành công!')
      const idSet = new Set(ids.map(Number))
      const previousCount = this.items.length
      this.items = this.items.filter(item => !idSet.has(Number(item.id)))
      const removedCount = previousCount - this.items.length
      this.pagination.total = Math.max(0, this.pagination.total - removedCount)
      if (this.activeItem && idSet.has(Number(this.activeItem.id))) this.activeItem = null
      if (removedCount) await this.refreshEmptyPage()
    },

    /**
     * [1. MỤC ĐÍCH]
     * Điều chuyển thiết bị vào phòng hoặc về kho và cập nhật thẻ thiết bị.
     */
    async assignRoom(payload) {
      const res = await equipmentService.assignToRoom(payload)
      toast.success(res.message || 'Điều chuyển thiết bị thành công!')
      this.patchEquipment(payload.equipment_id, extractEquipment(res))
      await this.refreshEmptyPage()
    },

    /**
     * [1. MỤC ĐÍCH]
     * Tiếp nhận báo hỏng thiết bị, đổi trạng thái sang 'broken'.
     */
    async reportBroken(id, payload) {
      const res = await equipmentService.reportBroken(id, payload)
      toast.success('Đã tiếp nhận báo hỏng và tạo phiếu bảo trì!')
      this.patchEquipment(id, extractEquipment(res) || { status: 'broken' })
      await this.refreshEmptyPage()
    },

    /**
     * [1. MỤC ĐÍCH]
     * Cập nhật trực tiếp một phần thông tin của thiết bị trong state (`items` và `activeItem`).
     * [Luồng]: Tìm thiết bị theo ID -> Merge dữ liệu mới -> Nếu không còn khớp filter nữa thì xóa khỏi view.
     */
    patchEquipment(id, changes) {
      if (!changes) return
      const itemIndex = this.items.findIndex(item => Number(item.id) === Number(id))
      if (itemIndex !== -1) {
        const updated = { ...this.items[itemIndex], ...changes }
        if (matchesFilters(updated, this.filters)) {
          this.items.splice(itemIndex, 1, updated)
        } else {
          this.items.splice(itemIndex, 1)
          this.pagination.total = Math.max(0, this.pagination.total - 1)
        }
      }
      if (Number(this.activeItem?.id) === Number(id)) {
        this.activeItem = { ...this.activeItem, ...changes }
      }
    },

    /**
     * [1. MỤC ĐÍCH]
     * Xóa 1 thiết bị khỏi mảng `items` và giảm `pagination.total`.
     */
    async removeEquipment(id) {
      const previousCount = this.items.length
      this.items = this.items.filter(item => Number(item.id) !== Number(id))
      const removed = this.items.length < previousCount
      if (removed) {
        this.pagination.total = Math.max(0, this.pagination.total - 1)
      }
      if (Number(this.activeItem?.id) === Number(id)) this.activeItem = null
      if (removed) await this.refreshEmptyPage()
    },

    /**
     * [1. MỤC ĐÍCH]
     * Tự động điều hướng và làm mới dữ liệu khi trang hiện tại không còn phần tử nào sau khi xóa.
     */
    async refreshEmptyPage() {
      if (this.items.length || this.pagination.total === 0) return
      const lastPage = Math.max(1, Math.ceil(this.pagination.total / this.pagination.perPage))
      const targetPage = Math.min(this.pagination.currentPage, lastPage)
      try {
        await this.fetchEquipments(targetPage, { silent: true })
      } catch (error) {
        console.error('Equipment changed, but the current list page could not be refreshed:', error)
        toast.warning('Đã lưu thay đổi nhưng trang danh sách chưa đồng bộ được.')
      }
    },

    /**
     * [1. MỤC ĐÍCH]
     * Gán giá trị cho một trường trong bộ lọc tìm kiếm.
     */
    setFilter(key, value) {
      this.filters[key] = value
    },

    /**
     * [1. MỤC ĐÍCH]
     * Đặt lại toàn bộ bộ lọc về mặc định và tải lại từ trang 1.
     */
    resetFilters() {
      this.filters = {
        search: '',
        status: '',
        block: '',
        floor: null,
        room_id: null,
        room_number: '',
        equipment_category_id: null,
        sort_by: 'id',
        sort_order: 'desc',
      }
      this.fetchEquipments(1)
    },
  },
})
