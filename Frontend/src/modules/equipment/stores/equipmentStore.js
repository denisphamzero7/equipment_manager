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
    async fetchEquipments(page = 1) {
      this.loading = true
      try {
        const params = {
          page,
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
        // Data format: res.data contains array or pagination object
        if (res.data?.data) {
          this.items = res.data.data
          this.pagination.currentPage = res.data.current_page || page
          this.pagination.lastPage = res.data.last_page || 1
          this.pagination.total = res.data.total || this.items.length
        } else if (Array.isArray(res.data)) {
          this.items = res.data
          this.pagination.total = res.data.length
        } else {
          this.items = res.data || []
        }
        return res
      } finally {
        this.loading = false
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
      await this.fetchEquipments(this.pagination.currentPage)
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
