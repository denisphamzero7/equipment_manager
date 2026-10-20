<script setup>
import { ref, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useEquipmentStore } from '../stores/equipmentStore'
import { EquipmentStatus, EquipmentStatusOptions } from '@/core/enums/EquipmentStatus'
import { formatDate } from '@/core/helpers/formatters'
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

const router = useRouter()
const route = useRoute()
const equipmentStore = useEquipmentStore()

// Modals state
const formModalOpen = ref(false)
const selectedEquipmentForEdit = ref(null)

const assignModalOpen = ref(false)
const selectedEquipmentForAssign = ref(null)

const reportBrokenModalOpen = ref(false)
const selectedEquipmentForReport = ref(null)

const deleteDialogOpen = ref(false)
const equipmentToDelete = ref(null)
const actionLoading = ref(false)

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
        <div>
          <p class="font-bold text-slate-800 text-xs">
            {{ item.model?.name || item.name || 'Thiết bị không tên' }}
          </p>
          <p class="text-[11px] font-mono text-blue-600 font-semibold mt-0.5">
            {{ item.serial_number || '— Chưa gắn Serial —' }}
          </p>
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
  </DefaultLayout>
</template>
