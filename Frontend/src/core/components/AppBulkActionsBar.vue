<script setup>
import { ref } from 'vue'
import AppButton from './AppButton.vue'
import AppConfirmDialog from './AppConfirmDialog.vue'

const props = defineProps({
  selectedCount: {
    type: Number,
    required: true,
  },
  itemLabel: {
    type: String,
    default: 'mục',
  },
  statusOptions: {
    type: Array,
    default: () => [], // Mảng các { value: string, title: string, color?: string, icon?: string }
  },
  canDelete: {
    type: Boolean,
    default: true,
  },
  canUpdateStatus: {
    type: Boolean,
    default: true,
  },
  loading: {
    type: Boolean,
    default: false,
  },
  deleteTitle: {
    type: String,
    default: '',
  },
  deleteMessage: {
    type: String,
    default: '',
  },
})

const emit = defineEmits(['clear', 'delete', 'update-status'])

const deleteDialogOpen = ref(false)
const statusDialogOpen = ref(false)
const selectedStatus = ref('')
const statusNote = ref('')

function openStatusDialog() {
  if (!props.statusOptions.length) return
  selectedStatus.value = props.statusOptions[0]?.value || ''
  statusNote.value = ''
  statusDialogOpen.value = true
}

function confirmStatusChange() {
  if (!selectedStatus.value) return
  emit('update-status', {
    status: selectedStatus.value,
    note: statusNote.value,
  })
  statusDialogOpen.value = false
}

function openDeleteDialog() {
  deleteDialogOpen.value = true
}

function confirmDelete() {
  emit('delete')
  deleteDialogOpen.value = false
}
</script>

<template>
  <Transition
    enter-active-class="transition duration-200 ease-out"
    enter-from-class="opacity-0 translate-y-3 sm:translate-y-0 sm:scale-95"
    enter-to-class="opacity-100 translate-y-0 sm:scale-100"
    leave-active-class="transition duration-150 ease-in"
    leave-from-class="opacity-100 translate-y-0 sm:scale-100"
    leave-to-class="opacity-0 translate-y-3 sm:translate-y-0 sm:scale-95"
  >
    <div
      v-if="selectedCount > 0"
      class="fixed bottom-4 inset-x-3.5 z-40 sm:static sm:inset-auto sm:mb-4 bg-slate-900/95 sm:bg-white text-white sm:text-slate-800 backdrop-blur-md rounded-2xl p-3 sm:px-4 sm:py-3 border border-slate-700/60 sm:border-slate-200/90 shadow-xl sm:shadow-sm flex items-center justify-between gap-3 flex-wrap"
    >
      <!-- Bên trái: Số lượng đã chọn & Nút Bỏ chọn -->
      <div class="flex items-center gap-2.5">
        <div class="flex items-center justify-center w-7 h-7 sm:w-8 sm:h-8 rounded-full bg-blue-500/20 sm:bg-blue-50 text-blue-400 sm:text-blue-600 font-bold text-xs sm:text-sm">
          {{ selectedCount }}
        </div>
        <span class="text-xs sm:text-sm font-medium">
          Đã chọn <strong class="text-blue-400 sm:text-blue-600 font-semibold">{{ selectedCount }}</strong> {{ itemLabel }}
        </span>
        <button
          type="button"
          class="text-[11px] sm:text-xs text-slate-400 hover:text-slate-200 sm:hover:text-slate-600 underline ml-1 cursor-pointer transition-colors"
          @click="emit('clear')"
        >
          Bỏ chọn
        </button>
      </div>

      <!-- Bên phải: Dropdown Hành động hàng loạt -->
      <div class="flex items-center gap-2 ml-auto">
        <v-menu location="top end" :close-on-content-click="true">
          <template #activator="{ props: menuProps }">
            <v-btn
              v-bind="menuProps"
              color="primary"
              variant="flat"
              rounded="lg"
              size="small"
              class="text-none font-medium px-3.5"
              :loading="loading"
            >
              <v-icon start icon="mdi-lightning-bolt" size="small" />
              <span>Thao tác hàng loạt</span>
              <v-icon end icon="mdi-chevron-down" size="small" />
            </v-btn>
          </template>

          <v-list density="compact" rounded="lg" elevation="4" class="py-1 min-w-[200px]">
            <!-- Cập nhật trạng thái nhiều -->
            <v-list-item
              v-if="statusOptions.length > 0 && canUpdateStatus"
              prepend-icon="mdi-swap-horizontal-bold"
              title="Đổi trạng thái"
              class="cursor-pointer hover:bg-slate-50"
              @click="openStatusDialog"
            />

            <v-divider v-if="statusOptions.length > 0 && canUpdateStatus && canDelete" class="my-1" />

            <!-- Xóa hàng loạt -->
            <v-list-item
              v-if="canDelete"
              prepend-icon="mdi-trash-can-outline"
              title="Xóa đã chọn"
              class="cursor-pointer text-rose-600 hover:bg-rose-50"
              @click="openDeleteDialog"
            />
          </v-list>
        </v-menu>

        <!-- Nút đóng nhanh cho Mobile -->
        <button
          type="button"
          class="sm:hidden p-1.5 rounded-lg text-slate-400 hover:text-white"
          title="Đóng"
          @click="emit('clear')"
        >
          <v-icon icon="mdi-close" size="small" />
        </button>
      </div>
    </div>
  </Transition>

  <!-- Modal Đổi trạng thái hàng loạt -->
  <v-dialog v-model="statusDialogOpen" max-width="480" persistent>
    <div class="bg-white rounded-2xl p-5 border border-slate-100 shadow-xl space-y-4">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center">
          <v-icon icon="mdi-swap-horizontal-bold" size="22" />
        </div>
        <div>
          <h3 class="text-base font-bold text-slate-800">Cập nhật trạng thái</h3>
          <p class="text-xs text-slate-500">Áp dụng cho {{ selectedCount }} {{ itemLabel }} đang được chọn.</p>
        </div>
      </div>

      <div class="space-y-3 pt-2">
        <label class="block text-xs font-semibold text-slate-700">Chọn trạng thái mới *</label>
        <v-select
          v-model="selectedStatus"
          :items="statusOptions"
          item-title="title"
          item-value="value"
          density="comfortable"
          variant="outlined"
          hide-details
        />

        <div v-if="$slots.statusNote || true">
          <label class="block text-xs font-semibold text-slate-700 mb-1">Ghi chú (Tùy chọn)</label>
          <v-text-field
            v-model="statusNote"
            placeholder="Ví dụ: Kiểm kê định kỳ, bảo dưỡng..."
            density="comfortable"
            variant="outlined"
            hide-details
          />
        </div>
      </div>

      <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
        <AppButton variant="text" color="secondary" @click="statusDialogOpen = false">
          Hủy bỏ
        </AppButton>
        <AppButton :loading="loading" @click="confirmStatusChange">
          Áp dụng cho {{ selectedCount }} mục
        </AppButton>
      </div>
    </div>
  </v-dialog>

  <!-- Modal Xác nhận Xóa hàng loạt -->
  <AppConfirmDialog
    v-model="deleteDialogOpen"
    :title="deleteTitle || `Xóa ${selectedCount} ${itemLabel}`"
    :message="deleteMessage || `Bạn có chắc chắn muốn xóa vĩnh viễn ${selectedCount} ${itemLabel} đã chọn? Hành động này không thể hoàn tác.`"
    confirm-text="Xác nhận xóa"
    confirm-color="error"
    confirm-icon="mdi-trash-can-outline"
    :loading="loading"
    @confirm="confirmDelete"
  />
</template>
