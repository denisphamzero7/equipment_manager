<script setup>
import { ref, computed } from 'vue'
import { toast } from '@/core/helpers/toast'
import AppButton from './AppButton.vue'
import AppConfirmDialog from './AppConfirmDialog.vue'

const props = defineProps({
  // Hỗ trợ cả v-model, v-model:selected-ids và props truyền vào
  modelValue: {
    type: Array,
    default: () => [],
  },
  selectedIds: {
    type: Array,
    default: null,
  },
  selectedCount: {
    type: Number,
    default: null,
  },
  allIds: {
    type: Array,
    default: () => [],
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
  deleteConfirmTitle: {
    type: String,
    default: '',
  },
  deleteConfirmMessage: {
    type: String,
    default: '',
  },
  statusModalTitle: {
    type: String,
    default: '',
  },
  alwaysShow: {
    type: Boolean,
    default: true,
  },
})

const emit = defineEmits(['update:modelValue', 'update:selectedIds', 'clear', 'delete', 'update-status'])

const currentSelectedList = computed(() => {
  if (Array.isArray(props.selectedIds)) return props.selectedIds
  if (Array.isArray(props.modelValue)) return props.modelValue
  return []
})

const count = computed(() => {
  if (typeof props.selectedCount === 'number') return props.selectedCount
  return currentSelectedList.value.length
})

const isAllSelected = computed(() => {
  if (!props.allIds?.length) return false
  const selectedSet = new Set(currentSelectedList.value.map(Number))
  return props.allIds.length > 0 && props.allIds.every(id => selectedSet.has(Number(id)))
})

const isIndeterminate = computed(() => {
  if (!props.allIds?.length) return false
  const selectedSet = new Set(currentSelectedList.value.map(Number))
  const matched = props.allIds.filter(id => selectedSet.has(Number(id))).length
  return matched > 0 && matched < props.allIds.length
})

function updateSelection(newList) {
  emit('update:modelValue', newList)
  emit('update:selectedIds', newList)
}

function toggleSelectAll(event) {
  if (event.target.checked) {
    const all = Array.from(new Set([...currentSelectedList.value.map(Number), ...props.allIds.map(Number)]))
    updateSelection(all)
  } else {
    const allIdsSet = new Set(props.allIds.map(Number))
    const remaining = currentSelectedList.value.filter(id => !allIdsSet.has(Number(id)))
    updateSelection(remaining)
  }
}

function handleClear() {
  updateSelection([])
  emit('clear')
}

// Dialog States
const deleteDialogOpen = ref(false)
const statusDialogOpen = ref(false)
const selectedStatus = ref('')
const statusNote = ref('')

function openStatusDialog() {
  if (count.value === 0) {
    toast.info(`Vui lòng tích chọn ít nhất một ${props.itemLabel} để đổi trạng thái.`)
    return
  }
  if (!props.statusOptions.length) return
  selectedStatus.value = props.statusOptions[0]?.value || ''
  statusNote.value = ''
  statusDialogOpen.value = true
}

function confirmStatusChange() {
  if (!selectedStatus.value) return
  emit('update-status', {
    ids: currentSelectedList.value,
    status: selectedStatus.value,
    note: statusNote.value,
  })
  statusDialogOpen.value = false
}

function openDeleteDialog() {
  if (count.value === 0) {
    toast.info(`Vui lòng tích chọn ít nhất một ${props.itemLabel} để xóa.`)
    return
  }
  deleteDialogOpen.value = true
}

function confirmDelete() {
  emit('delete', currentSelectedList.value)
  deleteDialogOpen.value = false
}
</script>

<template>
  <div>
    <!-- 1. Thanh công cụ luôn hiển thị trên Desktop & Tablet (hoặc luôn hiển thị khi alwaysShow=true) -->
    <div
      v-if="alwaysShow || count > 0"
      class="bg-white border border-slate-200/90 rounded-2xl p-3 sm:px-4 sm:py-3 shadow-xs mb-4 flex items-center justify-between gap-3 flex-wrap transition-all duration-200"
      :class="count > 0 ? 'bg-primary/5 border-primary/30 ring-1 ring-primary/20' : ''"
    >
      <!-- Bên trái: Checkbox chọn tất cả (nếu có allIds) + Đếm số lượng + Nút Bỏ chọn -->
      <div class="flex items-center gap-3 flex-wrap">
        <!-- Nút Checkbox Chọn tất cả -->
        <label
          v-if="allIds.length > 0"
          class="inline-flex items-center gap-2 cursor-pointer select-none text-xs font-semibold text-slate-700 hover:text-slate-900 mr-1"
        >
          <input
            type="checkbox"
            :checked="isAllSelected"
            :indeterminate.prop="isIndeterminate"
            class="rounded border-slate-300 text-primary focus:ring-primary w-4 h-4 cursor-pointer"
            @change="toggleSelectAll"
          />
          <span>Chọn tất cả ({{ allIds.length }})</span>
        </label>

        <!-- Trạng thái số lượng đã chọn -->
        <div v-if="count > 0" class="flex items-center gap-2">
          <div class="flex items-center justify-center min-w-6 h-6 px-1.5 rounded-full bg-primary text-white font-bold text-xs">
            {{ count }}
          </div>
          <span class="text-xs sm:text-sm font-semibold text-slate-800">
            Đã chọn <strong class="text-primary font-bold">{{ count }}</strong> {{ itemLabel }}
          </span>
          <button
            type="button"
            class="text-xs font-medium text-slate-500 hover:text-rose-600 underline cursor-pointer ml-1 transition-colors"
            @click="handleClear"
          >
            Bỏ chọn
          </button>
        </div>

        <div v-else-if="allIds.length === 0" class="text-xs text-slate-400 flex items-center gap-1.5">
          <v-icon icon="mdi-checkbox-multiple-marked-outline" size="16" class="text-slate-400" />
          <span>Tích chọn các ô để kích hoạt thao tác hàng loạt</span>
        </div>
      </div>

      <!-- Bên phải: Dropdown Hành động -->
      <div class="flex items-center gap-2 ml-auto">
        <v-menu location="bottom end" :close-on-content-click="true">
          <template #activator="{ props: menuProps }">
            <v-btn
              v-bind="menuProps"
              :color="count > 0 ? 'primary' : 'default'"
              :variant="count > 0 ? 'flat' : 'outlined'"
              rounded="lg"
              size="small"
              class="text-none font-semibold px-3.5"
              :loading="loading"
            >
              <v-icon start icon="mdi-lightning-bolt" size="small" />
              <span>Thao tác hàng loạt</span>
              <span v-if="count > 0" class="ml-1 px-1.5 py-0.2 rounded-full bg-white/20 text-[11px] font-bold">
                {{ count }}
              </span>
              <v-icon end icon="mdi-chevron-down" size="small" />
            </v-btn>
          </template>

          <v-list density="compact" rounded="lg" elevation="6" class="py-1 min-w-[210px]">
            <!-- Đổi trạng thái -->
            <v-list-item
              v-if="statusOptions.length > 0 && canUpdateStatus"
              prepend-icon="mdi-swap-horizontal-bold"
              :title="count > 0 ? `Đổi trạng thái (${count})` : 'Đổi trạng thái'"
              class="cursor-pointer hover:bg-slate-50 font-medium text-xs sm:text-sm"
              @click="openStatusDialog"
            />

            <v-divider v-if="statusOptions.length > 0 && canUpdateStatus && canDelete" class="my-1" />

            <!-- Xóa hàng loạt -->
            <v-list-item
              v-if="canDelete"
              prepend-icon="mdi-trash-can-outline"
              :title="count > 0 ? `Xóa ${count} ${itemLabel}` : 'Xóa đã chọn'"
              class="cursor-pointer text-rose-600 hover:bg-rose-50 font-medium text-xs sm:text-sm"
              @click="openDeleteDialog"
            />
          </v-list>
        </v-menu>
      </div>
    </div>

    <!-- 2. Floating Bottom Bar tiện lợi cho Mobile khi đã chọn item và cuộn trang -->
    <Transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="opacity-0 translate-y-4"
      enter-to-class="opacity-100 translate-y-0"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="opacity-100 translate-y-0"
      leave-to-class="opacity-0 translate-y-4"
    >
      <div
        v-if="count > 0"
        class="fixed bottom-4 inset-x-3.5 z-40 sm:hidden bg-slate-900/95 text-white backdrop-blur-md rounded-2xl p-3 border border-slate-700/60 shadow-2xl flex items-center justify-between gap-2.5"
      >
        <div class="flex items-center gap-2">
          <div class="flex items-center justify-center w-6 h-6 rounded-full bg-blue-500 font-bold text-xs text-white">
            {{ count }}
          </div>
          <span class="text-xs font-semibold">
            Đã chọn {{ count }} {{ itemLabel }}
          </span>
          <button
            type="button"
            class="text-[11px] text-slate-300 underline ml-1 cursor-pointer"
            @click="handleClear"
          >
            Bỏ chọn
          </button>
        </div>

        <div class="flex items-center gap-1.5 ml-auto">
          <v-menu location="top end" :close-on-content-click="true">
            <template #activator="{ props: menuProps }">
              <v-btn
                v-bind="menuProps"
                color="primary"
                variant="flat"
                rounded="lg"
                size="x-small"
                class="text-none font-bold"
                :loading="loading"
              >
                <span>Hành động</span>
                <v-icon end icon="mdi-chevron-up" size="14" />
              </v-btn>
            </template>

            <v-list density="compact" rounded="lg" elevation="6" class="py-1 min-w-[190px]">
              <v-list-item
                v-if="statusOptions.length > 0 && canUpdateStatus"
                prepend-icon="mdi-swap-horizontal-bold"
                title="Đổi trạng thái"
                class="cursor-pointer hover:bg-slate-50 text-xs"
                @click="openStatusDialog"
              />
              <v-divider v-if="statusOptions.length > 0 && canUpdateStatus && canDelete" class="my-1" />
              <v-list-item
                v-if="canDelete"
                prepend-icon="mdi-trash-can-outline"
                title="Xóa đã chọn"
                class="cursor-pointer text-rose-600 hover:bg-rose-50 text-xs"
                @click="openDeleteDialog"
              />
            </v-list>
          </v-menu>

          <button
            type="button"
            class="p-1 rounded-lg text-slate-400 hover:text-white"
            title="Bỏ chọn"
            @click="handleClear"
          >
            <v-icon icon="mdi-close" size="16" />
          </button>
        </div>
      </div>
    </Transition>

    <!-- Modal Đổi trạng thái hàng loạt -->
    <v-dialog v-model="statusDialogOpen" max-width="480" persistent>
      <div class="bg-white rounded-2xl p-5 border border-slate-100 shadow-xl space-y-4">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center shrink-0">
            <v-icon icon="mdi-swap-horizontal-bold" size="22" />
          </div>
          <div>
            <h3 class="text-base font-bold text-slate-800">{{ statusModalTitle || 'Cập nhật trạng thái' }}</h3>
            <p class="text-xs text-slate-500">Áp dụng cho {{ count }} {{ itemLabel }} đang được chọn.</p>
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

          <div>
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
            Áp dụng cho {{ count }} {{ itemLabel }}
          </AppButton>
        </div>
      </div>
    </v-dialog>

    <!-- Modal Xác nhận Xóa hàng loạt -->
    <AppConfirmDialog
      v-model="deleteDialogOpen"
      :title="deleteConfirmTitle || `Xóa ${count} ${itemLabel}`"
      :message="deleteConfirmMessage || `Bạn có chắc chắn muốn xóa vĩnh viễn ${count} ${itemLabel} đã chọn? Hành động này không thể hoàn tác.`"
      confirm-text="Xác nhận xóa"
      confirm-color="error"
      confirm-icon="mdi-trash-can-outline"
      :loading="loading"
      @confirm="confirmDelete"
    />
  </div>
</template>
