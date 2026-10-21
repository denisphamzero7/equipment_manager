<script setup>
import { ref, watch, computed } from 'vue'
import AppModal from '@/core/components/AppModal.vue'
import AppButton from '@/core/components/AppButton.vue'
import { RoomStatusOptions } from '@/core/enums/RoomStatus'
import { rules } from '@/core/helpers/validators'
import { roomService } from '../services/roomService'
import { toast } from '@/core/helpers/toast'

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  room: {
    type: Object,
    default: null,
  },
  roomTypes: {
    type: Array,
    default: () => [],
  },
  existingBlocks: {
    type: Array,
    default: () => [],
  },
})

const emit = defineEmits(['update:modelValue', 'saved'])

const formRef = ref(null)
const submitting = ref(false)
const isEdit = computed(() => !!props.room?.id)

const form = ref({
  room_number: '',
  name: '',
  block: 'Block A',
  floor: 1,
  room_type_id: null,
  price: 0,
  status: 'ready',
  grid_row: 1,
  grid_col: 1,
  description: '',
})

watch(
  () => props.room,
  val => {
    if (val) {
      form.value = {
        room_number: val.room_number || '',
        name: val.name || '',
        block: val.block || 'Block A',
        floor: val.floor || 1,
        room_type_id: val.room_type_id || val.roomType?.id || null,
        price: val.price || 0,
        status: val.status || 'ready',
        grid_row: val.grid_row ?? val.floor ?? 1,
        grid_col: val.grid_col ?? 1,
        description: val.description || '',
      }
    } else {
      form.value = {
        room_number: '',
        name: '',
        block: props.existingBlocks[0] || 'Block A',
        floor: 1,
        room_type_id: props.roomTypes[0]?.id || null,
        price: 0,
        status: 'ready',
        grid_row: 1,
        grid_col: 1,
        description: '',
      }
    }
  },
  { immediate: true }
)

// Auto-fill room name if empty when room_number changes
function handleRoomNumberChange() {
  if (!form.value.name || form.value.name.startsWith('Phòng ')) {
    form.value.name = form.value.room_number ? `Phòng ${form.value.room_number}` : ''
  }
}

async function handleSubmit() {
  const { valid } = await formRef.value.validate()
  if (!valid) return

  submitting.value = true
  try {
    const payload = {
      ...form.value,
      room_number: parseInt(form.value.room_number, 10),
      floor: parseInt(form.value.floor, 10),
      grid_row: parseInt(form.value.grid_row, 10) || parseInt(form.value.floor, 10) || 1,
      grid_col: parseInt(form.value.grid_col, 10) || 1,
      price: parseFloat(form.value.price) || 0,
    }

    if (isEdit.value) {
      await roomService.updateRoom(props.room.id, payload)
      toast.success(`Cập nhật phòng ${payload.room_number} thành công!`)
    } else {
      await roomService.createRoom(payload)
      toast.success(`Tạo mới phòng ${payload.room_number} thành công!`)
    }

    emit('update:modelValue', false)
    emit('saved')
  } catch (err) {
    const msg = err.response?.data?.message || err.message || 'Thao tác không thành công.'
    toast.error(msg)
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <AppModal
    :model-value="modelValue"
    :title="isEdit ? 'Chỉnh Sửa Thông Tin Phòng' : 'Thêm Phòng Mới'"
    :subtitle="isEdit ? `Mã phòng #${room?.id} - Số ${room?.room_number}` : 'Nhập thông tin chi tiết phòng khách sạn'"
    max-width="650"
    @update:model-value="val => emit('update:modelValue', val)"
  >
    <v-form ref="formRef" @submit.prevent="handleSubmit">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <!-- Số phòng -->
        <div>
          <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
            Số phòng *
          </label>
          <v-text-field
            v-model="form.room_number"
            type="number"
            placeholder="Ví dụ: 201"
            :rules="[rules.required]"
            hide-details="auto"
            variant="outlined"
            density="compact"
            color="primary"
            prepend-inner-icon="mdi-numeric"
            @update:model-value="handleRoomNumberChange"
          />
        </div>

        <!-- Tên phòng -->
        <div>
          <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
            Tên hiển thị
          </label>
          <v-text-field
            v-model="form.name"
            placeholder="Ví dụ: Phòng 201"
            hide-details="auto"
            variant="outlined"
            density="compact"
            color="primary"
            prepend-inner-icon="mdi-tag-outline"
          />
        </div>

        <!-- Khu vực / Block -->
        <div>
          <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
            Khu vực / Block *
          </label>
          <v-combobox
            v-model="form.block"
            :items="existingBlocks"
            placeholder="Chọn hoặc nhập Block..."
            :rules="[rules.required]"
            hide-details="auto"
            variant="outlined"
            density="compact"
            color="primary"
            prepend-inner-icon="mdi-domain"
          />
        </div>

        <!-- Tầng (Floor) -->
        <div>
          <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
            Tầng (Floor) *
          </label>
          <v-text-field
            v-model.number="form.floor"
            type="number"
            min="1"
            max="100"
            placeholder="Ví dụ: 2"
            :rules="[rules.required]"
            hide-details="auto"
            variant="outlined"
            density="compact"
            color="primary"
            prepend-inner-icon="mdi-stairs"
          />
        </div>

        <!-- Loại phòng -->
        <div>
          <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
            Hạng phòng (Loại phòng)
          </label>
          <v-select
            v-model="form.room_type_id"
            :items="roomTypes"
            item-title="name"
            item-value="id"
            placeholder="Chọn hạng phòng..."
            hide-details="auto"
            variant="outlined"
            density="compact"
            color="primary"
            prepend-inner-icon="mdi-bed-outline"
            clearable
          />
        </div>

        <!-- Trạng thái phòng -->
        <div>
          <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
            Trạng thái phòng *
          </label>
          <v-select
            v-model="form.status"
            :items="RoomStatusOptions"
            item-title="label"
            item-value="value"
            :rules="[rules.required]"
            hide-details="auto"
            variant="outlined"
            density="compact"
            color="primary"
            prepend-inner-icon="mdi-shield-check-outline"
          />
        </div>

        <!-- Giá thuê phòng -->
        <div>
          <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
            Giá niêm yết (VNĐ)
          </label>
          <v-text-field
            v-model.number="form.price"
            type="number"
            min="0"
            step="10000"
            placeholder="500000"
            hide-details="auto"
            variant="outlined"
            density="compact"
            color="primary"
            prepend-inner-icon="mdi-cash"
          />
        </div>

        <!-- Vị trí lưới sơ đồ (Map View Layout) -->
        <div class="grid grid-cols-2 gap-2">
          <div>
            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
              Hàng sơ đồ
            </label>
            <v-text-field
              v-model.number="form.grid_row"
              type="number"
              min="1"
              placeholder="1"
              hide-details="auto"
              variant="outlined"
              density="compact"
            />
          </div>
          <div>
            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
              Cột sơ đồ
            </label>
            <v-text-field
              v-model.number="form.grid_col"
              type="number"
              min="1"
              placeholder="1"
              hide-details="auto"
              variant="outlined"
              density="compact"
            />
          </div>
        </div>

        <!-- Ghi chú / Mô tả -->
        <div class="md:col-span-2">
          <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
            Mô tả / Ghi chú
          </label>
          <v-textarea
            v-model="form.description"
            rows="2"
            placeholder="Nhập ghi chú hoặc đặc điểm phòng (nếu có)..."
            hide-details="auto"
            variant="outlined"
            density="compact"
            color="primary"
          />
        </div>
      </div>
    </v-form>

    <template #actions>
      <div class="flex items-center justify-end gap-2 w-full">
        <AppButton variant="secondary" :disabled="submitting" @click="emit('update:modelValue', false)">
          Hủy bỏ
        </AppButton>
        <AppButton variant="primary" :loading="submitting" @click="handleSubmit">
          <v-icon start icon="mdi-check" />
          {{ isEdit ? 'Lưu thay đổi' : 'Tạo phòng' }}
        </AppButton>
      </div>
    </template>
  </AppModal>
</template>
