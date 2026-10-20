<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { equipmentService } from '../services/equipmentService'
import { formatDate, formatCurrencyVND } from '@/core/helpers/formatters'
import DefaultLayout from '@/core/layouts/DefaultLayout.vue'
import AppPageHeader from '@/core/components/AppPageHeader.vue'
import AppStatusBadge from '@/core/components/AppStatusBadge.vue'
import AppButton from '@/core/components/AppButton.vue'
import AppConfirmDialog from '@/core/components/AppConfirmDialog.vue'
import AssignRoomModal from '../components/AssignRoomModal.vue'
import ReportBrokenModal from '../components/ReportBrokenModal.vue'
import EquipmentFormModal from '../components/EquipmentFormModal.vue'

const route = useRoute()
const router = useRouter()
const equipmentId = Number(route.params.id)

const loading = ref(true)
const equipment = ref(null)

// Modals
const editModalOpen = ref(false)
const assignModalOpen = ref(false)
const reportBrokenModalOpen = ref(false)
const deleteDialogOpen = ref(false)
const actionLoading = ref(false)

onMounted(async () => {
  await loadDetail()
})

async function loadDetail() {
  loading.value = true
  try {
    equipment.value = await equipmentService.getEquipment(equipmentId)
  } finally {
    loading.value = false
  }
}

async function handleEditSubmit({ id, data }) {
  actionLoading.value = true
  try {
    await equipmentService.updateEquipment(id, data)
    editModalOpen.value = false
    await loadDetail()
  } finally {
    actionLoading.value = false
  }
}

async function handleAssignSubmit(payload) {
  actionLoading.value = true
  try {
    await equipmentService.assignToRoom(payload)
    assignModalOpen.value = false
    await loadDetail()
  } finally {
    actionLoading.value = false
  }
}

async function handleReportBrokenSubmit({ id, formData }) {
  actionLoading.value = true
  try {
    await equipmentService.reportBroken(id, formData)
    reportBrokenModalOpen.value = false
    await loadDetail()
  } finally {
    actionLoading.value = false
  }
}

async function handleDelete() {
  actionLoading.value = true
  try {
    await equipmentService.deleteEquipment(equipmentId)
    deleteDialogOpen.value = false
    router.push('/equipments')
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
        :subtitle="`Mã Serial: ${equipment.serial_number || 'Chưa gắn mã'} • ID #${equipment.id}`"
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
          <div class="bg-white rounded-2xl p-6 border border-slate-200/80 shadow-xs">
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

              <div>
                <span class="text-slate-400 block mb-1">Phòng hiện tại:</span>
                <span v-if="equipment.room" class="font-bold text-blue-600 text-sm">
                  Phòng {{ equipment.room.room_number }} (Tầng {{ equipment.room.floor || 1 }})
                </span>
                <span v-else class="font-semibold text-sky-700 bg-sky-50 px-2 py-0.5 rounded">
                  Trong kho
                </span>
              </div>

              <div>
                <span class="text-slate-400 block mb-1">Vị trí trong phòng:</span>
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

          <!-- Maintenance Logs History Card -->
          <div class="bg-white rounded-2xl p-6 border border-slate-200/80 shadow-xs">
            <h3 class="text-base font-bold text-slate-800 mb-4 flex items-center justify-between">
              <span>Lịch sử Bảo trì & Sự cố</span>
              <span class="text-xs text-slate-400 font-normal">
                {{ equipment.maintenance_logs?.length || 0 }} lần sửa chữa
              </span>
            </h3>

            <div v-if="equipment.maintenance_logs && equipment.maintenance_logs.length" class="space-y-3">
              <div
                v-for="log in equipment.maintenance_logs"
                :key="log.id"
                class="p-4 rounded-xl border border-slate-100 bg-slate-50/60 text-xs space-y-1.5"
              >
                <div class="flex items-center justify-between">
                  <span class="font-bold text-slate-800 text-sm">
                    {{ log.issue_description || 'Báo hỏng thiết bị' }}
                  </span>
                  <span class="text-slate-400">
                    {{ formatDate(log.created_at, 'DD/MM/YYYY HH:mm') }}
                  </span>
                </div>

                <div class="flex items-center justify-between text-slate-500 pt-1">
                  <span>Chi phí sửa chữa: <strong class="text-slate-800">{{ formatCurrencyVND(log.cost) }}</strong></span>
                  <span class="px-2 py-0.5 rounded text-[11px] font-semibold" :class="log.status === 'completed' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'">
                    {{ log.status === 'completed' ? 'Đã hoàn thành' : 'Đang xử lý' }}
                  </span>
                </div>
              </div>
            </div>

            <div v-else class="text-center py-8 text-slate-400 text-xs">
              <v-icon icon="mdi-shield-check-outline" size="36" class="text-emerald-500 mb-1 block mx-auto" />
              Thiết bị chưa có ghi nhận sự cố hỏng hóc nào.
            </div>
          </div>
        </div>

        <!-- Right 1 Col: Media & Quick Actions -->
        <div class="space-y-6">
          <!-- Photo Card -->
          <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-xs text-center">
            <h4 class="text-xs font-bold text-slate-700 uppercase tracking-wider mb-3 text-left">
              Hình ảnh Thiết bị
            </h4>

            <div v-if="equipment.photo_url || equipment.media?.[0]?.original_url" class="rounded-xl overflow-hidden border border-slate-100 max-h-64 flex items-center justify-center bg-slate-50">
              <img
                :src="equipment.photo_url || equipment.media?.[0]?.original_url"
                alt="Equipment Photo"
                class="object-contain w-full h-full"
              />
            </div>

            <div v-else class="py-12 bg-slate-50 rounded-xl border border-dashed border-slate-200 text-slate-400 text-xs">
              <v-icon icon="mdi-image-outline" size="40" class="text-slate-300 mb-1" />
              <p>Chưa có hình ảnh</p>
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
  </DefaultLayout>
</template>
