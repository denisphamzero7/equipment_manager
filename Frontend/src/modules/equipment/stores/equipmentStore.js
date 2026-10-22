import { defineStore } from 'pinia'
import { equipmentService } from '../services/equipmentService'
import { toast } from '@/core/helpers/toast'

export const useEquipmentStore = defineStore('equipment', {
  state: () => ({
    items: [],
    pagination: {
      currentPage: 1,
      lastPage: 1,
      perPage: 15,
      total: 0,
    },
    loading: false,
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
    activeItem: null,
  }),

  actions: {
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

    async createEquipment(payload) {
      const res = await equipmentService.createEquipment(payload)
      toast.success('Thêm thiết bị mới thành công!')
      await this.fetchEquipments(this.pagination.currentPage)
      return res
    },

    async updateEquipment(id, payload) {
      const res = await equipmentService.updateEquipment(id, payload)
      toast.success('Cập nhật thiết bị thành công!')

      const updated = res?.data?.data || res?.data || res
      const itemIndex = this.items.findIndex(item => Number(item.id) === Number(id))
      if (itemIndex !== -1 && updated && typeof updated === 'object') {
        const current = this.items[itemIndex]
        const merged = { ...current, ...updated }
        if (!updated.photo_url && current.photo_url) merged.photo_url = current.photo_url
        if (!updated.thumb_url && current.thumb_url) merged.thumb_url = current.thumb_url
        if (!updated.photos?.length && current.photos?.length) merged.photos = current.photos
        if (!updated.broken_photos?.length && current.broken_photos?.length) {
          merged.broken_photos = current.broken_photos
        }
        this.items.splice(itemIndex, 1, merged)
      }

      const filters = this.filters
      const hasActiveFilters = Boolean(
        filters.search ||
        filters.status ||
        filters.block ||
        (filters.floor !== null && filters.floor !== '' && filters.floor !== undefined) ||
        filters.room_id ||
        filters.room_number ||
        filters.equipment_category_id
      )
      const hasNewPhoto = payload instanceof FormData && payload.has('photo')
      if (itemIndex === -1 || hasActiveFilters || hasNewPhoto) {
        try {
          await this.fetchEquipments(this.pagination.currentPage, { silent: true })
        } catch (error) {
          console.error('Equipment updated, but refreshing the list failed:', error)
          toast.warning('Đã lưu thay đổi nhưng danh sách chưa đồng bộ. Vui lòng làm mới trang.')
        }
      }
      return res
    },

    async deleteEquipment(id) {
      await equipmentService.deleteEquipment(id)
      toast.success('Đã xóa thiết bị thành công.')
      await this.fetchEquipments(this.pagination.currentPage)
    },

    async changeStatus(id, status, note = '') {
      await equipmentService.changeStatus(id, status, note)
      toast.success('Cập nhật trạng thái thành công!')
      await this.fetchEquipments(this.pagination.currentPage)
    },

    async bulkUpdateStatus(ids, status, note = '') {
      const res = await equipmentService.bulkUpdateStatus(ids, status, note)
      toast.success(res.message || 'Cập nhật trạng thái hàng loạt thành công!')
      await this.fetchEquipments(this.pagination.currentPage)
    },

    async bulkDestroy(ids) {
      const res = await equipmentService.bulkDestroy(ids)
      toast.success(res.message || 'Xóa các thiết bị đã chọn thành công!')
      await this.fetchEquipments(this.pagination.currentPage)
    },

    async assignRoom(payload) {
      const res = await equipmentService.assignToRoom(payload)
      toast.success(res.message || 'Điều chuyển thiết bị thành công!')
      await this.fetchEquipments(this.pagination.currentPage)
    },

    async reportBroken(id, payload) {
      const res = await equipmentService.reportBroken(id, payload)
      toast.success('Đã tiếp nhận báo hỏng và tạo phiếu bảo trì!')
      await this.fetchEquipments(this.pagination.currentPage)
    },

    setFilter(key, value) {
      this.filters[key] = value
    },

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
