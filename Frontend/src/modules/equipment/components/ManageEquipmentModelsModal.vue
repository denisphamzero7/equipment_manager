<script setup>
import { ref, computed, watch } from 'vue'
import AppModal from '@/core/components/AppModal.vue'
import AppButton from '@/core/components/AppButton.vue'
import AppConfirmDialog from '@/core/components/AppConfirmDialog.vue'
import { equipmentService } from '../services/equipmentService'
import { toast } from '@/core/helpers/toast'
import { rules } from '@/core/helpers/validators'

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  initialName: {
    type: String,
    default: '',
  },
})

const emit = defineEmits(['update:modelValue', 'model-created', 'model-updated', 'models-changed'])

const loading = ref(false)
const actionLoading = ref(false)
const models = ref([])
const categories = ref([])
const searchQuery = ref('')

// Form state (Add / Edit)
const isFormVisible = ref(false)
const editingModelId = ref(null)
const formRef = ref(null)
const form = ref({
  name: '',
  equipment_category_id: null,
  brand: '',
  model_code: '',
})

// Delete Dialog
const deleteDialogOpen = ref(false)
const modelToDelete = ref(null)

watch(
  () => props.modelValue,
  (val) => {
    if (val) {
      loadModels()
      loadCategories()
      if (props.initialName && props.initialName.trim()) {
        openCreateForm(props.initialName.trim())
      }
    }
  }
)

async function loadModels() {
  loading.value = true
  try {
    models.value = await equipmentService.getModels()
  } catch (err) {
    console.error('Failed to load models:', err)
  } finally {
    loading.value = false
  }
}

async function loadCategories() {
  try {
    categories.value = await equipmentService.getCategories()
  } catch (err) {
    console.error('Failed to load categories:', err)
  }
}

const filteredModels = computed(() => {
  const q = searchQuery.value.trim().toLowerCase()
  if (!q) return models.value
  return models.value.filter(m => {
    const nameMatch = m.name?.toLowerCase().includes(q)
    const brandMatch = m.brand?.toLowerCase().includes(q)
    const catMatch = m.category?.name?.toLowerCase().includes(q)
    return nameMatch || brandMatch || catMatch
  })
})

function openCreateForm(prefilledName = '') {
  editingModelId.value = null
  form.value = {
    name: prefilledName,
    equipment_category_id: null,
    brand: '',
    model_code: '',
  }
  isFormVisible.value = true
}

function openEditForm(item) {
  editingModelId.value = item.id
  form.value = {
    name: item.name || '',
    equipment_category_id: item.equipment_category_id || item.category?.id || null,
    brand: item.brand || '',
    model_code: item.model_code || '',
  }
  isFormVisible.value = true
}

function cancelForm() {
  isFormVisible.value = false
  editingModelId.value = null
}

async function handleSaveModel() {
  const { valid } = await formRef.value.validate()
  if (!valid) return

  actionLoading.value = true
  try {
    if (editingModelId.value) {
      const updated = await equipmentService.updateModel(editingModelId.value, form.value)
      toast.success(`Đã cập nhật mẫu thiết bị '${form.value.name}'!`)
      emit('model-updated', updated)
    } else {
      const created = await equipmentService.createModel(form.value)
      toast.success(`Đã thêm mẫu thiết bị '${form.value.name}' thành công!`)
      emit('model-created', created)
    }
    isFormVisible.value = false
    await loadModels()
    emit('models-changed')
  } catch (err) {
    toast.error(err.response?.data?.message || err.message || 'Lưu mẫu thiết bị thất bại.')
  } finally {
    actionLoading.value = false
  }
}

function confirmDelete(item) {
  modelToDelete.value = item
  deleteDialogOpen.value = true
}

async function handleDeleteModel() {
  if (!modelToDelete.value) return
  actionLoading.value = true
  try {
    await equipmentService.deleteModel(modelToDelete.value.id)
    toast.success(`Đã xóa mẫu '${modelToDelete.value.name}'!`)
    deleteDialogOpen.value = false
    modelToDelete.value = null
    await loadModels()
    emit('models-changed')
  } catch (err) {
    toast.error(err.response?.data?.message || err.message || 'Xóa mẫu thiết bị thất bại.')
  } finally {
    actionLoading.value = false
  }
}
</script>

<template>
  <AppModal
    :model-value="modelValue"
    title="Quản lý Mẫu & Tên Thiết bị"
    subtitle="Thêm tên thiết bị mới, chỉnh sửa quy cách hoặc xóa các mẫu không sử dụng"
    max-width="680"
    @update:model-value="val => emit('update:modelValue', val)"
  >
    <div class="space-y-4">
      <!-- Toolbar: Search & Add button -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div class="flex-1">
          <v-text-field
            v-model="searchQuery"
            placeholder="Tìm theo tên mẫu, thương hiệu, danh mục..."
            prepend-inner-icon="mdi-magnify"
            density="compact"
            variant="outlined"
            hide-details
            clearable
          />
        </div>
        <AppButton
          v-if="!isFormVisible"
          color="primary"
          size="small"
          @click="openCreateForm()"
        >
          <v-icon start icon="mdi-plus" />
          Thêm mẫu mới
        </AppButton>
      </div>

      <!-- Quick Add / Edit Form Panel -->
      <v-expand-transition>
        <div
          v-if="isFormVisible"
          class="p-4 bg-slate-50 border-2 border-primary/20 rounded-2xl space-y-3"
        >
          <div class="flex items-center justify-between border-b border-slate-200 pb-2">
            <h4 class="text-xs font-bold text-slate-800 uppercase tracking-wider flex items-center gap-1.5">
              <v-icon :icon="editingModelId ? 'mdi-pencil' : 'mdi-plus-box'" size="16" color="primary" />
              <span>{{ editingModelId ? 'Sửa thông tin mẫu thiết bị' : 'Thêm mẫu thiết bị mới' }}</span>
            </h4>
            <button
              type="button"
              class="text-xs text-slate-400 hover:text-slate-600 transition"
              @click="cancelForm"
            >
              <v-icon icon="mdi-close" size="18" />
            </button>
          </div>

          <v-form ref="formRef" @submit.prevent="handleSaveModel">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <!-- Name -->
              <div class="sm:col-span-2">
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                  Tên thiết bị / Mẫu quy cách *
                </label>
                <v-text-field
                  v-model="form.name"
                  placeholder="Ví dụ: TV Sony 40 inch, Tủ lạnh mini Sanaky 90L..."
                  :rules="[rules.required]"
                  density="compact"
                  variant="outlined"
                  hide-details="auto"
                />
              </div>

              <!-- Category -->
              <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                  Nhóm danh mục *
                </label>
                <v-select
                  v-model="form.equipment_category_id"
                  :items="categories"
                  item-title="name"
                  item-value="id"
                  placeholder="Chọn nhóm danh mục"
                  :rules="[rules.required]"
                  density="compact"
                  variant="outlined"
                  hide-details="auto"
                />
              </div>

              <!-- Brand -->
              <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                  Hãng / Thương hiệu
                </label>
                <v-text-field
                  v-model="form.brand"
                  placeholder="Ví dụ: Sony, Samsung, TP-Link..."
                  density="compact"
                  variant="outlined"
                  hide-details="auto"
                />
              </div>

              <!-- Model Code -->
              <div class="sm:col-span-2">
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                  Mã hiệu sản xuất (Model Code - Tùy chọn)
                </label>
                <v-text-field
                  v-model="form.model_code"
                  placeholder="Ví dụ: KDL-40W650D, TL-WR841N..."
                  density="compact"
                  variant="outlined"
                  hide-details="auto"
                />
              </div>
            </div>

            <div class="flex items-center justify-end gap-2 pt-3">
              <v-btn
                variant="tonal"
                color="secondary"
                size="small"
                :disabled="actionLoading"
                @click="cancelForm"
              >
                Hủy bỏ
              </v-btn>
              <AppButton
                color="primary"
                size="small"
                :loading="actionLoading"
                @click="handleSaveModel"
              >
                <v-icon start icon="mdi-check" />
                {{ editingModelId ? 'Lưu cập nhật' : 'Tạo mẫu này' }}
              </AppButton>
            </div>
          </v-form>
        </div>
      </v-expand-transition>

      <!-- Models List Table / Cards -->
      <div class="border border-slate-200 rounded-xl overflow-hidden bg-white max-h-96 overflow-y-auto">
        <div v-if="loading" class="py-10 text-center">
          <v-progress-circular indeterminate color="primary" size="32" />
          <p class="text-xs text-slate-400 mt-2">Đang tải danh sách mẫu thiết bị...</p>
        </div>

        <div v-else-if="filteredModels.length === 0" class="py-12 text-center text-slate-400">
          <v-icon icon="mdi-devices" size="44" class="text-slate-300 mb-2" />
          <p class="text-xs font-medium text-slate-600">Không tìm thấy mẫu thiết bị nào phù hợp</p>
          <p class="text-[11px] text-slate-400 mt-0.5">Nhấp vào "Thêm mẫu mới" ở trên để tạo tên thiết bị này.</p>
          <div class="mt-3">
            <AppButton
              color="primary"
              size="small"
              @click="openCreateForm(searchQuery)"
            >
              <v-icon start icon="mdi-plus" />
              Thêm mẫu: "{{ searchQuery || 'Mới' }}"
            </AppButton>
          </div>
        </div>

        <div v-else class="divide-y divide-slate-100">
          <div
            v-for="item in filteredModels"
            :key="item.id"
            class="p-3 hover:bg-slate-50/80 transition-colors flex items-center justify-between gap-3 text-xs"
          >
            <!-- Left Info -->
            <div class="min-w-0 flex-1">
              <div class="flex items-center gap-2">
                <span class="font-bold text-slate-800 text-sm truncate">
                  {{ item.name }}
                </span>
                <span
                  v-if="item.brand"
                  class="text-[11px] px-2 py-0.5 rounded-md bg-slate-100 text-slate-600 font-medium shrink-0"
                >
                  {{ item.brand }}
                </span>
              </div>

              <div class="flex items-center gap-3 mt-1 text-[11px] text-slate-500">
                <span class="inline-flex items-center gap-1 font-medium text-slate-600">
                  <v-icon :icon="item.category?.icon || 'mdi-tag-outline'" size="14" class="text-primary" />
                  {{ item.category?.name || 'Chưa phân nhóm' }}
                </span>
                <span v-if="item.model_code" class="font-mono text-slate-400">
                  Mã: {{ item.model_code }}
                </span>
                <span
                  class="font-medium"
                  :class="item.equipments_count > 0 ? 'text-emerald-700' : 'text-slate-400'"
                >
                  {{ item.equipments_count > 0 ? `Đang dùng: ${item.equipments_count} thiết bị` : 'Chưa có cá thể nào' }}
                </span>
              </div>
            </div>

            <!-- Right Actions: Edit, Delete -->
            <div class="flex items-center gap-1 shrink-0">
              <button
                type="button"
                class="p-1.5 rounded-lg text-slate-500 hover:text-primary hover:bg-slate-100 transition"
                title="Sửa tên / thông tin mẫu"
                @click="openEditForm(item)"
              >
                <v-icon icon="mdi-pencil-outline" size="16" />
              </button>

              <button
                type="button"
                class="p-1.5 rounded-lg transition"
                :class="item.equipments_count > 0 ? 'text-slate-300 cursor-not-allowed' : 'text-slate-400 hover:text-rose-600 hover:bg-rose-50'"
                :title="item.equipments_count > 0 ? `Không thể xóa vì có ${item.equipments_count} thiết bị đang sử dụng mẫu này` : 'Xóa mẫu thiết bị'"
                :disabled="item.equipments_count > 0"
                @click="confirmDelete(item)"
              >
                <v-icon icon="mdi-delete-outline" size="16" />
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <template #footer>
      <v-btn
        variant="tonal"
        color="secondary"
        @click="emit('update:modelValue', false)"
      >
        Đóng
      </v-btn>
    </template>
  </AppModal>

  <!-- Confirm Delete Dialog -->
  <AppConfirmDialog
    v-model="deleteDialogOpen"
    title="Xác nhận xóa mẫu thiết bị"
    :message="`Bạn có chắc chắn muốn xóa mẫu thiết bị '${modelToDelete?.name}' không? Thao tác này không thể hoàn tác.`"
    confirm-text="Xóa vĩnh viễn"
    confirm-color="error"
    :loading="actionLoading"
    @confirm="handleDeleteModel"
  />
</template>
