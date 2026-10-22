import { defineStore } from 'pinia'
import { equipmentService } from '../services/equipmentService'
import { toast } from '@/core/helpers/toast'

function extractEquipment(response) {
  const value = response?.data?.data || response?.data || response
  return value && typeof value === 'object' && !Array.isArray(value) && value.id ? value : null
}

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
      const created = extractEquipment(res)
      if (!created) {
        await this.fetchEquipments(this.pagination.currentPage, { silent: true })
        return res
      }

      this.addEquipment(created)
      return res
    },

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

    async deleteEquipment(id) {
      await equipmentService.deleteEquipment(id)
      toast.success('Đã xóa thiết bị thành công.')
      await this.removeEquipment(id)
    },

    async changeStatus(id, status, note = '') {
      const res = await equipmentService.changeStatus(id, status, note)
      toast.success('Cập nhật trạng thái thành công!')
      const updated = extractEquipment(res)
      this.patchEquipment(id, updated || { status, note })
      await this.refreshEmptyPage()
    },

    async bulkUpdateStatus(ids, status, note = '') {
      const res = await equipmentService.bulkUpdateStatus(ids, status, note)
      toast.success(res.message || 'Cập nhật trạng thái hàng loạt thành công!')
      ids.forEach(id => this.patchEquipment(id, { status, note }))
      await this.refreshEmptyPage()
    },

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

    async assignRoom(payload) {
      const res = await equipmentService.assignToRoom(payload)
      toast.success(res.message || 'Điều chuyển thiết bị thành công!')
      this.patchEquipment(payload.equipment_id, extractEquipment(res))
      await this.refreshEmptyPage()
    },

    async reportBroken(id, payload) {
      const res = await equipmentService.reportBroken(id, payload)
      toast.success('Đã tiếp nhận báo hỏng và tạo phiếu bảo trì!')
      this.patchEquipment(id, extractEquipment(res) || { status: 'broken' })
      await this.refreshEmptyPage()
    },

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
