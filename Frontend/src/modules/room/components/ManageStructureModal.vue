<script setup>
import { ref } from 'vue'
import AppModal from '@/core/components/AppModal.vue'
import AppButton from '@/core/components/AppButton.vue'
import { rules } from '@/core/helpers/validators'
import { roomService } from '../services/roomService'
import { toast } from '@/core/helpers/toast'

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  existingBlocks: {
    type: Array,
    default: () => [],
  },
})

const emit = defineEmits(['update:modelValue', 'updated'])

const activeTab = ref('rename')
const submitting = ref(false)

// Rename state
const renameFormRef = ref(null)
const selectedOldBlock = ref('')
const newBlockName = ref('')

// Delete state
const selectedDeleteBlock = ref('')
const deleteConfirmText = ref('')

async function handleRename() {
  const { valid } = await renameFormRef.value.validate()
  if (!valid) return

  if (selectedOldBlock.value === newBlockName.value) {
    toast.error('Tên mới phải khác tên khu vực hiện tại.')
    return
  }

  submitting.value = true
  try {
    const res = await roomService.renameBlock(selectedOldBlock.value, newBlockName.value)
    toast.success(res.message || `Đã đổi tên khu vực từ ${selectedOldBlock.value} thành ${newBlockName.value}!`)
    selectedOldBlock.value = ''
    newBlockName.value = ''
    emit('updated')
    emit('update:modelValue', false)
  } catch (err) {
    const msg = err.response?.data?.message || err.message || 'Lỗi khi đổi tên khu vực.'
    toast.error(msg)
  } finally {
    submitting.value = false
  }
}

async function handleDelete() {
  if (!selectedDeleteBlock.value) {
    toast.error('Vui lòng chọn khu vực cần xóa.')
    return
  }

  if (deleteConfirmText.value !== selectedDeleteBlock.value) {
    toast.error(`Vui lòng nhập chính xác "${selectedDeleteBlock.value}" để xác nhận xóa.`)
    return
  }

  submitting.value = true
  try {
    const res = await roomService.deleteBlock(selectedDeleteBlock.value)
    toast.success(res.message || `Đã xóa toàn bộ phòng thuộc khu vực ${selectedDeleteBlock.value}!`)
    selectedDeleteBlock.value = ''
    deleteConfirmText.value = ''
    emit('updated')
    emit('update:modelValue', false)
  } catch (err) {
    const msg = err.response?.data?.message || err.message || 'Lỗi khi xóa khu vực.'
    toast.error(msg)
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <AppModal
    :model-value="modelValue"
    title="Quản Lý Khu Vực & Tòa Nhà (Block)"
    subtitle="Điều chỉnh cơ cấu tổ chức khu vực, đổi tên khối nhà hoặc dọn dẹp phân khu"
    max-width="580"
    @update:model-value="val => emit('update:modelValue', val)"
  >
    <!-- Tab navigation -->
    <div class="flex items-center border-b border-slate-200 mb-4">
      <button
        class="px-4 py-2 text-sm font-semibold border-b-2 transition-colors duration-150 flex items-center gap-1.5"
        :class="activeTab === 'rename' ? 'border-primary text-primary' : 'border-transparent text-slate-500 hover:text-slate-800'"
        @click="activeTab = 'rename'"
      >
        <v-icon icon="mdi-pencil-outline" size="18" />
        Đổi tên Khu vực / Block
      </button>
      <button
        class="px-4 py-2 text-sm font-semibold border-b-2 transition-colors duration-150 flex items-center gap-1.5"
        :class="activeTab === 'delete' ? 'border-rose-500 text-rose-600' : 'border-transparent text-slate-500 hover:text-slate-800'"
        @click="activeTab = 'delete'"
      >
        <v-icon icon="mdi-trash-can-outline" size="18" />
        Xóa Khu vực
      </button>
    </div>

    <!-- Tab 1: Đổi tên Block -->
    <div v-if="activeTab === 'rename'">
      <v-form ref="renameFormRef" @submit.prevent="handleRename">
        <div class="space-y-4">
          <div>
            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
              Chọn Khu vực / Block hiện tại *
            </label>
            <v-select
              v-model="selectedOldBlock"
              :items="existingBlocks"
              placeholder="Chọn khu vực cần đổi tên..."
              :rules="[rules.required]"
              hide-details="auto"
              variant="outlined"
              density="compact"
              color="primary"
              prepend-inner-icon="mdi-domain"
            />
          </div>

          <div>
            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
              Tên Khu vực mới *
            </label>
            <v-text-field
              v-model="newBlockName"
              placeholder="Ví dụ: Tòa A Chính, Khu Condotel..."
              :rules="[rules.required]"
              hide-details="auto"
              variant="outlined"
              density="compact"
              color="primary"
              prepend-inner-icon="mdi-rename"
            />
          </div>

          <div class="p-3 bg-amber-50 border border-amber-200 rounded-xl flex items-start gap-2.5">
            <v-icon icon="mdi-information-outline" color="amber-darken-3" class="mt-0.5" />
            <div class="text-xs text-amber-900 leading-relaxed">
              Khi đổi tên, toàn bộ các phòng và sơ đồ hiển thị liên kết với khu vực này sẽ tự động cập nhật sang tên mới ngay lập tức.
            </div>
          </div>
        </div>
      </v-form>
    </div>

    <!-- Tab 2: Xóa Block -->
    <div v-else-if="activeTab === 'delete'">
      <div class="space-y-4">
        <div>
          <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
            Chọn Khu vực / Block cần xóa *
          </label>
          <v-select
            v-model="selectedDeleteBlock"
            :items="existingBlocks"
            placeholder="Chọn khu vực..."
            hide-details="auto"
            variant="outlined"
            density="compact"
            color="error"
            prepend-inner-icon="mdi-domain-remove"
          />
        </div>

        <div class="p-3 bg-rose-50 border border-rose-200 rounded-xl flex items-start gap-2.5">
          <v-icon icon="mdi-alert-outline" color="rose" class="mt-0.5" />
          <div class="text-xs text-rose-900 leading-relaxed">
            <strong class="font-bold">Bảo toàn thiết bị:</strong> Khi xóa khu vực, tất cả các phòng thuộc khu vực đó sẽ bị xóa. Hệ thống sẽ <strong>tự động thu hồi toàn bộ thiết bị đang gắn trong các phòng về kho</strong> (trạng thái: <em>Tồn kho</em>), dữ liệu thiết bị không bị mất.
          </div>
        </div>

        <div v-if="selectedDeleteBlock">
          <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
            Nhập chữ "<span class="text-rose-600 select-all">{{ selectedDeleteBlock }}</span>" để xác nhận:
          </label>
          <v-text-field
            v-model="deleteConfirmText"
            :placeholder="selectedDeleteBlock"
            hide-details="auto"
            variant="outlined"
            density="compact"
            color="error"
          />
        </div>
      </div>
    </div>

    <template #footer>
      <div class="flex items-center justify-end gap-2 w-full">
        <AppButton variant="secondary" :disabled="submitting" @click="emit('update:modelValue', false)">
          Đóng
        </AppButton>
        <AppButton
          v-if="activeTab === 'rename'"
          variant="primary"
          :loading="submitting"
          :disabled="!selectedOldBlock || !newBlockName"
          @click="handleRename"
        >
          <v-icon start icon="mdi-check" />
          Đổi tên khu vực
        </AppButton>
        <AppButton
          v-else-if="activeTab === 'delete'"
          variant="danger"
          :loading="submitting"
          :disabled="!selectedDeleteBlock || deleteConfirmText !== selectedDeleteBlock"
          @click="handleDelete"
        >
          <v-icon start icon="mdi-trash-can" />
          Xác nhận xóa khu vực
        </AppButton>
      </div>
    </template>
  </AppModal>
</template>
