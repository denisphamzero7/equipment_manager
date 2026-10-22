<script setup>
import { ref, computed, watch } from 'vue'
import AppModal from '@/core/components/AppModal.vue'
import AppButton from '@/core/components/AppButton.vue'
import RoomCascaderSelect from '@/core/components/RoomCascaderSelect.vue'
import ManageEquipmentModelsModal from './ManageEquipmentModelsModal.vue'
import { equipmentService } from '../services/equipmentService'
import { EquipmentStatus, EquipmentStatusOptions } from '@/core/enums/EquipmentStatus'
import { rules } from '@/core/helpers/validators'
import { resolveMediaUrl } from '@/core/helpers/formatters'

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  equipment: {
    type: Object,
    default: null,
  },
  loading: {
    type: Boolean,
    default: false,
  },
  initialData: {
    type: Object,
    default: null,
  },
})

const emit = defineEmits(['update:modelValue', 'submit'])

const formRef = ref(null)
const isEdit = ref(false)
const photoFile = ref(null)
const photoPreview = ref(null)
const models = ref([])
const categories = ref([])

// Manage models modal
const manageModelsModalOpen = ref(false)
const prefillModelName = ref('')
const modelSearchQuery = ref('')

async function loadModels() {
  try {
    models.value = await equipmentService.getModels()
  } catch (error) {
    console.error('Failed to load equipment models:', error)
  }
}

async function loadCategories() {
  try {
    categories.value = await equipmentService.getCategories()
  } catch (error) {
    console.error('Failed to load equipment categories:', error)
  }
}

function openManageModelsModal() {
  prefillModelName.value = ''
  manageModelsModalOpen.value = true
}

async function handleModelsChanged() {
  await loadModels()
}

function handleModelCreated(created) {
  if (created?.id) {
    form.value.equipment_model_id = created.id
  }
}

const quickCreateCategoryId = ref(null)

const modelOptions = computed(() => {
  return models.value.map(m => ({
    id: m.id,
    title: `${m.name}${m.brand ? ' — ' + m.brand : ''}${m.category?.name ? ' (' + m.category.name + ')' : ''}`,
  }))
})

function findExistingModel(value) {
  if (value && typeof value === 'object') {
    if (value.id !== undefined && value.id !== null) {
      return modelOptions.value.find(option => Number(option.id) === Number(value.id)) || {
        id: Number(value.id),
        title: value.title || value.name || '',
      }
    }
    value = value.title || value.name || ''
  }

  if (typeof value === 'number' || (typeof value === 'string' && /^\d+$/.test(value.trim()))) {
    return modelOptions.value.find(option => Number(option.id) === Number(value)) || {
      id: Number(value),
      title: '',
    }
  }

  if (typeof value === 'string') {
    const name = value.trim()
    const exactOption = modelOptions.value.find(option => option.title === name)
    if (exactOption) return exactOption

    const matchingModel = models.value.find(model => model.name === name)
    if (matchingModel) {
      return modelOptions.value.find(option => Number(option.id) === Number(matchingModel.id)) || {
        id: Number(matchingModel.id),
        title: matchingModel.name,
      }
    }
  }

  return null
}

const selectedExistingModel = computed(() => findExistingModel(form.value.equipment_model_id))
const newModelName = computed(() => {
  if (selectedExistingModel.value) return ''
  const value = form.value.equipment_model_id
  if (typeof value === 'string') return value.trim()
  if (value && typeof value === 'object') return (value.title || value.name || '').trim()
  return ''
})
const isNewModel = computed(() => newModelName.value.length > 0)

const form = ref({
  equipment_model_id: '',
  serial_number: '',
  room_id: null,
  status: EquipmentStatus.GOOD,
  position_note: '',
  note: '',
  installed_at: '',
})

watch(
  () => props.equipment,
  newVal => {
    photoFile.value = null
    if (newVal) {
      isEdit.value = true
      photoPreview.value = resolveMediaUrl(newVal.photo_url || newVal.photos?.[0]?.url || null)
      form.value = {
        equipment_model_id: newVal.equipment_model_id || newVal.model?.id || '',
        serial_number: newVal.serial_number || '',
        room_id: newVal.room_id ?? newVal.room?.id ?? null,
        status: newVal.status || EquipmentStatus.GOOD,
        position_note: newVal.position_note || '',
        note: newVal.note || '',
        installed_at: newVal.installed_at ? newVal.installed_at.substring(0, 10) : '',
      }
    } else {
      isEdit.value = false
      photoPreview.value = null
      form.value = {
        equipment_model_id: '',
        serial_number: '',
        room_id: null,
        status: EquipmentStatus.GOOD,
        position_note: '',
        note: '',
        installed_at: new Date().toISOString().substring(0, 10),
      }
    }
  },
  { immediate: true }
)

watch(
  () => props.modelValue,
  isOpen => {
    if (!isOpen) return
    void loadModels()
    void loadCategories()
    if (props.equipment) return
    const initial = props.initialData
    form.value = {
      equipment_model_id: initial?.equipment_model_id ?? '',
      serial_number: initial?.serial_number || '',
      room_id: initial?.room_id || null,
      status: initial?.status || EquipmentStatus.GOOD,
      position_note: initial?.position_note || '',
      note: initial?.note || '',
      installed_at: initial?.installed_at || new Date().toISOString().substring(0, 10),
    }
    quickCreateCategoryId.value = initial?.quickCreateCategoryId || null
    isEdit.value = false
    photoFile.value = initial?.photoFile || null
    photoPreview.value = photoFile.value ? URL.createObjectURL(photoFile.value) : null
  }
)

function handlePhotoChange(event) {
  const file = event.target?.files?.[0]
  if (file) {
    photoFile.value = file
    photoPreview.value = URL.createObjectURL(file)
  }
}

function removePhoto() {
  photoFile.value = null
  photoPreview.value = null
}

async function handleSubmit(continueAdding = false) {
  const { valid } = await formRef.value.validate()
  if (!valid) return
  if (isNewModel.value && !quickCreateCategoryId.value) return

  const payloadData = { ...form.value }
  const modelDraft = isNewModel.value
    ? {
        name: newModelName.value,
        equipment_category_id: quickCreateCategoryId.value,
        brand: '',
        model_code: '',
      }
    : null
  if (modelDraft && !modelDraft.name) return
  if (selectedExistingModel.value) {
    payloadData.equipment_model_id = Number(selectedExistingModel.value.id)
  }
  let dataToSend = payloadData
  if (photoFile.value) {
    const fd = new FormData()
    Object.keys(payloadData).forEach(key => {
      if (payloadData[key] !== null && payloadData[key] !== undefined && payloadData[key] !== '') {
        fd.append(key, payloadData[key])
      }
    })
    fd.append('photo', photoFile.value)
    dataToSend = fd
  }

  emit('submit', {
    isEdit: isEdit.value,
    id: props.equipment?.id,
    data: dataToSend,
    modelDraft,
    continueAdding,
    formValues: {
      ...form.value,
      equipment_model_id: modelDraft?.name || Number(selectedExistingModel.value?.id || form.value.equipment_model_id),
      quickCreateCategoryId: quickCreateCategoryId.value,
    },
    modelName: modelDraft?.name || selectedExistingModel.value?.title || '',
  })

  if (continueAdding && !isEdit.value) {
    form.value.serial_number = ''
    form.value.note = ''
    photoFile.value = null
    photoPreview.value = null
    return
  }
  if (!isEdit.value) emit('update:modelValue', false)
}
</script>

<template>
  <AppModal
    :model-value="modelValue"
    :title="isEdit ? 'Cập nhật Thông tin Thiết bị' : 'Thêm Thiết bị Mới'"
    :subtitle="isEdit ? `Mã thiết bị #${equipment?.id}` : 'Nhập thông tin cá thể thiết bị và định vị phòng'"
    max-width="600"
    @update:model-value="val => emit('update:modelValue', val)"
  >
    <v-form ref="formRef" @submit.prevent="handleSubmit()">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <!-- Model Select -->
        <div>
          <div class="flex items-center justify-between mb-1">
            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">
              Mẫu thiết bị *
            </label>
            <button
              type="button"
              class="text-[11px] text-blue-600 hover:text-blue-800 font-semibold flex items-center gap-0.5 transition"
              title="Quản lý danh sách mẫu và tên thiết bị"
              @click="openManageModelsModal"
            >
              <v-icon icon="mdi-cog-outline" size="13" />
              <span>Quản lý tên / mẫu</span>
            </button>
          </div>

          <v-combobox
            v-model="form.equipment_model_id"
            :items="modelOptions"
            item-title="title"
            item-value="id"
            placeholder="Tìm mẫu hoặc nhập tên mẫu mới..."
            :rules="[rules.required]"
            density="compact"
            variant="outlined"
            hide-details="auto"
            @update:search="val => modelSearchQuery = val"
          >
            <template #no-data>
              <div class="p-3 text-center text-xs">
                <p class="text-slate-600 mb-1 font-medium">
                  Dùng tên mới "<strong>{{ modelSearchQuery }}</strong>"
                </p>
                <p class="text-slate-500">
                  Nhấn Enter để chọn tên này; mẫu sẽ được tạo cùng thiết bị.
                </p>
              </div>
            </template>
          </v-combobox>
          <v-select
            v-if="isNewModel"
            v-model="quickCreateCategoryId"
            :items="categories"
            item-title="name"
            item-value="id"
            label="Danh mục cho mẫu mới *"
            :rules="[rules.required]"
            density="compact"
            variant="outlined"
            hide-details="auto"
            class="mt-2"
          />
          <p v-if="isNewModel && !categories.length" class="mt-1 text-xs text-rose-600">
            Chưa có danh mục để tạo mẫu thiết bị mới.
          </p>
        </div>

        <!-- Serial Number -->
        <div>
          <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
            Mã Serial / QR code
          </label>
          <v-text-field
            v-model="form.serial_number"
            placeholder="TV-SAM-43-001"
            :rules="[rules.serialNumber]"
            density="compact"
            variant="outlined"
            hide-details="auto"
          />
        </div>

        <!-- Room Cascader (Bậc thang: Block -> Tầng -> Phòng) -->
        <div class="md:col-span-2">
          <RoomCascaderSelect
            v-if="modelValue"
            v-model="form.room_id"
            label="Phòng / Vị trí đặt thiết bị"
            :allow-warehouse="true"
          />
        </div>

        <!-- Status -->
        <div>
          <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
            Trạng thái hoạt động
          </label>
          <v-select
            v-model="form.status"
            :items="EquipmentStatusOptions"
            item-title="title"
            item-value="value"
            density="compact"
            variant="outlined"
            hide-details="auto"
          />
        </div>

        <!-- Installed Date -->
        <div>
          <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
            Ngày lắp đặt
          </label>
          <v-text-field
            v-model="form.installed_at"
            type="date"
            density="compact"
            variant="outlined"
            hide-details="auto"
          />
        </div>

        <!-- Position Note -->
        <div class="md:col-span-2">
          <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
            Vị trí đặt cụ thể trong phòng / kho
          </label>
          <v-text-field
            v-model="form.position_note"
            placeholder="Ví dụ: Treo tường phòng ngủ, Cạnh bàn làm việc, Kệ A3..."
            density="compact"
            variant="outlined"
            hide-details="auto"
          />
        </div>

        <!-- Note -->
        <div class="md:col-span-2">
          <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
            Ghi chú chi tiết
          </label>
          <v-textarea
            v-model="form.note"
            rows="2"
            placeholder="Thông tin bảo dưỡng, xuất xứ hoặc tình trạng đặc biệt..."
            hide-details="auto"
          />
        </div>
        <!-- Photo Upload -->
        <div class="md:col-span-2">
          <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
            Hình ảnh thiết bị (Tùy chọn)
          </label>
          <div class="flex items-center gap-3">
            <div
              v-if="photoPreview"
              class="relative w-16 h-16 rounded-xl overflow-hidden border border-slate-200 shrink-0 bg-slate-50"
            >
              <img :src="photoPreview" alt="Photo Preview" class="w-full h-full object-cover" />
              <button
                type="button"
                class="absolute top-1 right-1 w-5 h-5 rounded-full bg-rose-500 text-white flex items-center justify-center hover:bg-rose-600 transition-colors shadow-xs"
                title="Gỡ ảnh"
                @click="removePhoto"
              >
                <v-icon icon="mdi-close" size="12" />
              </button>
            </div>
            <v-file-input
              accept="image/png, image/jpeg, image/jpg, image/webp"
              placeholder="Chọn hình ảnh từ máy tính"
              prepend-inner-icon="mdi-camera"
              prepend-icon=""
              hide-details="auto"
              density="compact"
              variant="outlined"
              class="flex-1"
              @change="handlePhotoChange"
            />
          </div>
        </div>
      </div>
    </v-form>

    <template #footer>
      <v-btn
        variant="tonal"
        color="secondary"
        :disabled="loading"
        @click="emit('update:modelValue', false)"
      >
        Hủy bỏ
      </v-btn>
      <AppButton
        v-if="!isEdit"
        color="secondary"
        variant="tonal"
        :disabled="loading"
        @click="handleSubmit(true)"
      >
        Lưu & tiếp tục tạo
      </AppButton>
      <AppButton
        color="primary"
        :loading="loading"
        @click="handleSubmit()"
      >
        {{ isEdit ? 'Lưu thay đổi' : 'Lưu & đóng' }}
      </AppButton>
    </template>
  </AppModal>

  <!-- Sub-modal: Manage Equipment Models -->
  <ManageEquipmentModelsModal
    v-model="manageModelsModalOpen"
    :initial-name="prefillModelName"
    @model-created="handleModelCreated"
    @models-changed="handleModelsChanged"
  />
</template>
