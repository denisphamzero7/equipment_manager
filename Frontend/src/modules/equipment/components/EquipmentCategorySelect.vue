<script setup>
import { ref } from 'vue'
import { equipmentService } from '../services/equipmentService'

defineProps({
  modelValue: {
    type: [Number, String],
    default: null,
  },
  categories: {
    type: Array,
    default: () => [],
  },
  loading: {
    type: Boolean,
    default: false,
  },
  loadError: {
    type: String,
    default: '',
  },
  label: {
    type: String,
    default: 'Nhóm danh mục',
  },
})

const emit = defineEmits(['update:modelValue', 'retry', 'category-created'])

const adding = ref(false)
const saving = ref(false)
const categoryName = ref('')
const createError = ref('')

function openCreateForm() {
  adding.value = true
  createError.value = ''
}

function cancelCreateForm() {
  adding.value = false
  categoryName.value = ''
  createError.value = ''
}

async function createCategory() {
  const name = categoryName.value.trim()
  if (!name) {
    createError.value = 'Vui lòng nhập tên nhóm danh mục.'
    return
  }

  saving.value = true
  createError.value = ''
  try {
    const category = await equipmentService.createCategory({ name })
    emit('category-created', category)
    emit('update:modelValue', category.id)
    cancelCreateForm()
  } catch (error) {
    createError.value = error.response?.data?.message || error.message || 'Không thể tạo nhóm danh mục.'
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="space-y-1.5">
    <div class="flex items-center justify-between gap-2">
      <span class="text-xs font-bold text-slate-700">{{ label }}</span>
      <button
        v-if="!adding"
        type="button"
        class="shrink-0 text-xs font-semibold text-primary hover:underline"
        @click="openCreateForm"
      >
        + Thêm nhóm danh mục
      </button>
    </div>

    <v-select
      :model-value="modelValue"
      :items="categories"
      item-title="name"
      item-value="id"
      placeholder="Chọn nhóm danh mục"
      :loading="loading"
      :disabled="loading"
      :no-data-text="loading ? 'Đang tải nhóm danh mục...' : 'Chưa có nhóm danh mục thiết bị.'"
      :rules="[value => !!value || 'Vui lòng chọn nhóm danh mục.']"
      density="compact"
      variant="outlined"
      hide-details="auto"
      @update:model-value="emit('update:modelValue', $event)"
    >
      <template #no-data>
        <div class="px-3 py-2 text-center text-xs text-slate-600">
          <p>{{ loading ? 'Đang tải nhóm danh mục...' : 'Chưa có nhóm danh mục thiết bị.' }}</p>
          <button
            v-if="!loading"
            type="button"
            class="mt-1 font-semibold text-primary hover:underline"
            @click.stop="openCreateForm"
          >
            + Tạo nhóm danh mục mới
          </button>
        </div>
      </template>
    </v-select>

    <div v-if="loadError" class="flex items-center justify-between gap-2 text-xs text-rose-600">
      <span>{{ loadError }}</span>
      <button
        type="button"
        class="shrink-0 font-semibold underline"
        :disabled="loading"
        @click="emit('retry')"
      >
        Thử tải lại
      </button>
    </div>

    <div v-if="adding" class="space-y-2 rounded-lg border border-slate-200 bg-slate-50 p-3">
      <v-text-field
        v-model="categoryName"
        label="Tên nhóm danh mục mới *"
        placeholder="Ví dụ: Điện thoại bàn"
        maxlength="255"
        density="compact"
        variant="outlined"
        hide-details
        autofocus
        :disabled="saving"
        @keydown.enter.prevent="createCategory"
      />
      <p v-if="createError" class="text-xs text-rose-600">{{ createError }}</p>
      <div class="flex justify-end gap-2">
        <v-btn
          size="small"
          variant="tonal"
          :disabled="saving"
          @click="cancelCreateForm"
        >
          Hủy
        </v-btn>
        <v-btn
          size="small"
          color="primary"
          :loading="saving"
          :disabled="!categoryName.trim()"
          @click="createCategory"
        >
          Tạo nhóm
        </v-btn>
      </div>
    </div>

    <p v-else-if="!loading && !loadError && !categories.length" class="text-xs text-amber-700">
      Chưa có nhóm danh mục. Hãy thêm nhóm để tiếp tục tạo mẫu thiết bị.
    </p>
  </div>
</template>
