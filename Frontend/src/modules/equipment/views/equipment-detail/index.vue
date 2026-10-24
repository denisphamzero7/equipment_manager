<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { equipmentService } from '../../services/equipmentService'
import { useRealtimeStore } from '@/core/stores/realtimeStore'
import { formatDate, formatCurrencyVND, resolveMediaUrl } from '@/core/helpers/formatters'
import { toast } from '@/core/helpers/toast'
import DefaultLayout from '@/core/layouts/DefaultLayout.vue'
import AppPageHeader from '@/core/components/AppPageHeader.vue'
import AppStatusBadge from '@/core/components/AppStatusBadge.vue'
import AppButton from '@/core/components/AppButton.vue'
import AppConfirmDialog from '@/core/components/AppConfirmDialog.vue'
import AssignRoomModal from '../../components/AssignRoomModal.vue'
import ReportBrokenModal from '../../components/ReportBrokenModal.vue'
import EquipmentFormModal from '../../components/EquipmentFormModal.vue'

const route = useRoute()
const router = useRouter()
const realtimeStore = useRealtimeStore()
const equipmentId = Number(route.params.id)

const loading = ref(true)
const equipment = ref(null)

// Photo management
const photoInputRef = ref(null)
const uploadingPhoto = ref(false)
const deletingPhoto = ref(false)
const selectedPhotoIndex = ref(0)
const previewDialogOpen = ref(false)
const previewImageUrl = ref('')
const deletePhotoDialogOpen = ref(false)
const photoToDelete = ref(null)
const isDraggingOver = ref(false)
const imageLoadError = ref(false)

// Modals
const editModalOpen = ref(false)
const assignModalOpen = ref(false)
const reportBrokenModalOpen = ref(false)
const deleteDialogOpen = ref(false)
const actionLoading = ref(false)

// Maintenance log update modal
const updateLogModalOpen = ref(false)
const selectedLog = ref(null)
const logForm = ref({
  status: 'pending',
  cost: 0,
  issue_description: '',
})

const maintenanceStatusOptions = [
  { value: 'pending', title: '🟡 Chờ tiếp nhận (Mới báo hỏng)' },
  { value: 'in_progress', title: '🔵 Đang sửa chữa (Kỹ thuật đang xử lý)' },
  { value: 'completed', title: '🟢 Đã khắc phục xong (Hoạt động tốt)' },
  { value: 'unfixable', title: '🔴 Không thể sửa (Đề xuất thanh lý)' },
]

function openUpdateLogModal(log) {
  selectedLog.value = log
  logForm.value = {
    status: log.status || 'pending',
    cost: log.cost || 0,
    issue_description: log.issue_description || '',
  }
  updateLogModalOpen.value = true
}

async function handleUpdateLogSubmit() {
  if (!selectedLog.value) return
  actionLoading.value = true
  try {
    await equipmentService.updateMaintenanceLog(selectedLog.value.id, logForm.value)
    
    // Cập nhật trạng thái tại chỗ (in-place) ngay lập tức không chớp giật màn hình
    if (selectedLog.value) {
      selectedLog.value.status = logForm.value.status
      selectedLog.value.cost = logForm.value.cost
      selectedLog.value.issue_description = logForm.value.issue_description
      selectedLog.value.updated_at = new Date().toISOString()
    }
    if (equipment.value) {
      if (logForm.value.status === 'completed') {
        equipment.value.status = equipment.value.room_id ? 'in_use' : 'in_stock'
      } else if (logForm.value.status === 'in_progress') {
        equipment.value.status = 'maintenance'
      } else if (logForm.value.status === 'unfixable') {
        equipment.value.status = 'broken'
      }
    }

    updateLogModalOpen.value = false
    toast.success('Cập nhật tiến độ bảo trì thành công!')
    await loadDetail({ silent: true })
  } catch (err) {
    toast.error(err.response?.data?.message || err.message || 'Cập nhật thất bại.')
  } finally {
    actionLoading.value = false
  }
}

const equipmentPhotos = computed(() => {
  if (!equipment.value) return []
  const list = []
  if (Array.isArray(equipment.value.photos) && equipment.value.photos.length > 0) {
    list.push(
      ...equipment.value.photos.map(p => ({
        ...p,
        url: resolveMediaUrl(p.url),
      }))
    )
  } else if (equipment.value.photo_url) {
    list.push({ id: null, url: resolveMediaUrl(equipment.value.photo_url), file_name: 'equipment-photo' })
  } else if (equipment.value.media?.[0]?.original_url) {
    list.push({
      id: equipment.value.media[0].id,
      url: resolveMediaUrl(equipment.value.media[0].original_url),
      file_name: equipment.value.media[0].file_name,
    })
  }
  return list
})

const currentPhoto = computed(() => {
  if (equipmentPhotos.value.length === 0) return null
  return equipmentPhotos.value[selectedPhotoIndex.value] || equipmentPhotos.value[0]
})

const brokenPhotos = computed(() => {
  if (!equipment.value) return []
  const list = Array.isArray(equipment.value.broken_photos) ? equipment.value.broken_photos : []
  return list.map(bp => ({
    ...bp,
    url: resolveMediaUrl(bp.url),
  }))
})

onMounted(async () => {
  await loadDetail()
})

async function loadDetail({ silent = false } = {}) {
  if (!silent) {
    loading.value = true
  }
  imageLoadError.value = false
  try {
    const data = await equipmentService.getEquipment(equipmentId)
    equipment.value = data
  } finally {
    if (!silent) {
      loading.value = false
    }
  }
}

// Lắng nghe sự kiện Real-time qua WebSocket Reverb để tự động nhảy số / trạng thái
watch(
  () => realtimeStore.lastEvent,
  async (event) => {
    if (!event) return
    const evEquipId = Number(event.equipment?.id || event.equipment_id || event.id)
    if (evEquipId !== equipmentId) return

    if (event.type === 'deleted') {
      toast.warning('Thiết bị này vừa bị xóa khỏi hệ thống.')
      router.push('/equipments')
      return
    }

    // Cập nhật trạng thái in-place ngay lập tức
    if (event.type === 'status_changed' && event.equipment?.new_status && equipment.value) {
      equipment.value.status = event.equipment.new_status
    } else if (event.type === 'broken_reported' && equipment.value) {
      equipment.value.status = 'broken'
    }

    // Đồng bộ chi tiết mới nhất trong nền (không chớp màn hình)
    await loadDetail({ silent: true })
  }
)

function openAssignModal() {
  assignModalOpen.value = true
}

function triggerPhotoUpload() {
  if (photoInputRef.value) {
    photoInputRef.value.value = ''
    photoInputRef.value.click()
  }
}

async function handlePhotoUpload(event) {
  const file = event.target?.files?.[0]
  if (!file) return

  if (!file.type.startsWith('image/')) {
    toast.error('Vui lòng chọn tệp định dạng hình ảnh (PNG, JPG, JPEG, WEBP).')
    return
  }

  if (file.size > 10 * 1024 * 1024) {
    toast.error('Kích thước ảnh không được vượt quá 10MB.')
    return
  }

  const formData = new FormData()
  formData.append('file', file)
  formData.append('collection', 'equipment_photos')

  uploadingPhoto.value = true
  try {
    await equipmentService.uploadPhoto(equipmentId, formData)
    toast.success('Tải ảnh thiết bị lên thành công!')
    await loadDetail({ silent: true })
    selectedPhotoIndex.value = Math.max(0, (equipment.value?.photos?.length || 1) - 1)
  } catch (err) {
    toast.error(err.response?.data?.message || err.message || 'Tải ảnh lên thất bại.')
  } finally {
    uploadingPhoto.value = false
    if (photoInputRef.value) photoInputRef.value.value = ''
  }
}

function handleDrop(event) {
  isDraggingOver.value = false
  const file = event.dataTransfer?.files?.[0]
  if (!file) return
  handlePhotoUpload({ target: { files: [file] } })
}

function openPreview(url) {
  previewImageUrl.value = url
  previewDialogOpen.value = true
}

function confirmDeletePhoto(photo) {
  photoToDelete.value = photo
  deletePhotoDialogOpen.value = true
}

async function handleDeletePhoto() {
  if (!photoToDelete.value) return
  deletingPhoto.value = true
  try {
    if (photoToDelete.value.id && photoToDelete.value.id !== 'main') {
      await equipmentService.deletePhoto(equipmentId, photoToDelete.value.id)
    }
    toast.success('Đã xóa ảnh thiết bị thành công!')
    deletePhotoDialogOpen.value = false
    selectedPhotoIndex.value = 0
    await loadDetail({ silent: true })
  } catch (err) {
    toast.error(err.response?.data?.message || err.message || 'Xóa ảnh thất bại.')
  } finally {
    deletingPhoto.value = false
    photoToDelete.value = null
  }
}

async function handleEditSubmit({ id, data }) {
  actionLoading.value = true
  try {
    await equipmentService.updateEquipment(id, data)
    editModalOpen.value = false
    toast.success('Cập nhật thông tin thiết bị thành công!')
    await loadDetail({ silent: true })
  } catch (err) {
    toast.error(err.response?.data?.message || err.message || 'Cập nhật thất bại.')
  } finally {
    actionLoading.value = false
  }
}

async function handleAssignSubmit(payload) {
  actionLoading.value = true
  try {
    await equipmentService.assignToRoom(payload)
    assignModalOpen.value = false
    toast.success('Điều chuyển thiết bị thành công!')
    await loadDetail({ silent: true })
  } catch (err) {
    toast.error(err.response?.data?.message || err.message || 'Điều chuyển thất bại.')
  } finally {
    actionLoading.value = false
  }
}

async function handleReportBrokenSubmit({ id, formData }) {
  actionLoading.value = true
  try {
    await equipmentService.reportBroken(id, formData)
    if (equipment.value) {
      equipment.value.status = 'broken'
    }
    reportBrokenModalOpen.value = false
    toast.success('Báo hỏng thiết bị thành công!')
    await loadDetail({ silent: true })
  } catch (err) {
    toast.error(err.response?.data?.message || err.message || 'Báo hỏng thất bại.')
  } finally {
    actionLoading.value = false
  }
}

async function handleDelete() {
  actionLoading.value = true
  try {
    await equipmentService.deleteEquipment(equipmentId)
    deleteDialogOpen.value = false
    toast.success('Đã xóa thiết bị!')
    router.push('/equipments')
  } catch (err) {
    toast.error(err.response?.data?.message || err.message || 'Xóa thiết bị thất bại.')
  } finally {
    actionLoading.value = false
  }
}
</script>

<template>
  <DefaultLayout>
    <div v-if="loading" class="space-y-6">
      <div class="h-20 bg-slate-200 rounded-2xl animate-pulse" />
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="md:col-span-2 h-96 bg-slate-200 rounded-2xl animate-pulse" />
        <div class="h-96 bg-slate-200 rounded-2xl animate-pulse" />
      </div>
    </div>

    <div v-else-if="equipment">
      <AppPageHeader
        :title="equipment.model?.name || equipment.name || 'Chi tiết Thiết bị'"
        :subtitle="`Mã Serial: ${equipment.serial_number || 'Chưa gắn mã'} • ID #${equipment.id}${equipment.room ? ` • ${equipment.room.block || 'Khu chính'} • Tầng ${equipment.room.floor || 1} • Phòng ${equipment.room.room_number}` : ' • Đang lưu trong kho'}`"
        :breadcrumbs="[
          { title: 'Trang chủ', to: '/dashboard' },
          { title: 'Trang thiết bị', to: '/equipments' },
          { title: `Thiết bị #${equipment.id}` },
        ]"
      >
        <template #actions>
          <AppButton
            v-if="$can('assign', 'Equipment')"
            variant="tonal"
            color="info"
            icon="mdi-transfer"
            @click="assignModalOpen = true"
          >
            Điều chuyển
          </AppButton>

          <AppButton
            v-if="$can('report_broken', 'Equipment')"
            variant="tonal"
            color="warning"
            icon="mdi-wrench"
            @click="reportBrokenModalOpen = true"
          >
            Báo hỏng
          </AppButton>

          <AppButton
            v-if="$can('update', 'Equipment')"
            variant="elevated"
            color="primary"
            icon="mdi-pencil"
            @click="editModalOpen = true"
          >
            Chỉnh sửa
          </AppButton>

          <v-btn
            v-if="$can('delete', 'Equipment')"
            icon="mdi-delete-outline"
            color="error"
            variant="tonal"
            density="comfortable"
            @click="deleteDialogOpen = true"
          />
        </template>
      </AppPageHeader>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left 2 Cols: Main Info -->
        <div class="lg:col-span-2 space-y-6">
          <!-- General Specs Card -->
          <div class="bg-white rounded-2xl p-4 sm:p-6 border border-slate-200/80 shadow-xs">
            <div class="flex items-center justify-between pb-4 border-b border-slate-100 mb-5">
              <h3 class="text-base font-bold text-slate-800">Thông tin Thông số & Định danh</h3>
              <AppStatusBadge :status="equipment.status" />
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-y-4 gap-x-6 text-xs">
              <div>
                <span class="text-slate-400 block mb-1">Mã Serial / QR:</span>
                <span class="font-mono text-sm font-bold text-slate-800">{{ equipment.serial_number || '—' }}</span>
              </div>

              <div>
                <span class="text-slate-400 block mb-1">Danh mục thiết bị:</span>
                <span class="font-semibold text-slate-800">{{ equipment.model?.category?.name || equipment.category?.name || '—' }}</span>
              </div>

              <div>
                <span class="text-slate-400 block mb-1">Mẫu thiết bị (Model):</span>
                <span class="font-semibold text-slate-800">{{ equipment.model?.name || '—' }}</span>
              </div>

              <div>
                <span class="text-slate-400 block mb-1">Thương hiệu / Hãng SX:</span>
                <span class="font-semibold text-slate-800">{{ equipment.model?.brand || '—' }}</span>
              </div>

              <!-- Location Detail Box: Block mấy, Tầng mấy, Phòng mấy -->
              <div v-if="equipment.room" class="sm:col-span-2 p-4 bg-gradient-to-r from-blue-50/80 to-indigo-50/60 border border-blue-200/90 rounded-2xl">
                <div class="flex items-center justify-between gap-2 mb-2.5">
                  <span class="text-xs font-bold text-primary uppercase tracking-wider flex items-center gap-1.5">
                    <v-icon icon="mdi-map-marker-radius" size="18" />
                    <span>Vị Trí Lắp Đặt Chi Tiết (Phân Khu & Phòng)</span>
                  </span>
                  <router-link
                    v-if="equipment.room.block && equipment.room.floor"
                    :to="{ path: '/equipments/map-view', query: { block: equipment.room.block, floor: equipment.room.floor } }"
                    class="text-xs font-bold text-primary hover:underline flex items-center gap-1"
                  >
                    <span>Xem trên Sơ đồ Map</span>
                    <v-icon icon="mdi-arrow-right" size="14" />
                  </router-link>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5">
                  <!-- Block -->
                  <div class="bg-white p-3 rounded-xl border border-blue-100 shadow-2xs">
                    <span class="text-[10px] font-bold text-slate-400 block uppercase">1. Tòa nhà / Block</span>
                    <span class="text-base font-extrabold text-primary flex items-center gap-1.5 mt-0.5">
                      <v-icon icon="mdi-domain" size="18" />
                      <span>{{ equipment.room.block || 'Khu chính' }}</span>
                    </span>
                  </div>

                  <!-- Floor -->
                  <div class="bg-white p-3 rounded-xl border border-blue-100 shadow-2xs">
                    <span class="text-[10px] font-bold text-slate-400 block uppercase">2. Tầng</span>
                    <span class="text-base font-extrabold text-slate-800 flex items-center gap-1.5 mt-0.5">
                      <v-icon icon="mdi-stairs" size="18" />
                      <span>Tầng {{ equipment.room.floor ?? '—' }}</span>
                    </span>
                  </div>

                  <!-- Room -->
                  <div class="bg-white p-3 rounded-xl border border-blue-100 shadow-2xs">
                    <span class="text-[10px] font-bold text-slate-400 block uppercase">3. Số phòng</span>
                    <span class="text-base font-black text-blue-700 flex items-center gap-1.5 mt-0.5">
                      <v-icon icon="mdi-door" size="18" />
                      <span>Phòng {{ equipment.room.room_number }}</span>
                    </span>
                  </div>
                </div>

                <div v-if="equipment.position_note" class="mt-3 pt-2.5 border-t border-blue-100 flex items-center gap-2 text-xs text-slate-600">
                  <span class="font-bold text-slate-500">Vị trí cụ thể trong phòng:</span>
                  <span class="font-bold text-slate-800">{{ equipment.position_note }}</span>
                </div>
              </div>

              <!-- In Stock Box if no room -->
              <div v-else class="sm:col-span-2 p-3.5 bg-sky-50/70 border border-sky-200 rounded-2xl flex items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                  <div class="w-10 h-10 rounded-xl bg-sky-100 text-sky-700 flex items-center justify-center">
                    <v-icon icon="mdi-package-variant-closed" size="22" />
                  </div>
                  <div>
                    <p class="text-xs font-bold text-sky-900">Thiết bị đang lưu trong kho dự phòng</p>
                    <p class="text-[11px] text-sky-700 mt-0.5">Chưa được lắp đặt vào bất kỳ Block / Tầng / Phòng nào</p>
                  </div>
                </div>
                <AppButton
                  v-if="$can('assign', 'Equipment')"
                  variant="tonal"
                  color="primary"
                  icon="mdi-swap-horizontal"
                  density="compact"
                  @click="openAssignModal"
                >
                  Điều chuyển vào phòng
                </AppButton>
              </div>

              <div>
                <span class="text-slate-400 block mb-1">Vị trí lưu kho / Vị trí phụ:</span>
                <span class="font-medium text-slate-800">{{ equipment.position_note || '—' }}</span>
              </div>

              <div>
                <span class="text-slate-400 block mb-1">Ngày lắp đặt:</span>
                <span class="font-medium text-slate-800">{{ formatDate(equipment.installed_at) }}</span>
              </div>

              <div>
                <span class="text-slate-400 block mb-1">Ngày tạo trên hệ thống:</span>
                <span class="font-medium text-slate-800">{{ formatDate(equipment.created_at) }}</span>
              </div>
            </div>

            <div v-if="equipment.note" class="mt-5 pt-4 border-t border-slate-100 text-xs">
              <span class="text-slate-400 block mb-1 font-medium">Ghi chú vận hành:</span>
              <p class="text-slate-700 bg-slate-50 p-3 rounded-xl border border-slate-100 leading-relaxed">
                {{ equipment.note }}
              </p>
            </div>
          </div>

          <!-- Maintenance Logs History Card with Timeline -->
          <div class="bg-white rounded-2xl p-4 sm:p-6 border border-slate-200/80 shadow-xs">
            <div class="flex items-center justify-between mb-5 pb-3 border-b border-slate-100">
              <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-xl bg-primary/10 text-primary flex items-center justify-center">
                  <v-icon icon="mdi-timeline-clock-outline" size="20" />
                </div>
                <div>
                  <h3 class="text-base font-bold text-slate-800">
                    Lịch sử Bảo trì & Tiến trình Xử lý
                  </h3>
                  <p class="text-[11px] text-slate-400">
                    Theo dõi toàn bộ chu trình xử lý sự cố từ lúc báo hỏng đến khi nghiệm thu
                  </p>
                </div>
              </div>
              <span class="text-xs font-semibold px-2.5 py-1 rounded-full bg-slate-100 text-slate-600">
                {{ equipment.maintenance_logs?.length || 0 }} đợt xử lý
              </span>
            </div>

            <div v-if="equipment.maintenance_logs && equipment.maintenance_logs.length" class="space-y-6">
              <div
                v-for="(log, idx) in equipment.maintenance_logs"
                :key="log.id"
                class="rounded-2xl border border-slate-200 bg-gradient-to-b from-white to-slate-50/50 p-4 sm:p-5 shadow-xs"
              >
                <!-- Log Header -->
                <div class="flex flex-wrap items-center justify-between gap-2 pb-3 mb-4 border-b border-slate-100">
                  <div class="flex items-center gap-2">
                    <span class="px-2 py-0.5 rounded text-[11px] font-bold bg-slate-200 text-slate-700">
                      #{{ equipment.maintenance_logs.length - idx }}
                    </span>
                    <span class="font-bold text-slate-800 text-sm">
                      {{ log.issue_description || 'Ghi nhận báo hỏng / bảo trì' }}
                    </span>
                  </div>

                  <div class="flex items-center gap-2">
                    <!-- Status Badge -->
                    <span
                      class="px-2.5 py-1 rounded-lg text-xs font-bold inline-flex items-center gap-1.5"
                      :class="{
                        'bg-emerald-100 text-emerald-800 border border-emerald-200': log.status === 'completed',
                        'bg-blue-100 text-blue-800 border border-blue-200': log.status === 'in_progress',
                        'bg-amber-100 text-amber-800 border border-amber-200': log.status === 'pending',
                        'bg-rose-100 text-rose-800 border border-rose-200': log.status === 'unfixable',
                      }"
                    >
                      <v-icon
                        :icon="
                          log.status === 'completed'
                            ? 'mdi-check-circle'
                            : log.status === 'in_progress'
                              ? 'mdi-wrench'
                              : log.status === 'unfixable'
                                ? 'mdi-alert-octagon'
                                : 'mdi-clock-alert-outline'
                        "
                        size="14"
                      />
                      <span>{{ log.status_label || (log.status === 'completed' ? 'Đã khắc phục xong' : 'Đang xử lý') }}</span>
                    </span>

                    <!-- Update button -->
                    <button
                      v-if="$can('update', 'Equipment')"
                      type="button"
                      class="px-2.5 py-1 rounded-lg text-xs font-semibold bg-white hover:bg-primary/10 text-slate-700 hover:text-primary border border-slate-300 transition-colors flex items-center gap-1 shadow-2xs"
                      title="Cập nhật tiến độ xử lý hoặc chi phí"
                      @click="openUpdateLogModal(log)"
                    >
                      <v-icon icon="mdi-pencil-outline" size="14" />
                      Cập nhật
                    </button>
                  </div>
                </div>

                <!-- Visual Step Timeline -->
                <div class="relative pl-6 sm:pl-8 space-y-6 before:absolute before:left-3 sm:before:left-4 before:top-2 before:bottom-3 before:w-0.5 before:bg-slate-200">
                  <!-- Step 1: Báo hỏng / Tiếp nhận -->
                  <div class="relative">
                    <!-- Dot 1 -->
                    <div class="absolute -left-6 sm:-left-8 top-0.5 w-6 h-6 rounded-full bg-amber-500 text-white flex items-center justify-center ring-4 ring-white shadow-xs">
                      <v-icon icon="mdi-flag" size="13" />
                    </div>
                    <div class="space-y-1">
                      <div class="flex flex-wrap items-center justify-between gap-1">
                        <span class="text-xs font-bold text-slate-800 uppercase tracking-wide">
                          1. Tiếp nhận sự cố & Báo hỏng
                        </span>
                        <span class="text-[11px] font-medium text-slate-400">
                          {{ formatDate(log.created_at, 'DD/MM/YYYY HH:mm') }}
                        </span>
                      </div>
                      <div class="text-xs text-slate-600 bg-white p-2.5 rounded-xl border border-slate-100 shadow-2xs">
                        <div class="flex items-center gap-2 text-slate-500">
                          <v-icon icon="mdi-account" size="14" class="text-slate-400" />
                          <span>Người ghi nhận: <strong class="text-slate-700">{{ log.reported_by_name || 'Hệ thống / Quản lý' }}</strong></span>
                        </div>
                        <div v-if="log.issue_description" class="mt-1.5 pt-1.5 border-t border-slate-50 text-slate-700 font-medium">
                          "{{ log.issue_description }}"
                        </div>
                      </div>
                    </div>
                  </div>

                  <!-- Step 2: Xử lý kỹ thuật -->
                  <div class="relative">
                    <!-- Dot 2 -->
                    <div
                      class="absolute -left-6 sm:-left-8 top-0.5 w-6 h-6 rounded-full flex items-center justify-center ring-4 ring-white shadow-xs"
                      :class="{
                        'bg-blue-600 text-white animate-pulse': log.status === 'in_progress',
                        'bg-emerald-600 text-white': log.status === 'completed',
                        'bg-slate-400 text-white': log.status === 'unfixable',
                        'bg-slate-200 text-slate-500': log.status === 'pending',
                      }"
                    >
                      <v-icon
                        :icon="log.status === 'in_progress' ? 'mdi-wrench' : (log.status === 'pending' ? 'mdi-clock-outline' : 'mdi-check')"
                        size="13"
                      />
                    </div>
                    <div class="space-y-1">
                      <div class="flex flex-wrap items-center justify-between gap-1">
                        <span
                          class="text-xs font-bold uppercase tracking-wide"
                          :class="{
                            'text-blue-700': log.status === 'in_progress',
                            'text-slate-800': log.status === 'completed' || log.status === 'unfixable',
                            'text-slate-400': log.status === 'pending',
                          }"
                        >
                          2. Xử lý kỹ thuật & Sửa chữa
                        </span>
                        <span v-if="log.status !== 'pending' && log.updated_at" class="text-[11px] font-medium text-slate-400">
                          {{ formatDate(log.updated_at, 'DD/MM/YYYY HH:mm') }}
                        </span>
                      </div>
                      <div
                        class="text-xs p-2.5 rounded-xl border shadow-2xs"
                        :class="{
                          'bg-blue-50/70 border-blue-200 text-blue-900': log.status === 'in_progress',
                          'bg-white border-slate-100 text-slate-600': log.status !== 'in_progress',
                        }"
                      >
                        <p v-if="log.status === 'pending'" class="text-slate-400 italic">
                          Chờ kỹ thuật viên tiếp nhận và khảo sát linh kiện...
                        </p>
                        <p v-else-if="log.status === 'in_progress'" class="font-semibold text-blue-800 flex items-center gap-1.5">
                          <v-icon icon="mdi-progress-wrench" size="15" class="animate-spin text-blue-600" />
                          <span>Kỹ thuật viên đang tiến hành kiểm tra, sửa chữa và thay thế linh kiện.</span>
                        </p>
                        <p v-else class="text-slate-600">
                          Đã hoàn thành khâu sửa chữa, chuyển sang bước nghiệm thu kết quả.
                        </p>
                      </div>
                    </div>
                  </div>

                  <!-- Step 3: Nghiệm thu & Hoàn tất -->
                  <div class="relative">
                    <!-- Dot 3 -->
                    <div
                      class="absolute -left-6 sm:-left-8 top-0.5 w-6 h-6 rounded-full flex items-center justify-center ring-4 ring-white shadow-xs"
                      :class="{
                        'bg-emerald-600 text-white': log.status === 'completed',
                        'bg-rose-600 text-white': log.status === 'unfixable',
                        'bg-slate-200 text-slate-400': log.status === 'pending' || log.status === 'in_progress',
                      }"
                    >
                      <v-icon
                        :icon="
                          log.status === 'completed'
                            ? 'mdi-check-decagram'
                            : log.status === 'unfixable'
                              ? 'mdi-close'
                              : 'mdi-progress-clock'
                        "
                        size="13"
                      />
                    </div>
                    <div class="space-y-1">
                      <div class="flex flex-wrap items-center justify-between gap-1">
                        <span
                          class="text-xs font-bold uppercase tracking-wide"
                          :class="{
                            'text-emerald-700': log.status === 'completed',
                            'text-rose-700': log.status === 'unfixable',
                            'text-slate-400': log.status === 'pending' || log.status === 'in_progress',
                          }"
                        >
                          3. Nghiệm thu & Hoàn tất
                        </span>
                        <span v-if="log.resolved_at || (log.status === 'completed' && log.updated_at)" class="text-[11px] font-medium text-slate-400">
                          {{ formatDate(log.resolved_at || log.updated_at, 'DD/MM/YYYY HH:mm') }}
                        </span>
                      </div>

                      <!-- Step 3 Content by status -->
                      <div
                        v-if="log.status === 'completed'"
                        class="p-3 bg-emerald-50/70 border border-emerald-200 rounded-xl text-xs space-y-1.5"
                      >
                        <div class="flex items-center gap-1.5 font-bold text-emerald-800">
                          <v-icon icon="mdi-check-circle-outline" size="16" class="text-emerald-600" />
                          <span>Đã khắc phục hoàn toàn — Thiết bị sẵn sàng vận hành</span>
                        </div>
                        <div class="flex flex-wrap items-center justify-between gap-2 text-slate-600 pt-1 border-t border-emerald-100">
                          <span v-if="log.resolved_by_name">
                            Người nghiệm thu: <strong class="text-slate-800">{{ log.resolved_by_name }}</strong>
                          </span>
                          <span>
                            Chi phí sửa chữa: <strong class="text-emerald-700 text-sm font-black">{{ formatCurrencyVND(log.cost) }}</strong>
                          </span>
                        </div>
                      </div>

                      <div
                        v-else-if="log.status === 'unfixable'"
                        class="p-3 bg-rose-50/70 border border-rose-200 rounded-xl text-xs space-y-1.5"
                      >
                        <div class="flex items-center gap-1.5 font-bold text-rose-800">
                          <v-icon icon="mdi-alert-octagon-outline" size="16" class="text-rose-600" />
                          <span>Không thể sửa chữa — Đề xuất thanh lý / thay thế mới</span>
                        </div>
                        <div class="flex flex-wrap items-center justify-between gap-2 text-slate-600 pt-1 border-t border-rose-100">
                          <span v-if="log.resolved_by_name">
                            Người kết luận: <strong class="text-slate-800">{{ log.resolved_by_name }}</strong>
                          </span>
                          <span v-if="log.cost > 0">
                            Chi phí phát sinh: <strong class="text-rose-700 font-bold">{{ formatCurrencyVND(log.cost) }}</strong>
                          </span>
                        </div>
                      </div>

                      <div
                        v-else
                        class="p-2.5 bg-slate-50 border border-dashed border-slate-200 rounded-xl text-xs text-slate-400 italic flex items-center justify-between"
                      >
                        <span>Đang chờ hoàn tất xử lý để tiến hành nghiệm thu...</span>
                        <span v-if="log.cost > 0" class="not-italic text-slate-600">
                          Dự toán chi phí: <strong class="text-slate-700">{{ formatCurrencyVND(log.cost) }}</strong>
                        </span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div v-else class="text-center py-8 text-slate-400 text-xs">
              <v-icon icon="mdi-shield-check-outline" size="36" class="text-emerald-500 mb-1 block mx-auto" />
              Thiết bị chưa có ghi nhận sự cố hỏng hóc nào. Hoạt động ổn định.
            </div>
          </div>
        </div>

        <!-- Right 1 Col: Media & Quick Actions -->
        <div class="space-y-6">
          <!-- Photo Card -->
          <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-xs">
            <!-- Header -->
            <div class="flex items-center justify-between mb-4">
              <div class="flex items-center gap-2">
                <v-icon icon="mdi-camera" size="18" class="text-primary" />
                <h4 class="text-xs font-bold text-slate-800 uppercase tracking-wider">
                  Hình ảnh Thiết bị
                </h4>
              </div>
              <span v-if="equipmentPhotos.length" class="text-[11px] font-semibold px-2 py-0.5 rounded-full bg-slate-100 text-slate-600">
                {{ equipmentPhotos.length }} ảnh
              </span>
            </div>

            <!-- Hidden File Input -->
            <input
              ref="photoInputRef"
              type="file"
              accept="image/png, image/jpeg, image/jpg, image/webp"
              class="hidden"
              @change="handlePhotoUpload"
            />

            <!-- Photo Display & Upload Container -->
            <div class="relative">
              <!-- When photos exist -->
              <div v-if="equipmentPhotos.length > 0" class="space-y-3">
                <!-- Main Preview Container -->
                <div class="relative group rounded-xl overflow-hidden border border-slate-200 bg-slate-900/5 aspect-4/3 flex items-center justify-center">
                  <img
                    v-if="!imageLoadError"
                    :src="currentPhoto.url"
                    :alt="equipment.name || 'Hình ảnh thiết bị'"
                    class="object-contain w-full h-full max-h-64 transition-transform duration-300 group-hover:scale-102"
                    @error="imageLoadError = true"
                    @load="imageLoadError = false"
                  />

                  <!-- Error Fallback -->
                  <div v-else class="text-center p-6 text-slate-400">
                    <v-icon icon="mdi-image-broken-variant" size="44" class="text-slate-300 mb-2" />
                    <p class="text-xs font-medium text-slate-600">Không thể tải hiển thị hình ảnh</p>
                    <button
                      type="button"
                      class="mt-2 text-xs text-primary font-semibold hover:underline"
                      @click="imageLoadError = false"
                    >
                      Thử tải lại ảnh
                    </button>
                  </div>

                  <!-- Loading Overlay -->
                  <div
                    v-if="uploadingPhoto || deletingPhoto"
                    class="absolute inset-0 bg-white/80 backdrop-blur-xs flex flex-col items-center justify-center gap-2 z-20"
                  >
                    <v-progress-circular indeterminate color="primary" size="32" />
                    <span class="text-xs font-medium text-slate-700">
                      {{ uploadingPhoto ? 'Đang tải ảnh lên...' : 'Đang xóa ảnh...' }}
                    </span>
                  </div>

                  <!-- Hover Action Buttons Overlay -->
                  <div class="absolute inset-0 bg-slate-950/40 opacity-0 group-hover:opacity-100 transition-opacity duration-200 flex items-center justify-center gap-2 z-10">
                    <button
                      type="button"
                      class="px-3 py-1.5 rounded-lg bg-white/90 hover:bg-white text-slate-800 text-xs font-semibold shadow-md flex items-center gap-1.5 transition-transform active:scale-95"
                      title="Xem phóng to"
                      @click="openPreview(currentPhoto.url)"
                    >
                      <v-icon icon="mdi-magnify-plus-outline" size="16" />
                      Xem lớn
                    </button>

                    <button
                      v-if="$can('update', 'Equipment')"
                      type="button"
                      class="px-3 py-1.5 rounded-lg bg-primary hover:bg-primary-dark text-white text-xs font-semibold shadow-md flex items-center gap-1.5 transition-transform active:scale-95"
                      title="Thêm ảnh mới"
                      @click="triggerPhotoUpload"
                    >
                      <v-icon icon="mdi-camera-plus-outline" size="16" />
                      Thêm ảnh
                    </button>

                    <button
                      v-if="$can('update', 'Equipment') && currentPhoto.id && currentPhoto.id !== 'main'"
                      type="button"
                      class="p-1.5 rounded-lg bg-rose-500 hover:bg-rose-600 text-white shadow-md transition-transform active:scale-95"
                      title="Xóa ảnh này"
                      @click="confirmDeletePhoto(currentPhoto)"
                    >
                      <v-icon icon="mdi-delete-outline" size="16" />
                    </button>
                  </div>
                </div>

                <!-- Thumbnails Strip (if multiple photos) -->
                <div v-if="equipmentPhotos.length > 1" class="flex items-center gap-2 overflow-x-auto pb-1">
                  <div
                    v-for="(p, idx) in equipmentPhotos"
                    :key="p.id || idx"
                    class="relative w-14 h-14 shrink-0"
                  >
                    <button
                      type="button"
                      class="w-full h-full rounded-lg overflow-hidden border-2 transition-all"
                      :class="selectedPhotoIndex === idx ? 'border-primary ring-2 ring-primary/20 shadow-xs' : 'border-slate-200 opacity-70 hover:opacity-100'"
                      :aria-label="`Chọn ảnh ${idx + 1}`"
                      @click="selectedPhotoIndex = idx; imageLoadError = false"
                    >
                      <img :src="p.url" alt="Thumbnail" class="w-full h-full object-cover" />
                    </button>
                    <button
                      v-if="$can('update', 'Equipment') && p.id && p.id !== 'main'"
                      type="button"
                      class="absolute -top-1.5 -right-1.5 z-10 w-5 h-5 rounded-full bg-rose-600 text-white border-2 border-white shadow-sm flex items-center justify-center hover:bg-rose-700 transition-colors"
                      :aria-label="`Xóa ảnh ${idx + 1}`"
                      title="Xóa ảnh"
                      @click.stop="confirmDeletePhoto(p)"
                    >
                      <v-icon icon="mdi-close" size="12" />
                    </button>
                  </div>

                  <!-- Add more thumbnail button -->
                  <button
                    v-if="$can('update', 'Equipment')"
                    type="button"
                    class="w-14 h-14 shrink-0 rounded-lg border-2 border-dashed border-slate-300 hover:border-primary hover:bg-primary/5 text-slate-400 hover:text-primary flex flex-col items-center justify-center transition-colors"
                    title="Tải thêm ảnh mới"
                    @click="triggerPhotoUpload"
                  >
                    <v-icon icon="mdi-plus" size="20" />
                  </button>
                </div>

                <!-- Quick Action Bar under photo -->
                <div v-if="$can('update', 'Equipment')" class="pt-2 flex items-center justify-between border-t border-slate-100">
                  <span class="text-[11px] text-slate-400 truncate max-w-[150px]">
                    {{ currentPhoto.file_name || 'Ảnh thiết bị' }}
                  </span>
                  <div class="flex items-center gap-2">
                    <v-btn
                      size="small"
                      variant="tonal"
                      color="primary"
                      density="comfortable"
                      prepend-icon="mdi-upload"
                      :loading="uploadingPhoto"
                      @click="triggerPhotoUpload"
                    >
                      Tải thêm ảnh
                    </v-btn>
                  </div>
                </div>
              </div>

              <!-- When NO photo exists: Interactive Drag & Drop Area -->
              <div
                v-else
                class="relative border-2 border-dashed rounded-xl p-6 text-center transition-all cursor-pointer select-none"
                :class="isDraggingOver ? 'border-primary bg-primary/5 scale-101' : 'border-slate-200 hover:border-primary/60 hover:bg-slate-50/80'"
                @dragover.prevent="isDraggingOver = true"
                @dragleave.prevent="isDraggingOver = false"
                @drop.prevent="handleDrop"
                @click="triggerPhotoUpload"
              >
                <!-- Loading State -->
                <div v-if="uploadingPhoto" class="py-6 flex flex-col items-center justify-center gap-2">
                  <v-progress-circular indeterminate color="primary" size="36" />
                  <span class="text-xs font-semibold text-slate-700">Đang tải ảnh lên hệ thống...</span>
                </div>

                <!-- Empty State Content -->
                <div v-else class="space-y-3">
                  <div class="w-14 h-14 mx-auto rounded-full bg-primary/10 text-primary flex items-center justify-center">
                    <v-icon icon="mdi-cloud-upload-outline" size="28" />
                  </div>
                  <div>
                    <p class="text-sm font-bold text-slate-800">
                      Tải ảnh thiết bị lên
                    </p>
                    <p class="text-xs text-slate-500 mt-1">
                      Kéo & thả ảnh vào đây, hoặc <span class="text-primary font-semibold underline underline-offset-2">nhấp để duyệt</span>
                    </p>
                    <p class="text-[11px] text-slate-400 mt-1">
                      Hỗ trợ PNG, JPG, JPEG, WEBP (Tối đa 10MB)
                    </p>
                  </div>

                  <div v-if="$can('update', 'Equipment')" class="pt-1">
                    <v-btn
                      color="primary"
                      size="small"
                      variant="tonal"
                      prepend-icon="mdi-camera-plus"
                      :loading="uploadingPhoto"
                      @click.stop="triggerPhotoUpload"
                    >
                      Chọn ảnh tải lên
                    </v-btn>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Broken Photos Card (if any reported) -->
          <div v-if="brokenPhotos.length > 0" class="bg-amber-50/50 rounded-2xl p-5 border border-amber-200/80 shadow-xs">
            <div class="flex items-center justify-between mb-3">
              <div class="flex items-center gap-2">
                <v-icon icon="mdi-alert-circle-outline" size="18" class="text-amber-600" />
                <h4 class="text-xs font-bold text-amber-900 uppercase tracking-wider">
                  Ảnh Hiện trường Báo hỏng
                </h4>
              </div>
              <span class="text-[11px] font-semibold px-2 py-0.5 rounded-full bg-amber-100 text-amber-800">
                {{ brokenPhotos.length }} ảnh
              </span>
            </div>
            <div class="grid grid-cols-2 gap-2">
              <div
                v-for="(bp, bIdx) in brokenPhotos"
                :key="bp.id || bIdx"
                class="rounded-lg overflow-hidden border border-amber-200 aspect-square cursor-pointer hover:opacity-90 transition-opacity bg-white"
                @click="openPreview(bp.url)"
              >
                <img :src="bp.url" alt="Broken Photo" class="w-full h-full object-cover" />
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Modals -->
    <EquipmentFormModal
      v-model="editModalOpen"
      :equipment="equipment"
      :loading="actionLoading"
      @submit="handleEditSubmit"
    />

    <AssignRoomModal
      v-model="assignModalOpen"
      :equipment="equipment"
      :loading="actionLoading"
      @submit="handleAssignSubmit"
    />

    <!-- Modal cập nhật tiến độ xử lý phiếu bảo trì -->
    <v-dialog v-model="updateLogModalOpen" max-width="520">
      <div class="bg-white rounded-2xl p-6 shadow-xl space-y-4">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
          <div class="flex items-center gap-2">
            <div class="w-8 h-8 rounded-lg bg-primary/10 text-primary flex items-center justify-center">
              <v-icon icon="mdi-wrench-cog" size="18" />
            </div>
            <h3 class="text-sm font-bold text-slate-800">
              Cập nhật Tiến độ Bảo trì & Sự cố
            </h3>
          </div>
          <button
            type="button"
            class="text-slate-400 hover:text-slate-600 p-1 rounded-lg"
            @click="updateLogModalOpen = false"
          >
            <v-icon icon="mdi-close" size="18" />
          </button>
        </div>

        <div class="space-y-4 text-xs">
          <div>
            <label class="block font-semibold text-slate-700 mb-1">Trạng thái xử lý sự cố *</label>
            <v-select
              v-model="logForm.status"
              :items="maintenanceStatusOptions"
              item-value="value"
              item-title="title"
              density="compact"
              variant="outlined"
              hide-details
            />
            <p v-if="logForm.status === 'completed'" class="text-[11px] text-emerald-600 mt-1">
              ✓ Khi chuyển sang "Đã khắc phục xong", trạng thái thiết bị sẽ tự động chuyển về "Hoạt động tốt".
            </p>
          </div>

          <div>
            <label class="block font-semibold text-slate-700 mb-1">Chi phí sửa chữa (VNĐ)</label>
            <v-text-field
              v-model.number="logForm.cost"
              type="number"
              min="0"
              step="1000"
              density="compact"
              variant="outlined"
              placeholder="0"
              hide-details
            />
          </div>

          <div>
            <label class="block font-semibold text-slate-700 mb-1">Mô tả sự cố / Ghi chú khắc phục</label>
            <v-textarea
              v-model="logForm.issue_description"
              rows="3"
              density="compact"
              variant="outlined"
              placeholder="Nhập ghi chú sửa chữa, linh kiện đã thay..."
              hide-details
            />
          </div>
        </div>

        <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
          <AppButton
            variant="tonal"
            color="secondary"
            :disabled="actionLoading"
            @click="updateLogModalOpen = false"
          >
            Hủy bỏ
          </AppButton>
          <AppButton
            variant="elevated"
            color="primary"
            :loading="actionLoading"
            @click="handleUpdateLogSubmit"
          >
            Lưu cập nhật
          </AppButton>
        </div>
      </div>
    </v-dialog>

    <ReportBrokenModal
      v-model="reportBrokenModalOpen"
      :equipment="equipment"
      :loading="actionLoading"
      @submit="handleReportBrokenSubmit"
    />

    <AppConfirmDialog
      v-model="deleteDialogOpen"
      title="Xác nhận xóa thiết bị"
      message="Bạn có chắc chắn muốn xóa thiết bị này không? Mọi dữ liệu liên quan sẽ bị loại bỏ khỏi hệ thống."
      confirm-text="Xóa vĩnh viễn"
      confirm-color="error"
      :loading="actionLoading"
      @confirm="handleDelete"
    />

    <!-- Delete Photo Dialog -->
    <AppConfirmDialog
      v-model="deletePhotoDialogOpen"
      title="Xác nhận xóa ảnh"
      message="Bạn có chắc chắn muốn xóa ảnh này khỏi thiết bị không? Hành động này không thể hoàn tác."
      confirm-text="Xóa ảnh"
      confirm-color="error"
      :loading="deletingPhoto"
      @confirm="handleDeletePhoto"
    />

    <!-- Image Preview Lightbox Dialog -->
    <v-dialog v-model="previewDialogOpen" max-width="800">
      <div class="bg-slate-900 rounded-2xl overflow-hidden p-4 relative">
        <button
          type="button"
          class="absolute top-3 right-3 z-10 w-8 h-8 rounded-full bg-white/20 hover:bg-white/40 text-white flex items-center justify-center transition-colors"
          @click="previewDialogOpen = false"
        >
          <v-icon icon="mdi-close" size="18" />
        </button>
        <div class="flex items-center justify-center max-h-[80vh]">
          <img :src="previewImageUrl" alt="Preview" class="max-w-full max-h-[75vh] object-contain rounded-lg" />
        </div>
      </div>
    </v-dialog>
  </DefaultLayout>
</template>
