<script setup>
import { ref, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { roomService } from '../services/roomService'
import { equipmentService } from '@/modules/equipment/services/equipmentService'
import { useRealtimeStore } from '@/core/stores/realtimeStore'
import { toast } from '@/core/helpers/toast'
import DefaultLayout from '@/core/layouts/DefaultLayout.vue'
import AppPageHeader from '@/core/components/AppPageHeader.vue'
import AppButton from '@/core/components/AppButton.vue'
import AppStatusBadge from '@/core/components/AppStatusBadge.vue'
import AppModal from '@/core/components/AppModal.vue'
import AppConfirmDialog from '@/core/components/AppConfirmDialog.vue'
import AssignRoomModal from '@/modules/equipment/components/AssignRoomModal.vue'
import ReportBrokenModal from '@/modules/equipment/components/ReportBrokenModal.vue'
import EquipmentFormModal from '@/modules/equipment/components/EquipmentFormModal.vue'

const route = useRoute()
const router = useRouter()
const realtimeStore = useRealtimeStore()

const loading = ref(false)
const actionLoading = ref(false)
const rooms = ref([])
const selectedRoomId = ref(null)
const roomDetail = ref(null)

// Action Modals State (Reused Components)
const assignModalOpen = ref(false)
const selectedEquipmentForAssign = ref(null)

const reportBrokenModalOpen = ref(false)
const selectedEquipmentForReport = ref(null)

const retractDialogOpen = ref(false)
const equipmentToRetract = ref(null)

const createModalOpen = ref(false)

const warehouseModalOpen = ref(false)
const warehouseLoading = ref(false)
const warehouseEquipments = ref([])
const selectedWarehouseEquipmentId = ref(null)
const warehousePositionNote = ref('')

onMounted(async () => {
  await loadRooms()
})

// Tự động đối soát lại phòng khi có thiết bị thay đổi Real-time
watch(
  () => realtimeStore.lastEvent,
  async (event) => {
    if (!event) return
    if (selectedRoomId.value) {
      await loadRoomDetail(selectedRoomId.value)
    }
  }
)

async function loadRooms() {
  try {
    const data = await equipmentService.getMapView().catch(() => [])
    rooms.value = data || []
    
    // Nếu có query param room_id từ URL thì ưu tiên chọn phòng đó
    const queryRoomId = Number(route.query.room_id)
    if (queryRoomId && rooms.value.some(r => r.id === queryRoomId)) {
      selectedRoomId.value = queryRoomId
    } else if (rooms.value.length > 0) {
      selectedRoomId.value = rooms.value[0].id
    }

    if (selectedRoomId.value) {
      await loadRoomDetail(selectedRoomId.value)
    }
  } catch (e) {
    console.error('Failed to load rooms:', e)
  }
}

async function loadRoomDetail(id) {
  if (!id) return
  loading.value = true
  try {
    roomDetail.value = await roomService.getRoomEquipmentDetail(id)
  } catch (err) {
    console.error('Failed to load room detail:', err)
  } finally {
    loading.value = false
  }
}

// 1. Thao tác Điều chuyển
function openAssignModal(item) {
  selectedEquipmentForAssign.value = {
    ...item,
    room_id: selectedRoomId.value,
  }
  assignModalOpen.value = true
}

async function handleAssignSubmit(payload) {
  actionLoading.value = true
  try {
    await equipmentService.assignToRoom(payload)
    toast.success('Điều chuyển thiết bị thành công!')
    assignModalOpen.value = false
    await loadRoomDetail(selectedRoomId.value)
  } catch (err) {
    toast.error(err.response?.data?.message || err.message || 'Điều chuyển thất bại.')
  } finally {
    actionLoading.value = false
  }
}

// 2. Thao tác Báo hỏng
function openReportBrokenModal(item) {
  selectedEquipmentForReport.value = item
  reportBrokenModalOpen.value = true
}

async function handleReportBrokenSubmit({ id, formData }) {
  actionLoading.value = true
  try {
    await equipmentService.reportBroken(id, formData)
    toast.success('Báo hỏng thiết bị thành công! Đã gửi thông báo bảo trì.')
    reportBrokenModalOpen.value = false
    await loadRoomDetail(selectedRoomId.value)
  } catch (err) {
    toast.error(err.response?.data?.message || err.message || 'Báo hỏng thất bại.')
  } finally {
    actionLoading.value = false
  }
}

// 3. Thao tác Thu hồi về kho
function confirmRetract(item) {
  equipmentToRetract.value = item
  retractDialogOpen.value = true
}

async function handleRetract() {
  if (!equipmentToRetract.value) return
  actionLoading.value = true
  try {
    await equipmentService.assignToRoom({
      equipment_id: equipmentToRetract.value.id,
      room_id: null,
      position_note: 'Thu hồi về kho từ phòng ' + (roomDetail.value?.room_number || ''),
    })
    toast.success(`Đã thu hồi thiết bị #${equipmentToRetract.value.id} về kho thành công!`)
    retractDialogOpen.value = false
    equipmentToRetract.value = null
    await loadRoomDetail(selectedRoomId.value)
  } catch (err) {
    toast.error(err.response?.data?.message || err.message || 'Thu hồi thất bại.')
  } finally {
    actionLoading.value = false
  }
}

// 4. Thao tác Gán thiết bị từ kho vào phòng
async function openWarehouseModal() {
  warehouseModalOpen.value = true
  warehouseLoading.value = true
  selectedWarehouseEquipmentId.value = null
  warehousePositionNote.value = ''
  try {
    const res = await equipmentService.getEquipments({ status: 'in_stock', limit: 100 })
    warehouseEquipments.value = res.data || res || []
  } catch (err) {
    console.error('Failed to load in-stock equipments:', err)
  } finally {
    warehouseLoading.value = false
  }
}

async function handleAssignFromWarehouse() {
  if (!selectedWarehouseEquipmentId.value || !selectedRoomId.value) return
  actionLoading.value = true
  try {
    await equipmentService.assignToRoom({
      equipment_id: selectedWarehouseEquipmentId.value,
      room_id: selectedRoomId.value,
      position_note: warehousePositionNote.value || 'Gán trực tiếp vào phòng',
    })
    toast.success('Đã gán thiết bị từ kho vào phòng thành công!')
    warehouseModalOpen.value = false
    await loadRoomDetail(selectedRoomId.value)
  } catch (err) {
    toast.error(err.response?.data?.message || err.message || 'Gán thiết bị thất bại.')
  } finally {
    actionLoading.value = false
  }
}

// 5. Thao tác Tạo mới thiết bị gắn trực tiếp vào phòng
async function handleCreateSubmit({ data }) {
  actionLoading.value = true
  try {
    await equipmentService.createEquipment({
      ...data,
      room_id: selectedRoomId.value,
    })
    toast.success('Đã tạo thiết bị mới và gán vào phòng thành công!')
    createModalOpen.value = false
    await loadRoomDetail(selectedRoomId.value)
  } catch (err) {
    toast.error(err.response?.data?.message || err.message || 'Thêm thiết bị thất bại.')
  } finally {
    actionLoading.value = false
  }
}
</script>

<template>
  <DefaultLayout>
    <AppPageHeader
      title="Đối soát Định mức Thiết bị Phòng"
      subtitle="Kiểm tra mức độ đáp ứng định mức trang thiết bị tiêu chuẩn cho từng phòng khách sạn"
      :breadcrumbs="[
        { title: 'Trang chủ', to: '/dashboard' },
        { title: 'Định mức thiết bị phòng' },
      ]"
    >
      <template #actions>
        <div class="flex items-center gap-2">
          <AppButton
            v-if="$can('assign', 'Equipment')"
            variant="primary"
            @click="openWarehouseModal"
          >
            <v-icon start icon="mdi-package-down" size="18" />
            Gán thiết bị từ kho
          </AppButton>
          <AppButton
            v-if="$can('create', 'Equipment')"
            variant="secondary"
            @click="createModalOpen = true"
          >
            <v-icon start icon="mdi-plus" size="18" />
            Tạo thiết bị mới
          </AppButton>
        </div>
      </template>
    </AppPageHeader>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
      <!-- Left: Room Selector Sidebar -->
      <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-xs h-fit space-y-4">
        <div>
          <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">
            Chọn phòng đối soát
          </label>
          <v-select
            v-model="selectedRoomId"
            :items="rooms.map(r => ({ value: r.id, title: `Phòng ${r.room_number} - ${r.block || ''} Tầng ${r.floor || 1}` }))"
            item-title="title"
            item-value="value"
            density="compact"
            hide-details
            prepend-inner-icon="mdi-door"
            @update:model-value="loadRoomDetail"
          />
        </div>

        <div class="border-t border-slate-100 pt-3">
          <p class="text-xs font-semibold text-slate-500 mb-2">Danh sách phòng nhanh:</p>
          <div class="space-y-1 max-h-96 overflow-y-auto pr-1">
            <button
              v-for="room in rooms"
              :key="room.id"
              class="w-full text-left p-2.5 rounded-xl text-xs font-medium flex items-center justify-between transition cursor-pointer"
              :class="selectedRoomId === room.id ? 'bg-blue-50 text-blue-700 font-bold border border-blue-200' : 'hover:bg-slate-50 text-slate-700'"
              @click="selectedRoomId = room.id; loadRoomDetail(room.id)"
            >
              <span>Phòng {{ room.room_number }} ({{ room.block }})</span>
              <span
                class="w-2.5 h-2.5 rounded-full"
                :class="room.badge_color === 'green' ? 'bg-emerald-500' : room.badge_color === 'red' ? 'bg-rose-500' : 'bg-amber-500'"
              />
            </button>
          </div>
        </div>
      </div>

      <!-- Right: Detailed Audit Card -->
      <div class="lg:col-span-3">
        <div v-if="loading" class="h-96 bg-slate-200 rounded-2xl animate-pulse" />

        <div v-else-if="roomDetail" class="space-y-6">
          <!-- Room Header & Compliance Badge -->
          <div class="bg-white rounded-2xl p-4 sm:p-6 border border-slate-200/80 shadow-xs flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
              <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Đối soát phòng</span>
              <h2 class="text-xl sm:text-2xl font-extrabold text-slate-800">
                Phòng {{ roomDetail.room_number }} <span v-if="roomDetail.room?.block" class="text-slate-400 text-lg font-normal">({{ roomDetail.room?.block }} - Tầng {{ roomDetail.room?.floor }})</span>
              </h2>
              <p class="text-xs text-slate-500 mt-0.5">
                Loại phòng: <strong>{{ roomDetail.room_type || 'Tiêu chuẩn' }}</strong>
              </p>
            </div>

            <div class="flex items-center gap-2">
              <div
                class="px-4 py-2 rounded-xl text-xs font-bold border flex items-center gap-2 self-start sm:self-auto"
                :class="roomDetail.compliance_status === 'compliant' ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : 'bg-rose-50 text-rose-800 border-rose-200'"
              >
                <v-icon
                  :icon="roomDetail.compliance_status === 'compliant' ? 'mdi-check-decagram' : 'mdi-alert-decagram'"
                  size="20"
                />
                <span>
                  {{ roomDetail.compliance_status === 'compliant' ? 'Đạt chuẩn 100% định mức' : 'Chưa đạt định mức tiêu chuẩn' }}
                </span>
              </div>
            </div>
          </div>

          <!-- Missing items banner -->
          <div v-if="roomDetail.missing_equipments && roomDetail.missing_equipments.length" class="bg-rose-50 border border-rose-200 rounded-2xl p-4 sm:p-5 text-xs text-rose-900">
            <h4 class="font-bold uppercase tracking-wider mb-2 flex items-center gap-2">
              <v-icon icon="mdi-alert-circle" size="18" color="error" />
              <span>Cảnh báo thiết bị còn thiếu so với định mức:</span>
            </h4>
            <div class="space-y-1.5 pl-6 list-disc">
              <div v-for="(mis, i) in roomDetail.missing_equipments" :key="i">
                Thiếu <strong>{{ mis.missing_count || 1 }}</strong> thiết bị <strong>{{ mis.category_name || mis.name }}</strong> (Quy chuẩn yêu cầu: {{ mis.standard_quantity || 1 }}, hiện có: {{ mis.actual_quantity || 0 }})
              </div>
            </div>
          </div>

          <!-- Actual Equipment Table by Category -->
          <div class="bg-white rounded-2xl p-4 sm:p-6 border border-slate-200/80 shadow-xs">
            <div class="flex items-center justify-between mb-4">
              <h3 class="text-base font-bold text-slate-800">
                Danh mục Thiết bị Thực tế trong phòng
              </h3>
              <AppButton
                v-if="$can('assign', 'Equipment')"
                size="small"
                variant="secondary"
                @click="openWarehouseModal"
              >
                <v-icon start icon="mdi-plus" size="14" />
                Gán thêm thiết bị
              </AppButton>
            </div>

            <div v-if="roomDetail.categories && roomDetail.categories.length" class="space-y-4">
              <div
                v-for="cat in roomDetail.categories"
                :key="cat.category_code || cat.category_name"
                class="border border-slate-200 rounded-xl p-3.5 sm:p-4"
              >
                <div class="flex items-center justify-between mb-3">
                  <h4 class="text-xs font-bold text-slate-700 uppercase tracking-wider">
                    {{ cat.category_name }}
                  </h4>
                  <span class="text-xs text-slate-400">{{ cat.items?.length || 0 }} thiết bị</span>
                </div>

                <div class="space-y-2">
                  <div
                    v-for="item in cat.items"
                    :key="item.id"
                    class="flex flex-col sm:flex-row sm:items-center justify-between p-3 bg-slate-50 hover:bg-slate-100/70 transition rounded-xl text-xs gap-3 border border-slate-100"
                  >
                    <!-- Left: Equipment Info -->
                    <div class="min-w-0 flex-1">
                      <p class="font-bold text-slate-800 text-sm truncate">{{ item.model_name || 'Thiết bị' }}</p>
                      <p class="font-mono text-blue-600 font-semibold mt-0.5 truncate">
                        {{ item.serial_number || 'Chưa gắn Serial' }}
                      </p>
                      <p v-if="item.position_note" class="text-[11px] text-slate-400 mt-0.5 truncate">
                        Vị trí: {{ item.position_note }}
                      </p>
                    </div>

                    <!-- Right: Status & Actions Toolbar -->
                    <div class="flex items-center gap-2 self-end sm:self-center shrink-0">
                      <AppStatusBadge :status="item.status" />

                      <div class="flex items-center gap-1 pl-2 border-l border-slate-200">
                        <!-- Xem chi tiết -->
                        <button
                          class="p-1.5 rounded-lg text-slate-500 hover:text-primary hover:bg-white transition"
                          title="Xem chi tiết thiết bị"
                          @click="router.push(`/equipments/${item.id}`)"
                        >
                          <v-icon icon="mdi-eye-outline" size="16" />
                        </button>

                        <!-- Điều chuyển phòng -->
                        <button
                          v-if="$can('assign', 'Equipment')"
                          class="p-1.5 rounded-lg text-slate-500 hover:text-blue-600 hover:bg-white transition"
                          title="Điều chuyển / Chuyển phòng"
                          @click="openAssignModal(item)"
                        >
                          <v-icon icon="mdi-transfer" size="16" />
                        </button>

                        <!-- Báo hỏng -->
                        <button
                          v-if="$can('report_broken', 'Equipment')"
                          class="p-1.5 rounded-lg text-slate-500 hover:text-amber-600 hover:bg-white transition"
                          title="Báo hỏng thiết bị"
                          @click="openReportBrokenModal(item)"
                        >
                          <v-icon icon="mdi-wrench-outline" size="16" />
                        </button>

                        <!-- Thu hồi về kho -->
                        <button
                          v-if="$can('assign', 'Equipment')"
                          class="p-1.5 rounded-lg text-slate-500 hover:text-rose-600 hover:bg-rose-50 transition"
                          title="Thu hồi về kho"
                          @click="confirmRetract(item)"
                        >
                          <v-icon icon="mdi-package-down" size="16" />
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div v-else class="text-center py-12 text-slate-400 text-xs">
              <v-icon icon="mdi-door-open" size="44" class="text-slate-300 mb-2 block mx-auto" />
              Chưa có thiết bị nào được gán vào phòng này.
              <div class="mt-3">
                <AppButton variant="secondary" size="small" @click="openWarehouseModal">
                  <v-icon start icon="mdi-plus" size="14" />
                  Gán thiết bị đầu tiên
                </AppButton>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- REUSED MODAL 1: Điều chuyển phòng -->
    <AssignRoomModal
      v-model="assignModalOpen"
      :equipment="selectedEquipmentForAssign"
      :loading="actionLoading"
      @submit="handleAssignSubmit"
    />

    <!-- REUSED MODAL 2: Báo hỏng -->
    <ReportBrokenModal
      v-model="reportBrokenModalOpen"
      :equipment="selectedEquipmentForReport"
      :loading="actionLoading"
      @submit="handleReportBrokenSubmit"
    />

    <!-- REUSED MODAL 3: Tạo thiết bị mới cho phòng -->
    <EquipmentFormModal
      v-model="createModalOpen"
      :loading="actionLoading"
      @submit="handleCreateSubmit"
    />

    <!-- REUSED MODAL 4: Xác nhận thu hồi về kho -->
    <AppConfirmDialog
      v-model="retractDialogOpen"
      title="Thu hồi thiết bị về kho"
      :message="`Bạn có chắc chắn muốn thu hồi thiết bị #${equipmentToRetract?.id} (${equipmentToRetract?.serial_number || equipmentToRetract?.model_name}) về kho không? Trạng thái sẽ chuyển thành 'Trong kho' (in_stock).`"
      confirm-text="Xác nhận thu hồi"
      confirm-color="warning"
      :loading="actionLoading"
      @confirm="handleRetract"
    />

    <!-- MODAL 5: Gán thiết bị từ kho vào phòng hiện tại -->
    <AppModal
      v-model="warehouseModalOpen"
      title="Gán Thiết Bị từ Kho vào Phòng"
      :subtitle="`Phòng đích: Phòng ${roomDetail?.room_number || selectedRoomId}`"
      max-width="540"
    >
      <div v-if="warehouseLoading" class="py-8 text-center text-slate-400 text-xs">
        <v-progress-circular indeterminate color="primary" size="30" />
        <p class="mt-2">Đang tải danh sách thiết bị trong kho...</p>
      </div>

      <div v-else class="space-y-4">
        <div>
          <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
            Chọn thiết bị còn trong kho (in_stock) *
          </label>
          <v-select
            v-model="selectedWarehouseEquipmentId"
            :items="warehouseEquipments.map(e => ({
              value: e.id,
              title: `${e.serial_number || 'Chưa serial'} — ${e.model?.name || e.name} (${e.category?.name || 'Khác'})`,
            }))"
            item-title="title"
            item-value="value"
            placeholder="Tìm và chọn thiết bị trong kho..."
            density="compact"
            variant="outlined"
            hide-details
            prepend-inner-icon="mdi-package-variant-closed"
          />
          <p v-if="warehouseEquipments.length === 0" class="text-xs text-amber-600 mt-1">
            Kho hiện không còn thiết bị nào ở trạng thái sẵn sàng.
          </p>
        </div>

        <div>
          <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
            Ghi chú vị trí trong phòng
          </label>
          <v-text-field
            v-model="warehousePositionNote"
            placeholder="Ví dụ: Kệ tivi phòng ngủ, bàn làm việc..."
            density="compact"
            variant="outlined"
            hide-details
            prepend-inner-icon="mdi-map-marker-outline"
          />
        </div>
      </div>

      <template #footer>
        <AppButton variant="secondary" :disabled="actionLoading" @click="warehouseModalOpen = false">
          Hủy bỏ
        </AppButton>
        <AppButton
          variant="primary"
          :loading="actionLoading"
          :disabled="!selectedWarehouseEquipmentId"
          @click="handleAssignFromWarehouse"
        >
          <v-icon start icon="mdi-check" />
          Gán vào phòng
        </AppButton>
      </template>
    </AppModal>
  </DefaultLayout>
</template>
