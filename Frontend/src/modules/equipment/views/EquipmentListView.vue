<script setup>
import { ref, onMounted, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useEquipmentStore } from '../stores/equipmentStore'
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
import EquipmentFormModal from '../components/EquipmentFormModal.vue'
import AssignRoomModal from '../components/AssignRoomModal.vue'
import ReportBrokenModal from '../components/ReportBrokenModal.vue'
import ManageEquipmentModelsModal from '../components/ManageEquipmentModelsModal.vue'

const router = useRouter()
const route = useRoute()
const equipmentStore = useEquipmentStore()
const realtimeStore = useRealtimeStore()

// Modals state
const formModalOpen = ref(false)
const manageModelsModalOpen = ref(false)
const selectedEquipmentForEdit = ref(null)

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
  { key: 'room', title: 'Vị trí / Phòng' },
  { key: 'status', title: 'Trạng thái', width: '150px' },
  { key: 'installed_at', title: 'Ngày lắp đặt', width: '130px' },
  { key: 'actions', title: 'Thao tác', align: 'end', width: '130px' },
]

onMounted(() => {
  equipmentStore.fetchEquipments(1)
  if (route.query.action === 'create') {
    openCreateModal()
  }
})

// Cập nhật bảng dữ liệu thiết bị theo thời gian thực (In-place & Reload)
watch(
  () => realtimeStore.lastEvent,
  (event) => {
    if (!event) return

    if (event.type === 'created' || event.type === 'deleted') {
      equipmentStore.fetchEquipments(equipmentStore.pagination.currentPage)
    } else if (event.type === 'status_changed') {
      const target = equipmentStore.items.find(i => i.id === event.equipment?.id)
      if (target) {
        target.status = event.equipment?.new_status
      }
    } else if (event.type === 'assigned') {
      const target = equipmentStore.items.find(i => i.id === event.equipment?.id)
      if (target) {
        target.room_id = event.equipment?.to_room_id
        target.room = event.equipment?.to_room_number ? { id: event.equipment.to_room_id, room_number: event.equipment.to_room_number } : null
        target.position_note = event.equipment?.position_note
        target.status = event.equipment?.status
      }
    } else if (event.type === 'broken_reported') {
      const target = equipmentStore.items.find(i => i.id === event.equipment?.id)
      if (target) {
        target.status = 'broken'
      }
    }
  }
)

function openCreateModal() {
  selectedEquipmentForEdit.value = null
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

async function handleFormSubmit({ isEdit, id, data }) {
  actionLoading.value = true
  try {
    if (isEdit) {
      await equipmentStore.updateEquipment(id, data)
    } else {
      await equipmentStore.createEquipment(data)
    }
    formModalOpen.value = false
  } finally {
    actionLoading.value = false
  }
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

    <!-- Filter & Search Bar -->
    <div class="bg-white rounded-2xl p-4 border border-slate-200/80 shadow-xs mb-6">
      <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3">
        <!-- Search input -->
        <div>
          <v-text-field
            v-model="equipmentStore.filters.search"
            placeholder="Tìm theo Serial, tên thiết bị..."
            prepend-inner-icon="mdi-magnify"
            clearable
            hide-details
            density="compact"
            @update:model-value="equipmentStore.fetchEquipments(1)"
          />
        </div>

        <!-- Status filter -->
        <div>
          <v-select
            v-model="equipmentStore.filters.status"
            :items="[{ value: '', title: 'Tất cả trạng thái' }, ...EquipmentStatusOptions]"
            item-title="title"
            item-value="value"
            placeholder="Lọc theo trạng thái"
            hide-details
            density="compact"
            @update:model-value="equipmentStore.fetchEquipments(1)"
          />
        </div>

        <!-- Room filter -->
        <div>
          <v-text-field
            v-model.number="equipmentStore.filters.room_id"
            type="number"
            placeholder="Lọc theo ID phòng (vd: 101)"
            prepend-inner-icon="mdi-door"
            clearable
            hide-details
            density="compact"
            @update:model-value="equipmentStore.fetchEquipments(1)"
          />
        </div>

        <!-- Reset Button -->
        <div class="flex items-center gap-2">
          <v-btn
            variant="tonal"
            color="secondary"
            block
            height="40"
            @click="equipmentStore.resetFilters"
          >
            <v-icon icon="mdi-refresh" start size="small" />
            Đặt lại bộ lọc
          </v-btn>
        </div>
      </div>
    </div>

    <!-- Data Table -->
    <AppDataTable
      :headers="headers"
      :items="equipmentStore.items"
      :loading="equipmentStore.loading"
      :total-items="equipmentStore.pagination.total"
      :page="equipmentStore.pagination.currentPage"
      :items-per-page="equipmentStore.pagination.perPage"
      @update:page="p => equipmentStore.fetchEquipments(p)"
      @click:row="item => router.push(`/equipments/${item.id}`)"
    >
      <!-- Custom item: ID -->
      <template #item.id="{ item }">
        <span class="font-mono text-xs font-bold text-slate-500">#{{ item.id }}</span>
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
              :src="resolveMediaUrl(item.photo_url || item.photos?.[0]?.url)"
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
              {{ item.model?.name || item.name || 'Thiết bị không tên' }}
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

      <!-- Custom item: Room & Position -->
      <template #item.room="{ item }">
        <div v-if="item.room || item.room_id">
          <span class="inline-flex items-center gap-1 font-bold text-slate-800 text-xs">
            <v-icon icon="mdi-door" size="14" color="primary" />
            <span>Phòng {{ item.room?.room_number || item.room_id }}</span>
          </span>
          <p v-if="item.position_note" class="text-[11px] text-slate-400 mt-0.5">
            {{ item.position_note }}
          </p>
        </div>
        <div v-else>
          <span class="inline-flex items-center gap-1 text-[11px] font-medium text-sky-700 bg-sky-50 px-2 py-0.5 rounded-md border border-sky-200">
            <v-icon icon="mdi-package-variant-closed" size="12" />
            <span>Trong kho</span>
          </span>
        </div>
      </template>

      <!-- Custom item: Status -->
      <template #item.status="{ item }">
        <AppStatusBadge :status="item.status" />
      </template>

      <!-- Custom item: Installed At -->
      <template #item.installed_at="{ item }">
        <span class="text-xs text-slate-500">{{ formatDate(item.installed_at) }}</span>
      </template>

      <!-- Custom item: Actions -->
      <template #item.actions="{ item }">
        <div class="flex items-center justify-end gap-1" @click.stop>
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
                class="w-full flex items-center gap-2.5 px-3 py-2 rounded-lg text-slate-700 hover:bg-slate-50 transition text-left"
                @click="router.push(`/equipments/${item.id}`)"
              >
                <v-icon icon="mdi-eye-outline" size="16" color="primary" />
                <span>Xem chi tiết</span>
              </button>

              <button
                v-if="$can('update', 'Equipment')"
                class="w-full flex items-center gap-2.5 px-3 py-2 rounded-lg text-slate-700 hover:bg-slate-50 transition text-left"
                @click="openEditModal(item)"
              >
                <v-icon icon="mdi-pencil-outline" size="16" color="secondary" />
                <span>Chỉnh sửa</span>
              </button>

              <button
                v-if="$can('assign', 'Equipment')"
                class="w-full flex items-center gap-2.5 px-3 py-2 rounded-lg text-slate-700 hover:bg-slate-50 transition text-left"
                @click="openAssignModal(item)"
              >
                <v-icon icon="mdi-transfer" size="16" color="info" />
                <span>Điều chuyển phòng</span>
              </button>

              <button
                v-if="$can('report_broken', 'Equipment')"
                class="w-full flex items-center gap-2.5 px-3 py-2 rounded-lg text-amber-700 hover:bg-amber-50 transition text-left"
                @click="openReportBrokenModal(item)"
              >
                <v-icon icon="mdi-wrench-outline" size="16" color="warning" />
                <span>Báo hỏng</span>
              </button>

              <div class="my-1 border-t border-slate-100" />

              <button
                v-if="$can('delete', 'Equipment')"
                class="w-full flex items-center gap-2.5 px-3 py-2 rounded-lg text-rose-600 hover:bg-rose-50 transition text-left font-semibold"
                @click="openDeleteDialog(item)"
              >
                <v-icon icon="mdi-delete-outline" size="16" color="error" />
                <span>Xóa thiết bị</span>
              </button>
            </div>
          </v-menu>
        </div>
      </template>
    </AppDataTable>

    <!-- Modals -->
    <EquipmentFormModal
      v-model="formModalOpen"
      :equipment="selectedEquipmentForEdit"
      :loading="actionLoading"
      @submit="handleFormSubmit"
    />

    <AssignRoomModal
      v-model="assignModalOpen"
      :equipment="selectedEquipmentForAssign"
      :loading="actionLoading"
      @submit="handleAssignSubmit"
    />

    <ReportBrokenModal
      v-model="reportBrokenModalOpen"
      :equipment="selectedEquipmentForReport"
      :loading="actionLoading"
      @submit="handleReportBrokenSubmit"
    />

    <AppConfirmDialog
      v-model="deleteDialogOpen"
      title="Xóa thiết bị này?"
      :message="`Bạn có chắc chắn muốn xóa thiết bị #${equipmentToDelete?.id} (${equipmentToDelete?.serial_number || 'Serial trống'}) không? Dữ liệu không thể khôi phục.`"
      confirm-text="Xóa ngay"
      confirm-color="error"
      :loading="actionLoading"
      @confirm="confirmDelete"
    />

    <!-- Image Preview Lightbox Dialog -->
    <v-dialog v-model="lightboxOpen" max-width="700">
      <div class="bg-slate-900 rounded-2xl overflow-hidden p-4 relative">
        <button
          type="button"
          class="absolute top-3 right-3 z-10 w-8 h-8 rounded-full bg-white/20 hover:bg-white/40 text-white flex items-center justify-center transition-colors"
          @click="lightboxOpen = false"
        >
          <v-icon icon="mdi-close" size="18" />
        </button>
        <div class="text-center mb-3 pr-8">
          <p class="text-sm font-bold text-white">{{ lightboxTitle }}</p>
          <p class="text-xs text-slate-400 font-mono mt-0.5">{{ lightboxSubtitle }}</p>
        </div>
        <div class="flex items-center justify-center max-h-[70vh]">
          <img :src="lightboxImage" alt="Preview" class="max-w-full max-h-[65vh] object-contain rounded-lg" />
        </div>
      </div>
    </v-dialog>
    <!-- Manage Models Modal -->
    <ManageEquipmentModelsModal v-model="manageModelsModalOpen" />
  </DefaultLayout>
</template>
