<script setup>
import { ref, watch, computed, onMounted } from 'vue'
import AppModal from '@/core/components/AppModal.vue'
import AppButton from '@/core/components/AppButton.vue'
import { RoomStatusOptions } from '@/core/enums/RoomStatus'
import { rules } from '@/core/helpers/validators'
import { roomService } from '../services/roomService'
import { equipmentService } from '@/modules/equipment/services/equipmentService'
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

const availableRooms = ref([])

onMounted(async () => {
  try {
    const list = await equipmentService.getRoomOptions()
    availableRooms.value = Array.isArray(list) ? list : []
  } catch (err) {
    console.error('Failed to load room options for wifi sharing:', err)
  }
})

const wifiPartnerRooms = computed(() => {
  const currentId = props.room?.id
  return availableRooms.value
    .filter(r => !currentId || r.id !== currentId)
    .map(r => ({
      value: r.id,
      title: `Phòng ${r.room_number} (${r.block || 'Chính'} - T.${r.floor})`,
    }))
})

const form = ref({
  room_number: '',
  name: '',
  block: 'Block A',
  floor: 1,
  room_type_id: null,
  price: 0,
  status: 'ready',
  bedroom_count: 1,
  living_room_count: 0,
  wifi_mode: 'dedicated',
  shared_wifi_room_id: null,
  shared_wifi_note: '',
  grid_row: 1,
  grid_col: 1,
  description: '',
})

const totalTvRequired = computed(() => {
  const bn = parseInt(form.value.bedroom_count, 10) || 0
  const ln = parseInt(form.value.living_room_count, 10) || 0
  return Math.max(1, bn + ln)
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
        bedroom_count: val.bedroom_count ?? 1,
        living_room_count: val.living_room_count ?? 0,
        wifi_mode: val.wifi_mode || 'dedicated',
        shared_wifi_room_id: val.shared_wifi_room_id || null,
        shared_wifi_note: val.shared_wifi_note || '',
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
        bedroom_count: 1,
        living_room_count: 0,
        wifi_mode: 'dedicated',
        shared_wifi_room_id: null,
        shared_wifi_note: '',
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
      bedroom_count: Math.max(0, parseInt(form.value.bedroom_count, 10) || 0),
      living_room_count: Math.max(0, parseInt(form.value.living_room_count, 10) || 0),
      wifi_mode: form.value.wifi_mode || 'dedicated',
      shared_wifi_room_id: form.value.wifi_mode === 'shared' ? (form.value.shared_wifi_room_id || null) : null,
      shared_wifi_note: form.value.wifi_mode === 'shared' ? (form.value.shared_wifi_note || '') : null,
      grid_row: parseInt(form.value.grid_row, 10) || parseInt(form.value.floor, 10) || 1,
      grid_col: parseInt(form.value.grid_col, 10) || 1,
      price: parseFloat(form.value.price) || 0,
      room_type_id: form.value.room_type_id ? parseInt(form.value.room_type_id, 10) : null,
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
    max-width="700"
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

        <!-- KHỐI CƠ CẤU KHÔNG GIAN & CHẾ ĐỘ WIFI -->
        <div class="md:col-span-2 p-4 rounded-xl bg-slate-50 border border-slate-200/80 space-y-3.5">
          <div class="flex items-center justify-between">
            <h4 class="text-xs font-bold text-slate-800 uppercase tracking-wider flex items-center gap-1.5">
              <v-icon icon="mdi-home-analytics" size="18" color="primary" />
              <span>Cơ cấu không gian & Định mức thiết bị</span>
            </h4>
            <span class="text-[11px] font-semibold text-blue-700 bg-blue-100/70 px-2 py-0.5 rounded-full">
              Tự động tính định mức TV / Wifi
            </span>
          </div>

          <!-- Bộ đếm PN & PK -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <!-- Phòng ngủ -->
            <div class="bg-white p-3 rounded-lg border border-slate-200 flex items-center justify-between">
              <div>
                <span class="block text-xs font-bold text-slate-800 flex items-center gap-1">
                  <v-icon icon="mdi-bed" size="16" class="text-blue-600" />
                  Số phòng ngủ
                </span>
                <span class="text-[11px] text-slate-400">1 Tivi + 1 Remote / phòng</span>
              </div>
              <div class="flex items-center gap-1">
                <button
                  type="button"
                  class="w-7 h-7 rounded border border-slate-200 bg-slate-50 hover:bg-slate-100 text-slate-600 font-bold flex items-center justify-center transition active:scale-95 disabled:opacity-40 cursor-pointer"
                  :disabled="form.bedroom_count <= 0"
                  @click="form.bedroom_count = Math.max(0, form.bedroom_count - 1)"
                >
                  -
                </button>
                <input
                  v-model.number="form.bedroom_count"
                  type="number"
                  min="0"
                  max="20"
                  class="w-10 text-center font-bold text-slate-800 text-sm border-0 focus:outline-none focus:ring-1 focus:ring-primary rounded"
                />
                <button
                  type="button"
                  class="w-7 h-7 rounded border border-slate-200 bg-slate-50 hover:bg-slate-100 text-slate-600 font-bold flex items-center justify-center transition active:scale-95 cursor-pointer"
                  @click="form.bedroom_count++"
                >
                  +
                </button>
              </div>
            </div>

            <!-- Phòng khách -->
            <div class="bg-white p-3 rounded-lg border border-slate-200 flex items-center justify-between">
              <div>
                <span class="block text-xs font-bold text-slate-800 flex items-center gap-1">
                  <v-icon icon="mdi-sofa" size="16" class="text-indigo-600" />
                  Số phòng khách
                </span>
                <span class="text-[11px] text-slate-400">1 Tivi + 1 Remote / phòng</span>
              </div>
              <div class="flex items-center gap-1">
                <button
                  type="button"
                  class="w-7 h-7 rounded border border-slate-200 bg-slate-50 hover:bg-slate-100 text-slate-600 font-bold flex items-center justify-center transition active:scale-95 disabled:opacity-40 cursor-pointer"
                  :disabled="form.living_room_count <= 0"
                  @click="form.living_room_count = Math.max(0, form.living_room_count - 1)"
                >
                  -
                </button>
                <input
                  v-model.number="form.living_room_count"
                  type="number"
                  min="0"
                  max="20"
                  class="w-10 text-center font-bold text-slate-800 text-sm border-0 focus:outline-none focus:ring-1 focus:ring-primary rounded"
                />
                <button
                  type="button"
                  class="w-7 h-7 rounded border border-slate-200 bg-slate-50 hover:bg-slate-100 text-slate-600 font-bold flex items-center justify-center transition active:scale-95 cursor-pointer"
                  @click="form.living_room_count++"
                >
                  +
                </button>
              </div>
            </div>
          </div>

          <!-- Banner xem trước định mức Tivi / Remote -->
          <div class="p-2.5 rounded-lg bg-blue-50/80 border border-blue-200 text-xs text-blue-900 flex items-center gap-2">
            <v-icon icon="mdi-television-box" color="primary" size="20" />
            <div>
              <strong>Định mức tự động:</strong> Cần <strong>{{ totalTvRequired }} Tivi</strong> &amp; <strong>{{ totalTvRequired }} Remote</strong>
              <span class="text-blue-700 ml-1">({{ form.bedroom_count }} PN + {{ form.living_room_count }} PK)</span>
            </div>
          </div>

          <!-- Cấu hình Wifi -->
          <div class="space-y-2 pt-1 border-t border-slate-200/60">
            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">
              Chế độ phát sóng Wifi
            </label>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
              <label
                class="flex items-center gap-2 p-2.5 rounded-lg border cursor-pointer text-xs transition"
                :class="form.wifi_mode === 'dedicated' ? 'bg-white border-primary shadow-2xs font-bold text-primary ring-1 ring-primary' : 'bg-white/60 border-slate-200 text-slate-600 hover:bg-white'"
              >
                <input type="radio" v-model="form.wifi_mode" value="dedicated" class="hidden" />
                <v-icon icon="mdi-wifi" size="18" :color="form.wifi_mode === 'dedicated' ? 'primary' : 'secondary'" />
                <span>Router riêng (1 bộ)</span>
              </label>

              <label
                class="flex items-center gap-2 p-2.5 rounded-lg border cursor-pointer text-xs transition"
                :class="form.wifi_mode === 'shared' ? 'bg-white border-purple-500 shadow-2xs font-bold text-purple-700 ring-1 ring-purple-500' : 'bg-white/60 border-slate-200 text-slate-600 hover:bg-white'"
              >
                <input type="radio" v-model="form.wifi_mode" value="shared" class="hidden" />
                <v-icon icon="mdi-wifi-arrow-left-right" size="18" :color="form.wifi_mode === 'shared' ? 'purple' : 'secondary'" />
                <span>Dùng ké phòng khác</span>
              </label>

              <label
                class="flex items-center gap-2 p-2.5 rounded-lg border cursor-pointer text-xs transition"
                :class="form.wifi_mode === 'none' ? 'bg-white border-slate-500 shadow-2xs font-bold text-slate-700 ring-1 ring-slate-400' : 'bg-white/60 border-slate-200 text-slate-600 hover:bg-white'"
              >
                <input type="radio" v-model="form.wifi_mode" value="none" class="hidden" />
                <v-icon icon="mdi-wifi-off" size="18" :color="form.wifi_mode === 'none' ? 'slate' : 'secondary'" />
                <span>Không có Wifi</span>
              </label>
            </div>

            <!-- Trường chọn phòng dùng ké khi wifi_mode === 'shared' -->
            <div v-if="form.wifi_mode === 'shared'" class="grid grid-cols-1 sm:grid-cols-2 gap-2 mt-2 pt-2 bg-purple-50/60 p-2.5 rounded-lg border border-purple-200">
              <div>
                <label class="block text-[11px] font-bold text-purple-900 mb-1">
                  Phát từ phòng liền kề
                </label>
                <v-autocomplete
                  v-model="form.shared_wifi_room_id"
                  :items="wifiPartnerRooms"
                  item-title="title"
                  item-value="value"
                  placeholder="Chọn phòng phát Wifi..."
                  density="compact"
                  variant="outlined"
                  hide-details
                  bg-color="white"
                  clearable
                />
              </div>
              <div>
                <label class="block text-[11px] font-bold text-purple-900 mb-1">
                  Ghi chú sóng / vị trí
                </label>
                <v-text-field
                  v-model="form.shared_wifi_note"
                  placeholder="Ví dụ: Dùng ké Wifi phòng 122 sát vách"
                  density="compact"
                  variant="outlined"
                  hide-details
                  bg-color="white"
                />
              </div>
              <div class="sm:col-span-2 text-[11px] text-purple-800">
                ✨ Phòng dùng ké sẽ <strong>không bị cảnh báo thiếu Wifi Router</strong> trên sơ đồ đối soát định mức.
              </div>
            </div>
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

    <template #footer>
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
