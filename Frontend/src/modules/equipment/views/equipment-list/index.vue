<script setup>
import { ref, computed, onMounted, watch, defineAsyncComponent } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useEquipmentStore } from '../../stores/equipmentStore'
import { equipmentService } from '../../services/equipmentService'
import { useRealtimeStore } from '@/core/stores/realtimeStore'
import { EquipmentStatus, EquipmentStatusOptions } from '@/core/enums/EquipmentStatus'
import { formatDate, resolveMediaUrl } from '@/core/helpers/formatters'
import { toast } from '@/core/helpers/toast'
import DefaultLayout from '@/core/layouts/DefaultLayout.vue'
import AppPageHeader from '@/core/components/AppPageHeader.vue'
import AppDataTable from '@/core/components/AppDataTable.vue'
import AppStatusBadge from '@/core/components/AppStatusBadge.vue'
import AppButton from '@/core/components/AppButton.vue'
import AppConfirmDialog from '@/core/components/AppConfirmDialog.vue'
import AppExcelTransfer from '@/core/components/AppExcelTransfer.vue'

const EquipmentFormModal = defineAsyncComponent(() => import('../../components/EquipmentFormModal.vue'))
const AssignRoomModal = defineAsyncComponent(() => import('../../components/AssignRoomModal.vue'))
const ReportBrokenModal = defineAsyncComponent(() => import('../../components/ReportBrokenModal.vue'))
const ManageEquipmentModelsModal = defineAsyncComponent(() => import('../../components/ManageEquipmentModelsModal.vue'))

const router = useRouter()
const route = useRoute()
const equipmentStore = useEquipmentStore()
const realtimeStore = useRealtimeStore()

// Modals state
const formModalOpen = ref(false)
const formInitialData = ref(null)
const manageModelsModalOpen = ref(false)
const selectedEquipmentForEdit = ref(null)
const pendingCreations = ref([])
let creationSequence = 0
let editingPendingCreation = null
const modelCreationPromises = new Map()
const locallyCreatedRequestIds = new Map()

const tableItems = computed(() => [...pendingCreations.value, ...equipmentStore.items])
const tableTotal = computed(() => equipmentStore.pagination.total + pendingCreations.value.length)

function refreshEquipmentListAfterRealtime() {
  return equipmentStore.fetchEquipments(equipmentStore.pagination.currentPage, { silent: true })
    .catch(error => {
      console.error('Failed to refresh equipment list after a realtime event:', error)
      toast.warning('Danh sách chưa đồng bộ với thay đổi mới. Vui lòng thử tải lại.')
    })
}

function rememberLocalCreation(clientRequestId) {
  const cutoff = Date.now() - 60000
  for (const [id, createdAt] of locallyCreatedRequestIds) {
    if (createdAt < cutoff) locallyCreatedRequestIds.delete(id)
  }
  locallyCreatedRequestIds.set(clientRequestId, Date.now())
}

function extractCreatedEquipment(response) {
  const equipment = response?.data?.data || response?.data || response
  return equipment && typeof equipment === 'object' && equipment.id ? equipment : null
}

watch(formModalOpen, isOpen => {
  if (!isOpen && editingPendingCreation?.state === 'failed') {
    editingPendingCreation = null
  }
})

const assignModalOpen = ref(false)
const selectedEquipmentForAssign = ref(null)

const reportBrokenModalOpen = ref(false)
const selectedEquipmentForReport = ref(null)

const deleteDialogOpen = ref(false)
const equipmentToDelete = ref(null)
const actionLoading = ref(false)

// Lightbox state
const lightboxOpen = ref(false)
const lightboxImage = ref('')
const lightboxTitle = ref('')
const lightboxSubtitle = ref('')

// Danh mục thiết bị cho dropdown bộ lọc
const categoryOptions = ref([])

const hotelBlockOrder = [
  'Block A',
  'Block B',
  'Block C',
  'Block D',
  'Condos 1',
  'Condos 2',
  'Penthouse-spa+Villa',
]

const hasActiveFilters = computed(() => {
  const f = equipmentStore.filters
  return !!(
    f.search ||
    f.status ||
    f.block ||
    (f.floor !== null && f.floor !== '' && f.floor !== undefined) ||
    f.room_number ||
    f.equipment_category_id
  )
})

function selectBlock(b) {
  equipmentStore.filters.block = b
  equipmentStore.fetchEquipments(1)
}

let debounceTimer = null
function handleDebouncedFetch(val) {
  if (debounceTimer) clearTimeout(debounceTimer)
  if (!val) {
    equipmentStore.fetchEquipments(1)
    return
  }
  debounceTimer = setTimeout(() => {
    equipmentStore.fetchEquipments(1)
  }, 350)
}

function resetAllFilters() {
  equipmentStore.resetFilters()
}

async function loadCategories() {
  try {
    categoryOptions.value = await equipmentService.getCategories()
  } catch (error) {
    console.error('Failed to load equipment categories:', error)
    toast.error(error.response?.data?.message || 'Không thể tải danh mục thiết bị.')
  }
}

function openLightbox(item, event) {
  if (event) event.stopPropagation()
  const url = item.photo_url || item.photos?.[0]?.url
  if (!url) return
  lightboxImage.value = resolveMediaUrl(url)
  lightboxTitle.value = item.model?.name || item.name || 'Hình ảnh thiết bị'
  lightboxSubtitle.value = `Mã Serial: ${item.serial_number || 'Chưa gắn mã'} • ID #${item.id}`
  lightboxOpen.value = true
}

const headers = [
  { key: 'id', title: 'ID', width: '70px' },
  { key: 'equipment_info', title: 'Thiết bị & Serial' },
  { key: 'category', title: 'Danh mục' },
  { key: 'room', title: 'Vị trí / Phân khu & Phòng' },
  { key: 'status', title: 'Trạng thái', width: '150px' },
  { key: 'installed_at', title: 'Ngày lắp đặt', width: '130px' },
  { key: 'actions', title: 'Thao tác', align: 'end', width: '130px' },
]

onMounted(async () => {
  // Đồng bộ tham số URL (khi click từ Dashboard hoặc Map View qua)
  if (route.query.status) equipmentStore.filters.status = route.query.status
  if (route.query.block) equipmentStore.filters.block = route.query.block
  if (route.query.category_id) equipmentStore.filters.equipment_category_id = Number(route.query.category_id)
  if (route.query.room_number) equipmentStore.filters.room_number = route.query.room_number
  if (route.query.floor) equipmentStore.filters.floor = Number(route.query.floor)

  await Promise.all([
    equipmentStore.fetchEquipments(1),
    loadCategories(),
  ])

  if (route.query.action === 'create') {
    openCreateModal()
  }
})

// Cập nhật bảng dữ liệu thiết bị theo thời gian thực (In-place & Reload)
watch(
  () => realtimeStore.lastEvent,
  (event) => {
    if (!event) return

    if (event.type === 'created') {
      const clientRequestId = event.equipment?.client_request_id
      if (clientRequestId && locallyCreatedRequestIds.delete(clientRequestId)) return

      const pending = pendingCreations.value.find(
        item => item.clientRequestId === clientRequestId
      )
      if (pending) {
        pending.acknowledgedByEvent = true
        pending.successNotified = true
        pendingCreations.value = pendingCreations.value.filter(item => item !== pending)
        equipmentStore.addEquipment(event.equipment)
        if (pending.state === 'failed') void refreshAfterCreation(pending)
      } else if (event.equipment?.id) {
        void equipmentService.getEquipment(event.equipment?.id)
          .then(equipmentStore.addEquipment)
          .catch(error => {
            console.error('Failed to load a newly created equipment item:', error)
            toast.warning('Có thiết bị mới nhưng chưa tải được thông tin chi tiết.')
          })
      } else {
        void refreshEquipmentListAfterRealtime()
      }
    } else if (event.type === 'deleted') {
      equipmentStore.removeEquipment(event.equipment_id)
    } else if (event.type === 'updated') {
      const equipment = event.equipment
      const current = equipmentStore.items.find(item => Number(item.id) === Number(equipment?.id))
      if (current && equipment) {
        const hasGeographicFilter = Boolean(
          (equipmentStore.filters.block && equipmentStore.filters.block !== '__stock__') ||
          (equipmentStore.filters.floor !== null && equipmentStore.filters.floor !== '')
        )
        const roomChanged = Number(equipment.room_id ?? 0) !== Number(current.room_id ?? 0)
        if (hasGeographicFilter && roomChanged) {
          void refreshEquipmentListAfterRealtime()
          return
        }
        equipmentStore.patchEquipment(equipment.id, {
          serial_number: equipment.serial_number,
          status: equipment.status,
          room_id: equipment.room_id,
          room: equipment.room_id
            ? { ...current.room, id: equipment.room_id, room_number: equipment.room_number }
            : null,
          position_note: equipment.position_note,
          note: equipment.note,
          model: { ...current.model, name: equipment.model_name },
        })
      }
    } else if (event.type === 'status_changed') {
      equipmentStore.patchEquipment(event.equipment?.id, { status: event.equipment?.new_status })
    } else if (event.type === 'assigned') {
      const current = equipmentStore.items.find(item => Number(item.id) === Number(event.equipment?.id))
      if (
        current &&
        ((equipmentStore.filters.block && equipmentStore.filters.block !== '__stock__') ||
          (equipmentStore.filters.floor !== null && equipmentStore.filters.floor !== ''))
      ) {
        void refreshEquipmentListAfterRealtime()
        return
      }
      equipmentStore.patchEquipment(event.equipment?.id, {
        room_id: event.equipment?.to_room_id,
        room: event.equipment?.to_room_id
          ? { id: event.equipment.to_room_id, room_number: event.equipment.to_room_number }
          : null,
        position_note: event.equipment?.position_note,
        status: event.equipment?.status,
      })
    } else if (event.type === 'broken_reported') {
      equipmentStore.patchEquipment(event.equipment?.id, { status: 'broken' })
    } else if (event.type === 'imported') {
      void refreshEquipmentListAfterRealtime()
    }
  }
)

function openCreateModal() {
  selectedEquipmentForEdit.value = null
  formInitialData.value = null
  formModalOpen.value = true
}

function openEditModal(item) {
  selectedEquipmentForEdit.value = item
  formModalOpen.value = true
}

function openAssignModal(item) {
  selectedEquipmentForAssign.value = item
  assignModalOpen.value = true
}

function openReportBrokenModal(item) {
  selectedEquipmentForReport.value = item
  reportBrokenModalOpen.value = true
}

function openDeleteDialog(item) {
  equipmentToDelete.value = item
  deleteDialogOpen.value = true
}

async function handleFormSubmit({ isEdit, id, data, modelDraft, continueAdding, formValues, modelName }) {
  if (isEdit) {
    actionLoading.value = true
    try {
      let payload = data instanceof FormData ? data : { ...data }
      if (modelDraft) {
        const createdModel = await equipmentService.createModel(modelDraft)
        if (payload instanceof FormData) {
          payload.set('equipment_model_id', createdModel.id)
        } else {
          payload.equipment_model_id = createdModel.id
        }
      }
      await equipmentStore.updateEquipment(id, payload)
      formModalOpen.value = false
    } finally {
      actionLoading.value = false
    }
    return
  }

  if (editingPendingCreation) {
    pendingCreations.value = pendingCreations.value.filter(item => item !== editingPendingCreation)
    editingPendingCreation = null
  }

  const pending = {
    id: `pending-${++creationSequence}`,
    clientRequestId: `equipment-create-${Date.now()}-${creationSequence}`,
    isOptimistic: true,
    state: 'saving',
    modelName: modelName || 'Thiết bị mới',
    category: modelDraft
      ? categoryOptions.value.find(category => category.id === modelDraft.equipment_category_id) || null
      : null,
    serial_number: formValues.serial_number,
    room_id: formValues.room_id,
    position_note: formValues.position_note,
    installed_at: formValues.installed_at,
    formValues,
    photoFile: data instanceof FormData ? data.get('photo') : null,
    data,
    modelDraft,
    modelId: typeof formValues.equipment_model_id === 'number' ? formValues.equipment_model_id : null,
    error: '',
  }
  pendingCreations.value.unshift(pending)
  toast.info(`Đang tạo thiết bị "${pending.modelName}"...`, 2500)
  void persistCreation(pending)

  if (!continueAdding) {
    formModalOpen.value = false
    selectedEquipmentForEdit.value = null
  }
}

async function persistCreation(pending) {
  pending.state = 'saving'
  pending.error = ''
  try {
    if (pending.modelDraft && !pending.modelId) {
      const key = `${pending.modelDraft.equipment_category_id}:${pending.modelDraft.name.trim().toLocaleLowerCase()}`
      if (!modelCreationPromises.has(key)) {
        const creation = equipmentService.createModel(pending.modelDraft).catch(error => {
          modelCreationPromises.delete(key)
          throw error
        })
        modelCreationPromises.set(key, creation)
      }
      const createdModel = await modelCreationPromises.get(key)
      pending.modelId = createdModel.id
      pending.modelDraft = null
    }

    const payload = pending.data instanceof FormData
      ? pending.data
      : { ...pending.data }
    if (payload instanceof FormData) {
      payload.set('equipment_model_id', pending.modelId)
      payload.set('client_request_id', pending.clientRequestId)
    } else if (pending.modelId) {
      payload.equipment_model_id = pending.modelId
    }
    if (!(payload instanceof FormData)) {
      payload.client_request_id = pending.clientRequestId
    }

    const response = await equipmentService.createEquipment(payload)
    const created = extractCreatedEquipment(response)
    if (created) {
      equipmentStore.addEquipment(created)
      if (!pending.acknowledgedByEvent) rememberLocalCreation(pending.clientRequestId)
    }
    pendingCreations.value = pendingCreations.value.filter(item => item !== pending)
    pending.acknowledgedByResponse = true
    if (!pending.successNotified) {
      toast.success(`Đã tạo thiết bị "${pending.modelName}" thành công!`)
    }
    if (!created && !pending.acknowledgedByEvent) {
      await refreshAfterCreation(pending)
    }
  } catch (error) {
    if (pending.acknowledgedByEvent || pending.acknowledgedByResponse) {
      await refreshAfterCreation(pending)
      return
    }
    pending.state = 'failed'
    pending.error = error.response?.data?.message || error.message || 'Không thể lưu thiết bị.'
    toast.error(`Tạo thiết bị thất bại: ${pending.error}`)
  }
}

async function refreshAfterCreation(pending) {
  try {
    await equipmentStore.fetchEquipments(equipmentStore.pagination.currentPage, { silent: true })
  } catch (error) {
    console.error('Equipment created, but refreshing the list failed:', error)
    toast.warning('Thiết bị đã được tạo nhưng danh sách chưa tải lại. Vui lòng làm mới trang.')
  }
}

function retryCreation(pending) {
  void persistCreation(pending)
}

function reopenFailedCreation(pending) {
  editingPendingCreation = pending
  selectedEquipmentForEdit.value = null
  formInitialData.value = {
    ...pending.formValues,
    equipment_model_id: pending.modelId || pending.modelDraft?.name || pending.formValues.equipment_model_id,
    photoFile: pending.photoFile,
  }
  formModalOpen.value = true
}

function handleRowClick(item) {
  if (!item.isOptimistic) router.push(`/equipments/${item.id}`)
}

async function handleAssignSubmit(payload) {
  actionLoading.value = true
  try {
    await equipmentStore.assignRoom(payload)
    assignModalOpen.value = false
  } finally {
    actionLoading.value = false
  }
}

async function handleReportBrokenSubmit({ id, formData }) {
  actionLoading.value = true
  try {
    await equipmentStore.reportBroken(id, formData)
    reportBrokenModalOpen.value = false
  } finally {
    actionLoading.value = false
  }
}

async function confirmDelete() {
  if (!equipmentToDelete.value) return
  actionLoading.value = true
  try {
    await equipmentStore.deleteEquipment(equipmentToDelete.value.id)
    deleteDialogOpen.value = false
  } finally {
    actionLoading.value = false
  }
}

async function handleQuickStatusChange(item, newStatus) {
  await equipmentStore.changeStatus(item.id, newStatus)
}
</script>

<template>
  <DefaultLayout>
    <AppPageHeader
      title="Danh sách Trang thiết bị"
      subtitle="Quản lý chi tiết từng cá thể thiết bị, mã Serial, vị trí phòng và trạng thái vận hành"
      :breadcrumbs="[
        { title: 'Trang chủ', to: '/dashboard' },
        { title: 'Trang thiết bị' },
      ]"
    >
      <template #actions>
        <AppExcelTransfer
          v-if="$can('import', 'Equipment') || $can('export', 'Equipment')"
          title="Thiết bị"
          description="Nhập thiết bị theo mẫu hoặc xuất danh sách để kiểm kê."
          :exporter="equipmentService.exportExcel"
          :importer="equipmentService.importExcel"
          :filters="equipmentStore.filters"
          :allow-import="$can('import', 'Equipment')"
          :allow-export="$can('export', 'Equipment')"
          @imported="equipmentStore.fetchEquipments(1)"
        />
        <AppButton
          v-if="$can('create', 'Equipment')"
          variant="tonal"
          color="secondary"
          icon="mdi-format-list-bulleted-type"
          @click="manageModelsModalOpen = true"
        >
          Quản lý tên thiết bị
        </AppButton>
        <AppButton
          v-if="$can('create', 'Equipment')"
          color="primary"
          icon="mdi-plus"
          @click="openCreateModal"
        >
          Thêm thiết bị mới
        </AppButton>
      </template>
    </AppPageHeader>

    <!-- 1. Modern Block Quick-Filter Pills Bar (Đồng bộ với Dashboard, RoomListView & EquipmentMapView) -->
    <div class="bg-white border border-slate-200/80 rounded-2xl shadow-xs overflow-hidden mb-4">
      <div class="px-4 py-2.5 bg-slate-50/60 border-b border-slate-100 flex items-center justify-between gap-2">
        <span class="text-xs font-bold text-slate-500 uppercase tracking-wider flex items-center gap-1.5">
          <v-icon icon="mdi-domain" size="15" color="primary" />
          <span>Lọc Nhanh Theo Phân Khu & Vị Trí:</span>
        </span>
        <button
          v-if="equipmentStore.filters.block"
          type="button"
          class="text-xs text-primary font-semibold hover:underline flex items-center gap-1 cursor-pointer"
          @click="selectBlock('')"
        >
          <v-icon icon="mdi-eye-outline" size="14" />
          <span>Hiển thị tất cả vị trí</span>
        </button>
      </div>

      <!-- Horizontal Pills Scroll -->
      <div class="p-3 flex items-center gap-2 overflow-x-auto scrollbar-thin">
        <button
          type="button"
          class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all duration-150 shrink-0 flex items-center gap-1.5 border cursor-pointer"
          :class="!equipmentStore.filters.block
            ? 'bg-primary text-white border-primary shadow-xs'
            : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-100 hover:border-slate-300'"
          @click="selectBlock('')"
        >
          <span>Tất cả vị trí</span>
        </button>

        <button
          v-for="b in hotelBlockOrder"
          :key="b"
          type="button"
          class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all duration-150 shrink-0 flex items-center gap-1.5 border cursor-pointer"
          :class="equipmentStore.filters.block === b
            ? 'bg-primary text-white border-primary shadow-xs'
            : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-100 hover:border-slate-300'"
          @click="selectBlock(b)"
        >
          <v-icon icon="mdi-domain" size="13" />
          <span>{{ b }}</span>
        </button>

        <!-- Pill: Thiết bị đang trong kho -->
        <button
          type="button"
          class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all duration-150 shrink-0 flex items-center gap-1.5 border cursor-pointer ml-auto"
          :class="equipmentStore.filters.block === '__stock__'
            ? 'bg-sky-600 text-white border-sky-600 shadow-xs'
            : 'bg-sky-50 text-sky-700 border-sky-200 hover:bg-sky-100'"
          @click="selectBlock('__stock__')"
        >
          <v-icon icon="mdi-package-variant-closed" size="14" />
          <span>Trong kho</span>
        </button>
      </div>
    </div>

    <!-- 2. Detailed Filter Row (Search, Status, Floor, Room Number, Category) -->
    <div class="bg-white rounded-2xl p-4 border border-slate-200/80 shadow-xs mb-6">
      <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 items-center">
        <!-- 1. Search input (Spacious) -->
        <div class="lg:col-span-2">
          <v-text-field
            v-model="equipmentStore.filters.search"
            placeholder="Tìm theo Serial, tên thiết bị, phòng..."
            prepend-inner-icon="mdi-magnify"
            clearable
            hide-details
            density="compact"
            variant="outlined"
            @update:model-value="handleDebouncedFetch"
          />
        </div>

        <!-- 2. Status filter -->
        <div>
          <v-select
            v-model="equipmentStore.filters.status"
            :items="[{ value: '', title: 'Tất cả trạng thái' }, ...EquipmentStatusOptions]"
            item-title="title"
            item-value="value"
            placeholder="Trạng thái"
            hide-details
            density="compact"
            variant="outlined"
            prepend-inner-icon="mdi-shield-check-outline"
            @update:model-value="equipmentStore.fetchEquipments(1)"
          />
        </div>

        <!-- 3. Floor filter -->
        <div>
          <v-select
            v-model="equipmentStore.filters.floor"
            :items="[
              { value: null, title: 'Tất cả Tầng' },
              { value: 1, title: 'Tầng 1' },
              { value: 2, title: 'Tầng 2' },
              { value: 3, title: 'Tầng 3' },
              { value: 4, title: 'Tầng 4' },
              { value: 5, title: 'Tầng 5' },
              { value: 6, title: 'Tầng 6' },
              { value: 7, title: 'Tầng 7' },
            ]"
            item-title="title"
            item-value="value"
            placeholder="Tầng"
            hide-details
            density="compact"
            variant="outlined"
            prepend-inner-icon="mdi-stairs"
            :disabled="equipmentStore.filters.block === '__stock__'"
            @update:model-value="equipmentStore.fetchEquipments(1)"
          />
        </div>

        <!-- 4. Room Number filter (vd: 122) -->
        <div>
          <v-text-field
            v-model="equipmentStore.filters.room_number"
            placeholder="Số phòng (vd: 122)"
            prepend-inner-icon="mdi-door"
            clearable
            hide-details
            density="compact"
            variant="outlined"
            :disabled="equipmentStore.filters.block === '__stock__'"
            @update:model-value="handleDebouncedFetch"
          />
        </div>

        <!-- 5. Category filter -->
        <div>
          <v-select
            v-model="equipmentStore.filters.equipment_category_id"
            :items="[{ id: null, name: 'Tất cả danh mục' }, ...categoryOptions]"
            item-title="name"
            item-value="id"
            placeholder="Danh mục"
            hide-details
            density="compact"
            variant="outlined"
            prepend-inner-icon="mdi-tag-outline"
            @update:model-value="equipmentStore.fetchEquipments(1)"
          />
        </div>
      </div>

      <!-- Active filters tags bar & Reset button -->
      <div v-if="hasActiveFilters" class="mt-3 pt-3 border-t border-slate-100 flex items-center justify-between flex-wrap gap-2 text-xs">
        <div class="flex items-center gap-2 flex-wrap">
          <span class="text-slate-400 font-medium">Đang áp dụng bộ lọc:</span>
          <span v-if="equipmentStore.filters.block" class="px-2 py-0.5 rounded-md bg-blue-50 text-primary font-bold border border-blue-200">
            Khu vực: {{ equipmentStore.filters.block === '__stock__' ? 'Trong kho' : equipmentStore.filters.block }}
          </span>
          <span v-if="equipmentStore.filters.floor" class="px-2 py-0.5 rounded-md bg-slate-100 text-slate-700 font-bold">
            Tầng: {{ equipmentStore.filters.floor }}
          </span>
          <span v-if="equipmentStore.filters.room_number" class="px-2 py-0.5 rounded-md bg-slate-100 text-slate-700 font-bold">
            Phòng: {{ equipmentStore.filters.room_number }}
          </span>
          <span v-if="equipmentStore.filters.status" class="px-2 py-0.5 rounded-md bg-slate-100 text-slate-700 font-bold">
            Trạng thái: {{ equipmentStore.filters.status }}
          </span>
          <span v-if="equipmentStore.filters.search" class="px-2 py-0.5 rounded-md bg-slate-100 text-slate-700 font-medium">
            Từ khóa: "{{ equipmentStore.filters.search }}"
          </span>
        </div>

        <button
          type="button"
          class="px-3 py-1.5 text-xs font-semibold rounded-lg border border-slate-200 text-slate-600 bg-slate-50 hover:bg-slate-100 hover:text-slate-900 transition flex items-center gap-1.5 cursor-pointer ml-auto"
          title="Đặt lại toàn bộ bộ lọc"
          @click="resetAllFilters"
        >
          <v-icon icon="mdi-filter-off-outline" size="14" />
          <span>Đặt lại bộ lọc</span>
        </button>
      </div>
    </div>

    <!-- Data Table -->
    <AppDataTable
      :headers="headers"
      :items="tableItems"
      :loading="equipmentStore.loading"
      :total-items="tableTotal"
      :page="equipmentStore.pagination.currentPage"
      :items-per-page="equipmentStore.pagination.perPage"
      @update:page="p => equipmentStore.fetchEquipments(p)"
      @click:row="handleRowClick"
    >
      <!-- Custom item: ID -->
      <template #item.id="{ item }">
        <span v-if="item.isOptimistic" class="text-[11px] font-semibold text-slate-400">
          Đang lưu
        </span>
        <span v-else class="font-mono text-xs font-bold text-slate-500">#{{ item.id }}</span>
      </template>

      <!-- Custom item: Serial & Name -->
      <template #item.equipment_info="{ item }">
        <div class="flex items-center gap-3">
          <div
            class="relative w-10 h-10 rounded-lg overflow-hidden shrink-0 border border-slate-200/80 bg-white flex items-center justify-center shadow-2xs group cursor-pointer"
            :title="(item.photo_url || item.photos?.[0]?.url) ? 'Xem ảnh lớn' : (item.model?.name || 'Thiết bị')"
            @click="openLightbox(item, $event)"
          >
            <img
              v-if="item.photo_url || item.photos?.[0]?.url"
              :src="resolveMediaUrl(item.thumb_url || item.photo_url || item.photos?.[0]?.url)"
              :alt="item.model?.name || 'Thiết bị'"
              class="w-full h-full object-cover transition-transform duration-200 group-hover:scale-110"
              loading="lazy"
            />
            <v-icon
              v-else
              :icon="item.model?.category?.icon || item.category?.icon || 'mdi-devices'"
              size="20"
              class="text-slate-400"
            />
            <div
              v-if="item.photo_url || item.photos?.[0]?.url"
              class="absolute inset-0 bg-slate-900/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center text-white"
            >
              <v-icon icon="mdi-magnify-plus" size="14" />
            </div>
          </div>
          <div class="min-w-0">
            <p class="font-bold text-slate-800 text-xs truncate">
              {{ item.model?.name || item.modelName || item.name || 'Thiết bị không tên' }}
            </p>
            <p class="text-[11px] font-mono text-blue-600 font-semibold mt-0.5 truncate">
              {{ item.serial_number || '— Chưa gắn Serial —' }}
            </p>
          </div>
        </div>
      </template>

      <!-- Custom item: Category -->
      <template #item.category="{ item }">
        <span class="text-xs text-slate-600 font-medium">
          {{ item.category?.name || item.model?.category?.name || 'Khác' }}
        </span>
      </template>

      <!-- Custom item: Room & Position (Rõ ràng: Block mấy • Tầng mấy • Phòng mấy) -->
      <template #item.room="{ item }">
        <div v-if="item.room || item.room_id" class="space-y-1">
          <div class="flex items-center gap-1.5 flex-wrap">
            <!-- Block Pill -->
            <span
              v-if="item.room?.block"
              class="inline-flex items-center gap-1 text-[11px] font-bold text-primary bg-blue-50 px-2 py-0.5 rounded-md border border-blue-200"
            >
              <v-icon icon="mdi-domain" size="12" />
              <span>{{ item.room.block }}</span>
            </span>

            <!-- Floor Pill -->
            <span
              v-if="item.room?.floor !== undefined && item.room?.floor !== null"
              class="inline-flex items-center gap-1 text-[11px] font-semibold text-slate-700 bg-slate-100 px-1.5 py-0.5 rounded-md"
            >
              <v-icon icon="mdi-stairs" size="12" />
              <span>Tầng {{ item.room.floor }}</span>
            </span>

            <!-- Room Pill -->
            <span class="inline-flex items-center gap-1 font-black text-slate-900 text-xs">
              <v-icon icon="mdi-door" size="13" color="primary" />
              <span>P.{{ item.room?.room_number || item.room_id }}</span>
            </span>
          </div>

          <p
            v-if="item.position_note"
            class="text-[11px] text-slate-500 font-medium truncate max-w-[220px]"
            :title="item.position_note"
          >
            {{ item.position_note }}
          </p>
        </div>

        <!-- In Stock -->
        <div v-else class="space-y-1">
          <span class="inline-flex items-center gap-1 text-[11px] font-bold text-sky-700 bg-sky-50 px-2 py-0.5 rounded-md border border-sky-200">
            <v-icon icon="mdi-package-variant-closed" size="13" />
            <span>Trong kho</span>
          </span>
          <p
            v-if="item.position_note"
            class="text-[11px] text-slate-400 truncate max-w-[220px]"
            :title="item.position_note"
          >
            {{ item.position_note }}
          </p>
        </div>
      </template>

      <!-- Custom item: Status -->
      <template #item.status="{ item }">
        <v-chip
          v-if="item.isOptimistic"
          size="x-small"
          :color="item.state === 'failed' ? 'error' : 'info'"
          variant="tonal"
        >
          {{ item.state === 'failed' ? 'Lưu thất bại' : 'Đang lưu...' }}
        </v-chip>
        <AppStatusBadge v-else :status="item.status" />
      </template>

      <!-- Custom item: Installed At -->
      <template #item.installed_at="{ item }">
        <span class="text-xs text-slate-500">{{ formatDate(item.installed_at) }}</span>
      </template>

      <!-- Custom item: Actions -->
      <template #item.actions="{ item }">
        <div v-if="item.isOptimistic" class="flex items-center justify-end gap-1" @click.stop>
          <v-btn
            v-if="item.state === 'failed'"
            variant="text"
            density="compact"
            size="small"
            color="primary"
            @click="retryCreation(item)"
          >
            Thử lại
          </v-btn>
          <v-btn
            v-if="item.state === 'failed'"
            variant="text"
            density="compact"
            size="small"
            color="secondary"
            @click="reopenFailedCreation(item)"
          >
            Mở lại form
          </v-btn>
        </div>
        <div v-else class="flex items-center justify-end gap-1" @click.stop>
          <v-menu location="bottom end">
            <template #activator="{ props: menuProps }">
              <v-btn
                v-bind="menuProps"
                icon="mdi-dots-vertical"
                variant="text"
                density="comfortable"
                size="small"
                color="secondary"
              />
            </template>

            <div class="bg-white rounded-xl shadow-xl border border-slate-100 p-1.5 min-w-[180px] text-xs">
              <button
                class="w-full flex items-center gap-2.5 px-3 py-2 rounded-lg text-slate-700 hover:bg-slate-50 transition text-left cursor-pointer"
                @click="router.push(`/equipments/${item.id}`)"
              >
                <v-icon icon="mdi-eye-outline" size="16" color="primary" />
                <span>Xem chi tiết</span>
              </button>

              <button
                v-if="$can('update', 'Equipment')"
                class="w-full flex items-center gap-2.5 px-3 py-2 rounded-lg text-slate-700 hover:bg-slate-50 transition text-left cursor-pointer"
                @click="openEditModal(item)"
              >
                <v-icon icon="mdi-pencil-outline" size="16" />
                <span>Chỉnh sửa</span>
              </button>

              <button
                v-if="$can('assign', 'Equipment')"
                class="w-full flex items-center gap-2.5 px-3 py-2 rounded-lg text-slate-700 hover:bg-slate-50 transition text-left cursor-pointer"
                @click="openAssignModal(item)"
              >
                <v-icon icon="mdi-swap-horizontal" size="16" color="info" />
                <span>Điều chuyển phòng</span>
              </button>

              <button
                v-if="$can('report_broken', 'Equipment')"
                class="w-full flex items-center gap-2.5 px-3 py-2 rounded-lg text-rose-600 hover:bg-rose-50 transition text-left cursor-pointer"
                @click="openReportBrokenModal(item)"
              >
                <v-icon icon="mdi-alert-circle-outline" size="16" color="error" />
                <span>Báo hỏng</span>
              </button>

              <div class="my-1 border-t border-slate-100" />

              <button
                v-if="$can('delete', 'Equipment')"
                class="w-full flex items-center gap-2.5 px-3 py-2 rounded-lg text-rose-600 hover:bg-rose-50 transition text-left cursor-pointer"
                @click="openDeleteDialog(item)"
              >
                <v-icon icon="mdi-trash-can-outline" size="16" color="error" />
                <span>Xóa thiết bị</span>
              </button>
            </div>
          </v-menu>
        </div>
      </template>
    </AppDataTable>

    <!-- Modals -->
    <EquipmentFormModal
      v-if="formModalOpen"
      v-model="formModalOpen"
      :equipment="selectedEquipmentForEdit"
      :initial-data="formInitialData"
      :loading="actionLoading"
      @submit="handleFormSubmit"
    />

    <AssignRoomModal
      v-if="assignModalOpen"
      v-model="assignModalOpen"
      :equipment="selectedEquipmentForAssign"
      :loading="actionLoading"
      @submit="handleAssignSubmit"
    />

    <ReportBrokenModal
      v-if="reportBrokenModalOpen"
      v-model="reportBrokenModalOpen"
      :equipment="selectedEquipmentForReport"
      :loading="actionLoading"
      @submit="handleReportBrokenSubmit"
    />

    <ManageEquipmentModelsModal
      v-if="manageModelsModalOpen"
      v-model="manageModelsModalOpen"
    />

    <!-- Lightbox Modal -->
    <v-dialog v-model="lightboxOpen" max-width="700">
      <div class="bg-slate-900 rounded-2xl overflow-hidden p-4 relative">
        <button
          type="button"
          class="absolute top-3 right-3 z-10 w-8 h-8 rounded-full bg-slate-800/80 text-white flex items-center justify-center hover:bg-slate-700 transition cursor-pointer"
          @click="lightboxOpen = false"
        >
          <v-icon icon="mdi-close" size="18" />
        </button>
        <p v-if="lightboxTitle" class="text-xs font-bold text-slate-200 mb-1 px-1 truncate">
          {{ lightboxTitle }}
        </p>
        <p v-if="lightboxSubtitle" class="text-[11px] text-slate-400 mb-3 px-1 truncate font-mono">
          {{ lightboxSubtitle }}
        </p>
        <div class="flex items-center justify-center max-h-[75vh] overflow-hidden rounded-xl bg-black/40">
          <img
            :src="lightboxImage"
            :alt="lightboxTitle"
            class="max-w-full max-h-[75vh] object-contain rounded-xl"
          />
        </div>
      </div>
    </v-dialog>

    <!-- Delete Confirm Dialog -->
    <AppConfirmDialog
      v-model="deleteDialogOpen"
      title="Xác nhận xóa thiết bị"
      :message="`Bạn có chắc chắn muốn xóa thiết bị '${equipmentToDelete?.model?.name || equipmentToDelete?.name}' (Serial: ${equipmentToDelete?.serial_number}) không? Hành động này không thể hoàn tác.`"
      confirm-text="Xác nhận xóa"
      confirm-color="error"
      :loading="actionLoading"
      @confirm="confirmDelete"
    />
  </DefaultLayout>
</template>
