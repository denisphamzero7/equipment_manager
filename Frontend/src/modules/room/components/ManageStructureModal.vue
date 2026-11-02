<script setup>
import { ref, reactive, computed } from 'vue'
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
  roomTypes: {
    type: Array,
    default: () => [],
  },
})

const emit = defineEmits(['update:modelValue', 'updated'])

const activeTab = ref('create')
const submitting = ref(false)

// 1. Create Block state
const createFormRef = ref(null)
const createForm = reactive({
  blockName: '',
  floor: 1,
  roomCount: 5,
  room_type_id: null,
  price: 500000,
})

// 2. Rename state
const renameFormRef = ref(null)
const selectedOldBlock = ref('')
const newBlockName = ref('')

// 3. Delete state
const selectedDeleteBlock = ref('')
const deleteConfirmText = ref('')

/**
 * Danh sách phòng dự kiến sẽ được tự động khởi tạo khi tạo Block mới.
 */
const previewRooms = computed(() => {
  const floor = parseInt(createForm.floor, 10) || 1
  const count = parseInt(createForm.roomCount, 10) || 1
  const startNum = floor * 100 + 1
  const list = []
  for (let i = 0; i < Math.min(count, 8); i++) {
    list.push(`Phòng ${startNum + i}`)
  }
  if (count > 8) {
    list.push(`... (+${count - 8} phòng nữa)`)
  }
  return list
})

/**
 * [1. MỤC ĐÍCH]
 * Tạo mới một Khu vực / Tòa nhà (Block) đồng thời khởi tạo các phòng ban đầu thuộc Block đó.
 *
 * [2. LUỒNG XỬ LÝ (FLOW)]
 * 1. Validate form tạo mới qua `createFormRef`.
 * 2. Kiểm tra tên Block mới không được trùng với các Block đã có trong `existingBlocks`.
 * 3. Tính toán dải số phòng khởi tạo dựa trên tầng và số lượng phòng.
 * 4. Gọi API `roomService.batchGenerate` để tạo các phòng cho Block mới.
 * 5. Hiển thị thông báo thành công Toast.
 * 6. Reset form, phát sự kiện `@updated` và đóng modal.
 *
 * [3. NGUỒN GỌI (CALLERS / TRIGGERED FROM)]
 * - Nút "Tạo Block mới" tại Tab 1 của modal `ManageStructureModal.vue`.
 *
 * [4. THAM SỐ (PARAMETERS)]
 * Không có.
 *
 * [5. GIÁ TRỊ TRẢ VỀ & NGOẠI LỆ]
 * @return {Promise<void>}
 *
 * [6. VÍ DỤ SỬ DỤNG]
 * @example
 * await handleCreateBlock()
 */
async function handleCreateBlock() {
  const { valid } = await createFormRef.value.validate()
  if (!valid) return

  const cleanBlockName = createForm.blockName.trim()
  const isDuplicate = props.existingBlocks.some(
    b => String(b).trim().toLowerCase() === cleanBlockName.toLowerCase()
  )
  if (isDuplicate) {
    toast.error(`Khu vực "${cleanBlockName}" đã tồn tại trong hệ thống. Vui lòng đặt tên khác.`)
    return
  }

  const floor = parseInt(createForm.floor, 10) || 1
  const count = Math.min(50, Math.max(1, parseInt(createForm.roomCount, 10) || 1))
  const startNum = floor * 100 + 1
  const endNum = startNum + count - 1

  submitting.value = true
  try {
    await roomService.batchGenerate({
      block: cleanBlockName,
      floor,
      prefix: 'Phòng ',
      start_number: startNum,
      end_number: endNum,
      room_type_id: createForm.room_type_id ? parseInt(createForm.room_type_id, 10) : null,
      price: parseFloat(createForm.price) || 0,
      status: 'ready',
      bedroom_count: 1,
      living_room_count: 0,
      wifi_mode: 'dedicated',
      wifi_router_count: 1,
    })

    toast.success(`Đã thêm mới thành công Khu vực / Block "${cleanBlockName}" với ${count} phòng khởi tạo!`)
    createForm.blockName = ''
    createForm.floor = 1
    createForm.roomCount = 5
    createForm.room_type_id = null
    emit('updated')
    emit('update:modelValue', false)
  } catch (err) {
    const msg = err.response?.data?.message || err.message || 'Lỗi khi tạo mới khu vực.'
    toast.error(msg)
  } finally {
    submitting.value = false
  }
}

/**
 * [1. MỤC ĐÍCH]
 * Đổi tên một Khu vực / Block hiện tại và tự động cập nhật đồng bộ toàn bộ phòng thuộc Block đó.
 *
 * [2. LUỒNG XỬ LÝ (FLOW)]
 * 1. Validate form đổi tên qua `renameFormRef`.
 * 2. Kiểm tra tên mới phải khác tên hiện tại.
 * 3. Gọi API `roomService.renameBlock(oldBlock, newBlock)`.
 * 4. Hiển thị Toast thành công, reset form, phát sự kiện `@updated` và đóng modal.
 *
 * [3. NGUỒN GỌI (CALLERS / TRIGGERED FROM)]
 * - Nút "Đổi tên khu vực" tại Tab 2 của modal `ManageStructureModal.vue`.
 *
 * [4. THAM SỐ (PARAMETERS)]
 * Không có.
 *
 * [5. GIÁ TRỊ TRẢ VỀ & NGOẠI LỆ]
 * @return {Promise<void>}
 *
 * [6. VÍ DỤ SỬ DỤNG]
 * @example
 * await handleRename()
 */
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

/**
 * [1. MỤC ĐÍCH]
 * Xóa một Khu vực / Block và tự động thu hồi toàn bộ thiết bị đang gắn trong các phòng về lại kho.
 *
 * [2. LUỒNG XỬ LÝ (FLOW)]
 * 1. Kiểm tra khu vực cần xóa đã được chọn.
 * 2. Kiểm tra chuỗi nhập xác nhận bảo mật phải khớp chính xác với tên Block.
 * 3. Gọi API `roomService.deleteBlock(selectedDeleteBlock)`.
 * 4. Hiển thị Toast thông báo đã xóa và bảo toàn thiết bị.
 * 5. Reset form, phát sự kiện `@updated` và đóng modal.
 *
 * [3. NGUỒN GỌI (CALLERS / TRIGGERED FROM)]
 * - Nút "Xác nhận xóa khu vực" tại Tab 3 của modal `ManageStructureModal.vue`.
 *
 * [4. THAM SỐ (PARAMETERS)]
 * Không có.
 *
 * [5. GIÁ TRỊ TRẢ VỀ & NGOẠI LỆ]
 * @return {Promise<void>}
 *
 * [6. VÍ DỤ SỬ DỤNG]
 * @example
 * await handleDelete()
 */
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
    subtitle="Thêm khối nhà mới, đổi tên khu vực hoặc dọn dẹp cơ cấu tổ chức khách sạn"
    max-width="600"
    @update:model-value="val => emit('update:modelValue', val)"
  >
    <!-- Tab navigation: Thêm mới | Đổi tên | Xóa -->
    <div class="flex items-center border-b border-slate-200 mb-4 overflow-x-auto">
      <button
        class="px-4 py-2.5 text-xs sm:text-sm font-bold border-b-2 transition-colors duration-150 flex items-center gap-1.5 whitespace-nowrap cursor-pointer"
        :class="activeTab === 'create' ? 'border-primary text-primary' : 'border-transparent text-slate-500 hover:text-slate-800'"
        @click="activeTab = 'create'"
      >
        <v-icon icon="mdi-plus-circle-outline" size="18" />
        Thêm Khu vực / Block mới
      </button>

      <button
        class="px-4 py-2.5 text-xs sm:text-sm font-bold border-b-2 transition-colors duration-150 flex items-center gap-1.5 whitespace-nowrap cursor-pointer"
        :class="activeTab === 'rename' ? 'border-primary text-primary' : 'border-transparent text-slate-500 hover:text-slate-800'"
        @click="activeTab = 'rename'"
      >
        <v-icon icon="mdi-pencil-outline" size="18" />
        Đổi tên Khu vực
      </button>

      <button
        class="px-4 py-2.5 text-xs sm:text-sm font-bold border-b-2 transition-colors duration-150 flex items-center gap-1.5 whitespace-nowrap cursor-pointer"
        :class="activeTab === 'delete' ? 'border-rose-500 text-rose-600' : 'border-transparent text-slate-500 hover:text-slate-800'"
        @click="activeTab = 'delete'"
      >
        <v-icon icon="mdi-trash-can-outline" size="18" />
        Xóa Khu vực
      </button>
    </div>

    <!-- TAB 1: Thêm Block mới -->
    <div v-if="activeTab === 'create'">
      <v-form ref="createFormRef" @submit.prevent="handleCreateBlock">
        <div class="space-y-4">
          <div>
            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
              Tên Khu vực / Block mới *
            </label>
            <v-text-field
              v-model="createForm.blockName"
              placeholder="Ví dụ: Block E, Khu Condotel B, Khu Biệt Thự Villa..."
              :rules="[rules.required]"
              hide-details="auto"
              variant="outlined"
              density="compact"
              color="primary"
              prepend-inner-icon="mdi-domain-plus"
            />
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
            <div>
              <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                Tầng khởi tạo *
              </label>
              <v-text-field
                v-model.number="createForm.floor"
                type="number"
                min="1"
                max="50"
                placeholder="1"
                :rules="[rules.required, rules.numeric]"
                hide-details="auto"
                variant="outlined"
                density="compact"
                color="primary"
                prepend-inner-icon="mdi-stairs"
              />
            </div>

            <div>
              <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                Số lượng phòng ban đầu *
              </label>
              <v-text-field
                v-model.number="createForm.roomCount"
                type="number"
                min="1"
                max="30"
                placeholder="5"
                :rules="[rules.required, rules.numeric]"
                hide-details="auto"
                variant="outlined"
                density="compact"
                color="primary"
                prepend-inner-icon="mdi-door-open"
              />
            </div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
            <div>
              <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                Hạng / Loại phòng
              </label>
              <v-select
                v-model="createForm.room_type_id"
                :items="roomTypes"
                item-title="name"
                item-value="id"
                placeholder="Chọn loại phòng..."
                hide-details="auto"
                variant="outlined"
                density="compact"
                color="primary"
                clearable
                prepend-inner-icon="mdi-bed"
              />
            </div>

            <div>
              <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                Giá niêm yết (VNĐ)
              </label>
              <v-text-field
                v-model.number="createForm.price"
                type="number"
                min="0"
                step="50000"
                placeholder="500000"
                hide-details="auto"
                variant="outlined"
                density="compact"
                color="primary"
                prepend-inner-icon="mdi-currency-usd"
              />
            </div>
          </div>

          <!-- Preview các phòng sẽ được khởi tạo -->
          <div class="p-3 bg-blue-50/70 border border-blue-200/80 rounded-xl space-y-1.5">
            <div class="flex items-center gap-1.5 text-xs font-bold text-blue-900">
              <v-icon icon="mdi-eye-outline" size="16" color="primary" />
              <span>Xem trước các phòng khởi tạo thuộc {{ createForm.blockName.trim() || 'Block mới' }}:</span>
            </div>
            <div class="flex flex-wrap gap-1.5 pt-0.5">
              <span
                v-for="(pName, idx) in previewRooms"
                :key="idx"
                class="px-2 py-0.5 rounded-md text-[11px] font-mono font-semibold bg-white border border-blue-200 text-blue-700 shadow-2xs"
              >
                {{ pName }}
              </span>
            </div>
            <p class="text-[11px] text-blue-700/80 pt-1">
              * Khởi tạo ngay các phòng sẽ giúp Block mới hiển thị tức thì trên <strong>Sơ đồ mặt bằng (Map View)</strong> và <strong>Danh sách phòng</strong>.
            </p>
          </div>
        </div>
      </v-form>
    </div>

    <!-- TAB 2: Đổi tên Block -->
    <div v-else-if="activeTab === 'rename'">
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

    <!-- TAB 3: Xóa Block -->
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
          v-if="activeTab === 'create'"
          variant="primary"
          :loading="submitting"
          :disabled="!createForm.blockName.trim()"
          @click="handleCreateBlock"
        >
          <v-icon start icon="mdi-plus" />
          Tạo Khu vực & Khởi tạo phòng
        </AppButton>

        <AppButton
          v-else-if="activeTab === 'rename'"
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
