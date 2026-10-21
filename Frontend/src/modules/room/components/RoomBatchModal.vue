<script setup>
import { ref, computed } from 'vue'
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
  roomTypes: {
    type: Array,
    default: () => [],
  },
  existingBlocks: {
    type: Array,
    default: () => [],
  },
})

const emit = defineEmits(['update:modelValue', 'generated'])

const formRef = ref(null)
const submitting = ref(false)

const form = ref({
  block: 'Block A',
  floor: 2,
  prefix: 'Phòng ',
  start_number: 201,
  end_number: 215,
  room_type_id: null,
  price: 500000,
  status: 'ready',
})

// Quick calculation of rooms to generate
const calculatedCount = computed(() => {
  const start = parseInt(form.value.start_number, 10)
  const end = parseInt(form.value.end_number, 10)
  if (isNaN(start) || isNaN(end) || end < start) return 0
  return end - start + 1
})

const previewSamples = computed(() => {
  const start = parseInt(form.value.start_number, 10)
  const end = parseInt(form.value.end_number, 10)
  if (isNaN(start) || isNaN(end) || end < start) return []
  const count = end - start + 1
  if (count <= 6) {
    const list = []
    for (let i = start; i <= end; i++) list.push(i)
    return list
  }
  return [start, start + 1, start + 2, '...', end - 1, end]
})

function onFloorChange(val) {
  const floorNum = parseInt(val, 10)
  if (!isNaN(floorNum) && floorNum > 0) {
    form.value.start_number = floorNum * 100 + 1
    form.value.end_number = floorNum * 100 + 15
  }
}

async function handleSubmit() {
  const { valid } = await formRef.value.validate()
  if (!valid) return

  if (calculatedCount.value <= 0) {
    toast.error('Số kết thúc phải lớn hơn hoặc bằng số bắt đầu.')
    return
  }

  if (calculatedCount.value > 100) {
    toast.error('Để đảm bảo hiệu năng, mỗi lần chỉ nên sinh tối đa 100 phòng.')
    return
  }

  submitting.value = true
  try {
    const payload = {
      block: form.value.block,
      floor: parseInt(form.value.floor, 10),
      prefix: form.value.prefix,
      start_number: parseInt(form.value.start_number, 10),
      end_number: parseInt(form.value.end_number, 10),
      room_type_id: form.value.room_type_id,
      price: parseFloat(form.value.price) || 0,
      status: form.value.status,
    }

    const res = await roomService.batchGenerate(payload)
    const count = res.created_count || 0

    toast.success(`Đã tự động tạo thành công ${count} phòng cho ${payload.block} - Tầng ${payload.floor}!`)
    emit('update:modelValue', false)
    emit('generated', res)
  } catch (err) {
    const msg = err.response?.data?.message || err.message || 'Lỗi khi tạo phòng hàng loạt.'
    toast.error(msg)
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <AppModal
    :model-value="modelValue"
    title="Tạo Phòng Nhanh Hàng Loạt (Batch Generator)"
    subtitle="Tự động sinh đồng loạt các phòng và bố trí tọa độ cho một tầng trong vài giây"
    max-width="650"
    @update:model-value="val => emit('update:modelValue', val)"
  >
    <v-form ref="formRef" @submit.prevent="handleSubmit">
      <!-- Banner hướng dẫn -->
      <div class="mb-4 p-3 bg-indigo-50 border border-indigo-200 rounded-xl flex items-start gap-3">
        <v-icon icon="mdi-flash-circle" color="indigo" class="mt-0.5" />
        <div class="text-xs text-indigo-900 leading-relaxed">
          <strong class="font-semibold">Tiết kiệm thời gian:</strong> Nhập khoảng số phòng cần tạo (ví dụ từ 201 đến 215), hệ thống sẽ tự động tính toán tọa độ lưới để hiển thị ngay ngắn trên Sơ đồ mặt bằng (Map View). Các số phòng đã tồn tại sẽ được tự động bỏ qua để tránh trùng lặp.
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <!-- Khu vực / Block -->
        <div>
          <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
            Khu vực / Block *
          </label>
          <v-combobox
            v-model="form.block"
            :items="existingBlocks"
            placeholder="Chọn hoặc nhập tên Block..."
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
            Tầng áp dụng *
          </label>
          <v-text-field
            v-model.number="form.floor"
            type="number"
            min="1"
            max="100"
            placeholder="2"
            :rules="[rules.required]"
            hide-details="auto"
            variant="outlined"
            density="compact"
            color="primary"
            prepend-inner-icon="mdi-stairs"
            @update:model-value="onFloorChange"
          />
        </div>

        <!-- Tiền tố tên phòng -->
        <div>
          <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
            Tiền tố tên hiển thị
          </label>
          <v-text-field
            v-model="form.prefix"
            placeholder="Phòng "
            hide-details="auto"
            variant="outlined"
            density="compact"
            color="primary"
            prepend-inner-icon="mdi-format-title"
          />
        </div>

        <!-- Hạng phòng -->
        <div>
          <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
            Hạng phòng mặc định
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

        <!-- Số phòng bắt đầu -->
        <div>
          <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
            Từ số phòng *
          </label>
          <v-text-field
            v-model.number="form.start_number"
            type="number"
            min="1"
            placeholder="201"
            :rules="[rules.required]"
            hide-details="auto"
            variant="outlined"
            density="compact"
            color="primary"
            prepend-inner-icon="mdi-numeric-1-box-outline"
          />
        </div>

        <!-- Số phòng kết thúc -->
        <div>
          <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
            Đến số phòng *
          </label>
          <v-text-field
            v-model.number="form.end_number"
            type="number"
            min="1"
            placeholder="215"
            :rules="[rules.required]"
            hide-details="auto"
            variant="outlined"
            density="compact"
            color="primary"
            prepend-inner-icon="mdi-numeric-9-plus-box-outline"
          />
        </div>

        <!-- Trạng thái phòng -->
        <div>
          <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
            Trạng thái ban đầu
          </label>
          <v-select
            v-model="form.status"
            :items="RoomStatusOptions"
            item-title="label"
            item-value="value"
            hide-details="auto"
            variant="outlined"
            density="compact"
            color="primary"
            prepend-inner-icon="mdi-shield-check-outline"
          />
        </div>

        <!-- Giá thuê mặc định -->
        <div>
          <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
            Giá thuê mặc định (VNĐ)
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
      </div>

      <!-- Preview kết quả dự kiến -->
      <div class="mt-4 p-3 bg-slate-50 border border-slate-200 rounded-xl">
        <div class="flex items-center justify-between text-xs font-medium text-slate-600 mb-2">
          <span>Dự kiến tạo: <strong class="text-indigo-600 font-bold text-sm">{{ calculatedCount }}</strong> phòng</span>
          <span class="text-slate-400">Vị trí: {{ form.block }} - Tầng {{ form.floor }}</span>
        </div>
        <div class="flex flex-wrap items-center gap-1.5">
          <span
            v-for="(num, idx) in previewSamples"
            :key="idx"
            class="px-2 py-0.5 rounded text-xs font-medium"
            :class="num === '...' ? 'text-slate-400' : 'bg-white border border-slate-200 text-slate-700 shadow-sm'"
          >
            {{ num === '...' ? '...' : (form.prefix || '') + num }}
          </span>
        </div>
      </div>
    </v-form>

    <template #actions>
      <div class="flex items-center justify-end gap-2 w-full">
        <AppButton variant="secondary" :disabled="submitting" @click="emit('update:modelValue', false)">
          Hủy bỏ
        </AppButton>
        <AppButton
          variant="primary"
          :loading="submitting"
          :disabled="calculatedCount <= 0"
          @click="handleSubmit"
        >
          <v-icon start icon="mdi-flash" />
          Tạo ngay {{ calculatedCount }} phòng
        </AppButton>
      </div>
    </template>
  </AppModal>
</template>
