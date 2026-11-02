<script setup>
import { ref, computed, watch } from 'vue'
import AppModal from '@/core/components/AppModal.vue'
import AppButton from '@/core/components/AppButton.vue'

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  roomNumber: {
    type: [String, Number],
    default: '',
  },
  warehouseEquipments: {
    type: Array,
    default: () => [],
  },
  warehouseLoading: {
    type: Boolean,
    default: false,
  },
  actionLoading: {
    type: Boolean,
    default: false,
  },
  suggestedPositions: {
    type: Array,
    default: () => [],
  },
  targetCategoryCode: {
    type: String,
    default: '',
  },
  missingEquipments: {
    type: Array,
    default: () => [],
  },
  standardModelIds: {
    type: Array,
    default: () => [],
  },
})

const emit = defineEmits(['update:modelValue', 'submit'])

const selectedWarehouseEquipmentIds = ref([])
const warehousePositionNote = ref('')
const activeCategory = ref('all')
const searchQuery = ref('')

/**
 * [1. MỤC ĐÍCH]
 * Ánh xạ mã phân loại thiết bị sang biểu tượng giao diện MDI tương ứng.
 *
 * [2. LUỒNG XỬ LÝ (FLOW)]
 * 1. Chuyển chuỗi `code` về chữ thường.
 * 2. Tìm kiếm trong bảng map icon.
 * 3. Trả về tên icon hoặc 'mdi-devices' nếu chưa định nghĩa.
 *
 * [3. NGUỒN GỌI (CALLERS / TRIGGERED FROM)]
 * - Hàm `categoryTabs` để hiển thị icon cho từng tab danh mục trong modal.
 *
 * [4. THAM SỐ (PARAMETERS)]
 * @param {string} code Mã nhóm thiết bị (tv, remote, box, wifi...).
 *
 * [5. GIÁ TRỊ TRẢ VỀ & NGOẠI LỆ]
 * @return {string} Icon MDI.
 *
 * [6. VÍ DỤ SỬ DỤNG]
 * @example
 * getCategoryIcon('tv') // => 'mdi-television'
 */
function getCategoryIcon(code) {
  const map = {
    tv: 'mdi-television',
    remote: 'mdi-remote',
    box: 'mdi-set-top-box',
    wifi: 'mdi-wifi',
    phone: 'mdi-phone-classic',
    ac: 'mdi-air-conditioner',
    refrigerator: 'mdi-fridge',
    kettle: 'mdi-kettle-outline',
    safe: 'mdi-safe',
    hairdryer: 'mdi-hair-dryer',
  }
  return map[code?.toLowerCase()] || 'mdi-devices'
}

/**
 * [1. MỤC ĐÍCH]
 * Trích xuất an toàn mã định danh (`code`) và tên gọi (`name`) của danh mục thiết bị từ object thiết bị kho.
 *
 * [2. LUỒNG XỬ LÝ (FLOW)]
 * 1. Đọc thuộc tính lồng nhau từ `item.model.category` hoặc `item.category`.
 * 2. Đặt giá trị mặc định là 'other' và 'Chưa phân loại' nếu thiết bị thiếu dữ liệu danh mục.
 * 3. Trả về object `{ code, name }`.
 *
 * [3. NGUỒN GỌI (CALLERS / TRIGGERED FROM)]
 * - Computed `categoryTabs` và `filteredEquipments`.
 *
 * [4. THAM SỐ (PARAMETERS)]
 * @param {Object} item Thiết bị kho.
 *
 * [5. GIÁ TRỊ TRẢ VỀ & NGOẠI LỆ]
 * @return {{code: string, name: string}}
 *
 * [6. VÍ DỤ SỬ DỤNG]
 * @example
 * const { code, name } = getItemCategory(item)
 */
function getItemCategory(item) {
  const code = item.model?.category?.code || item.category?.code || 'other'
  const name = item.model?.category?.name || item.category?.name || 'Chưa phân loại'
  return { code, name }
}

// Build category filter tabs with counts
const categoryTabs = computed(() => {
  const counts = {}
  props.warehouseEquipments.forEach((e) => {
    const { code, name } = getItemCategory(e)
    if (!counts[code]) {
      counts[code] = { code, name, count: 0, icon: getCategoryIcon(code) }
    }
    counts[code].count++
  })

  const tabs = [
    { code: 'all', name: 'Tất cả', count: props.warehouseEquipments.length, icon: 'mdi-apps' },
    ...Object.values(counts),
  ]

  return tabs
})

// Filtered items based on active tab and search query
const filteredEquipments = computed(() => {
  return props.warehouseEquipments.filter((item) => {
    const { code } = getItemCategory(item)
    if (activeCategory.value !== 'all' && code !== activeCategory.value) {
      return false
    }

    if (searchQuery.value) {
      const q = searchQuery.value.trim().toLowerCase()
      const serial = (item.serial_number || '').toLowerCase()
      const name = (item.model?.name || item.name || '').toLowerCase()
      if (!serial.includes(q) && !name.includes(q)) {
        return false
      }
    }

    return true
  })
})

// Missing items summary text
const missingSummary = computed(() => {
  const list = props.missingEquipments.filter(m => !m.is_cascade_error)
  if (!list.length) return ''
  return list.map(m => `${m.missing_count || 1} ${m.category_name || m.name}`).join(', ')
})

watch(
  () => props.modelValue,
  (val) => {
    if (val) {
      selectedWarehouseEquipmentIds.value = []
      warehousePositionNote.value = ''
      searchQuery.value = ''
      if (props.targetCategoryCode) {
        activeCategory.value = props.targetCategoryCode
      } else {
        activeCategory.value = 'all'
      }
    }
  }
)

/**
 * [1. MỤC ĐÍCH]
 * Bật/tắt chọn một thiết bị cụ thể trong kho để chuẩn bị gán vào phòng.
 *
 * [2. LUỒNG XỬ LÝ (FLOW)]
 * 1. Tìm vị trí `id` trong mảng `selectedWarehouseEquipmentIds`.
 * 2. Nếu chưa có: thêm vào mảng bằng `push(id)`.
 * 3. Nếu đã có: loại bỏ bằng `splice(idx, 1)`.
 *
 * [3. NGUỒN GỌI (CALLERS / TRIGGERED FROM)]
 * - Sự kiện click vào thẻ thiết bị hoặc checkbox trong danh sách thiết bị kho.
 *
 * [4. THAM SỐ (PARAMETERS)]
 * @param {number|string} id ID của thiết bị kho.
 *
 * [5. GIÁ TRỊ TRẢ VỀ & NGOẠI LỆ]
 * @return {void}
 *
 * [6. VÍ DỤ SỬ DỤNG]
 * @example
 * toggleSelect(45)
 */
function toggleSelect(id) {
  const idx = selectedWarehouseEquipmentIds.value.indexOf(id)
  if (idx === -1) {
    selectedWarehouseEquipmentIds.value.push(id)
  } else {
    selectedWarehouseEquipmentIds.value.splice(idx, 1)
  }
}

/**
 * [1. MỤC ĐÍCH]
 * Chọn nhanh tất cả thiết bị đang thỏa mãn điều kiện lọc và tìm kiếm hiện tại.
 *
 * [2. LUỒNG XỬ LÝ (FLOW)]
 * 1. Lấy danh sách ID từ `filteredEquipments.value`.
 * 2. Đưa vào `Set` để tránh trùng lặp với các thiết bị đã chọn từ trước.
 * 3. Chuyển Set về mảng và cập nhật `selectedWarehouseEquipmentIds`.
 *
 * [3. NGUỒN GỌI (CALLERS / TRIGGERED FROM)]
 * - Nút "Chọn tất cả" trong thanh công cụ chọn của modal.
 *
 * [4. THAM SỐ (PARAMETERS)]
 * Không có.
 *
 * [5. GIÁ TRỊ TRẢ VỀ & NGOẠI LỆ]
 * @return {void}
 *
 * [6. VÍ DỤ SỬ DỤNG]
 * @example
 * selectAllFiltered()
 */
function selectAllFiltered() {
  const ids = filteredEquipments.value.map(e => e.id)
  const currentSet = new Set(selectedWarehouseEquipmentIds.value)
  ids.forEach(id => currentSet.add(id))
  selectedWarehouseEquipmentIds.value = Array.from(currentSet)
}

/**
 * [1. MỤC ĐÍCH]
 * Xóa toàn bộ danh sách các thiết bị đã chọn trong kho về mảng rỗng.
 *
 * [2. LUỒNG XỬ LÝ (FLOW)]
 * 1. Gán `selectedWarehouseEquipmentIds.value = []`.
 *
 * [3. NGUỒN GỌI (CALLERS / TRIGGERED FROM)]
 * - Nút "Bỏ chọn tất cả" trong thanh công cụ chọn của modal.
 *
 * [4. THAM SỐ (PARAMETERS)]
 * Không có.
 *
 * [5. GIÁ TRỊ TRẢ VỀ & NGOẠI LỆ]
 * @return {void}
 *
 * [6. VÍ DỤ SỬ DỤNG]
 * @example
 * clearSelection()
 */
function clearSelection() {
  selectedWarehouseEquipmentIds.value = []
}

/**
 * [1. MỤC ĐÍCH]
 * Xác nhận gán các thiết bị đã chọn từ kho vào phòng và phát sự kiện `submit` lên view cha.
 *
 * [2. LUỒNG XỬ LÝ (FLOW)]
 * 1. Kiểm tra danh sách thiết bị đã chọn `selectedWarehouseEquipmentIds.length`. Nếu rỗng thì bỏ qua.
 * 2. Emit sự kiện `submit` với payload gồm mảng `equipment_ids`, `equipment_id` đầu tiên, và ghi chú vị trí `position_note`.
 *
 * [3. NGUỒN GỌI (CALLERS / TRIGGERED FROM)]
 * - Nút "Xác nhận gán vào phòng" ở footer của modal.
 *
 * [4. THAM SỐ (PARAMETERS)]
 * Không có.
 *
 * [5. GIÁ TRỊ TRẢ VỀ & NGOẠI LỆ]
 * @return {void}
 *
 * [6. VÍ DỤ SỬ DỤNG]
 * @example
 * handleSubmit()
 */
function handleSubmit() {
  if (!selectedWarehouseEquipmentIds.value.length) return
  emit('submit', {
    equipment_ids: selectedWarehouseEquipmentIds.value,
    equipment_id: selectedWarehouseEquipmentIds.value[0],
    position_note: warehousePositionNote.value || 'Gán trực tiếp vào phòng',
  })
}
</script>

<template>
  <AppModal
    :model-value="modelValue"
    title="Gán & Bù Thiết Bị từ Kho vào Phòng"
    :subtitle="`Phòng đích: Phòng ${roomNumber}`"
    max-width="680"
    @update:model-value="val => emit('update:modelValue', val)"
  >
    <div v-if="warehouseLoading" class="py-12 text-center text-slate-400 text-xs">
      <v-progress-circular indeterminate color="primary" size="36" />
      <p class="mt-2.5 font-medium">Đang tải danh sách thiết bị có sẵn trong kho...</p>
    </div>

    <div v-else class="space-y-4">
      <!-- Missing Requirements Banner -->
      <div v-if="missingSummary" class="p-3 bg-rose-50 border border-rose-200 rounded-xl flex items-center justify-between gap-3 text-xs">
        <div class="flex items-center gap-2">
          <v-icon icon="mdi-alert-circle" size="18" class="text-rose-600 shrink-0" />
          <span class="text-rose-900 font-medium">
            Phòng đang thiếu: <strong class="font-bold text-rose-700">{{ missingSummary }}</strong>
          </span>
        </div>
        <button
          v-if="targetCategoryCode"
          type="button"
          class="text-rose-600 hover:text-rose-800 font-bold underline shrink-0 cursor-pointer"
          @click="activeCategory = 'all'"
        >
          Xem tất cả loại
        </button>
      </div>

      <!-- Category Filter Tabs -->
      <div class="flex items-center gap-1.5 overflow-x-auto pb-1 scrollbar-none">
        <button
          v-for="tab in categoryTabs"
          :key="tab.code"
          type="button"
          class="px-2.5 py-1.5 rounded-lg text-xs font-bold transition flex items-center gap-1.5 whitespace-nowrap cursor-pointer"
          :class="activeCategory === tab.code
            ? 'bg-primary text-white shadow-xs'
            : 'bg-slate-100 hover:bg-slate-200 text-slate-600'"
          @click="activeCategory = tab.code"
        >
          <v-icon :icon="tab.icon" size="14" />
          <span>{{ tab.name }}</span>
          <span
            class="px-1.5 py-0.2 rounded-full text-[10px] font-black"
            :class="activeCategory === tab.code ? 'bg-white/25 text-white' : 'bg-slate-200/80 text-slate-700'"
          >
            {{ tab.count }}
          </span>
        </button>
      </div>

      <!-- Search & Bulk select action bar -->
      <div class="flex items-center justify-between gap-2 pt-1">
        <div class="flex-1 max-w-sm">
          <v-text-field
            v-model="searchQuery"
            placeholder="Tìm theo Serial hoặc Tên model..."
            density="compact"
            variant="outlined"
            hide-details
            prepend-inner-icon="mdi-magnify"
            clearable
          />
        </div>
        <div class="flex items-center gap-2 text-xs">
          <button
            v-if="filteredEquipments.length > 0"
            type="button"
            class="text-primary hover:underline font-bold cursor-pointer"
            @click="selectAllFiltered"
          >
            Chọn tất cả ({{ filteredEquipments.length }})
          </button>
          <span v-if="selectedWarehouseEquipmentIds.length > 0" class="text-slate-300">•</span>
          <button
            v-if="selectedWarehouseEquipmentIds.length > 0"
            type="button"
            class="text-slate-500 hover:text-slate-800 font-medium cursor-pointer"
            @click="clearSelection"
          >
            Bỏ chọn
          </button>
        </div>
      </div>

      <!-- Equipment Selection List (Rich Cards with Checkboxes) -->
      <div class="border border-slate-200 rounded-xl overflow-hidden">
        <div class="max-h-[300px] overflow-y-auto divide-y divide-slate-100 pr-1 scrollbar-thin">
          <div
            v-if="filteredEquipments.length === 0"
            class="py-10 text-center text-slate-400 text-xs"
          >
            <v-icon icon="mdi-package-variant-closed" size="36" class="text-slate-300 mb-1.5 block mx-auto" />
            <p v-if="warehouseEquipments.length === 0">
              Kho hiện không còn thiết bị nào ở trạng thái sẵn sàng (in_stock).
            </p>
            <p v-else>
              Không tìm thấy thiết bị phù hợp với bộ lọc.
            </p>
          </div>

          <div
            v-for="item in filteredEquipments"
            :key="item.id"
            class="p-3 transition flex items-center justify-between gap-3 cursor-pointer select-none"
            :class="selectedWarehouseEquipmentIds.includes(item.id)
              ? 'bg-blue-50/70 border-l-4 border-l-primary'
              : 'hover:bg-slate-50'"
            @click="toggleSelect(item.id)"
          >
            <!-- Checkbox & Identity -->
            <div class="flex items-center gap-3 min-w-0">
              <input
                type="checkbox"
                :checked="selectedWarehouseEquipmentIds.includes(item.id)"
                class="w-4 h-4 rounded text-primary focus:ring-primary/20 border-slate-300 cursor-pointer shrink-0"
                @click.stop="toggleSelect(item.id)"
              />
              <div class="w-8 h-8 rounded-lg bg-slate-100 flex items-center justify-center shrink-0 text-slate-600">
                <v-icon :icon="getCategoryIcon(getItemCategory(item).code)" size="18" />
              </div>
              <div class="min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                  <span class="text-xs font-mono font-black text-slate-900">{{ item.serial_number || 'Chưa có Serial' }}</span>
                  <span class="px-2 py-0.2 rounded-full text-[10px] font-bold bg-slate-100 text-slate-600 border border-slate-200">
                    {{ getItemCategory(item).name }}
                  </span>
                  <span
                    v-if="standardModelIds.includes(item.equipment_model_id)"
                    class="px-2 py-0.2 rounded-full text-[10px] font-black bg-emerald-100 text-emerald-800 border border-emerald-300 flex items-center gap-0.5"
                  >
                    <v-icon icon="mdi-check-decagram" size="12" />
                    Đúng chuẩn định mức
                  </span>
                </div>
                <p class="text-xs text-slate-700 font-medium truncate mt-0.5">
                  {{ item.model?.name || item.name }}
                  <span v-if="item.model?.brand" class="text-slate-400 font-normal">({{ item.model.brand }})</span>
                </p>
              </div>
            </div>

            <!-- Selection Indicator -->
            <div class="shrink-0">
              <span
                v-if="selectedWarehouseEquipmentIds.includes(item.id)"
                class="px-2 py-1 rounded-lg text-[11px] font-black bg-primary text-white"
              >
                Đã chọn
              </span>
              <span
                v-else
                class="px-2 py-1 rounded-lg text-[11px] font-semibold text-slate-400 border border-slate-200 hover:border-slate-300"
              >
                Chọn
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- Suggested Positions & Note -->
      <div>
        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
          Ghi chú vị trí lắp đặt trong phòng (tùy chọn)
        </label>
        <v-text-field
          v-model="warehousePositionNote"
          placeholder="Ví dụ: Kệ tivi phòng khách, bàn làm việc, phòng ngủ 1..."
          density="compact"
          variant="outlined"
          hide-details
          prepend-inner-icon="mdi-map-marker-outline"
        />

        <div v-if="suggestedPositions.length" class="flex items-center gap-1.5 flex-wrap mt-2">
          <span class="text-[11px] text-slate-400 font-medium">Gợi ý nhanh:</span>
          <button
            v-for="pos in suggestedPositions"
            :key="pos"
            type="button"
            class="px-2 py-0.5 rounded-full text-[11px] font-medium transition cursor-pointer"
            :class="warehousePositionNote === pos ? 'bg-primary text-white font-bold' : 'bg-slate-100 hover:bg-slate-200 text-slate-700'"
            @click="warehousePositionNote = pos"
          >
            {{ pos }}
          </button>
        </div>
      </div>
    </div>

    <template #footer>
      <div class="flex items-center justify-between w-full">
        <div class="text-xs font-bold text-slate-700">
          <template v-if="selectedWarehouseEquipmentIds.length > 0">
            Đã chọn: <strong class="text-primary text-sm font-black">{{ selectedWarehouseEquipmentIds.length }}</strong> thiết bị
          </template>
          <template v-else>
            <span class="text-slate-400 font-normal">Chưa chọn thiết bị nào</span>
          </template>
        </div>

        <div class="flex items-center gap-2">
          <AppButton
            variant="secondary"
            :disabled="actionLoading"
            @click="emit('update:modelValue', false)"
          >
            Hủy bỏ
          </AppButton>
          <AppButton
            variant="primary"
            :loading="actionLoading"
            :disabled="selectedWarehouseEquipmentIds.length === 0"
            @click="handleSubmit"
          >
            <v-icon start icon="mdi-check-all" />
            Gán vào phòng {{ selectedWarehouseEquipmentIds.length > 1 ? `(${selectedWarehouseEquipmentIds.length} món)` : '' }}
          </AppButton>
        </div>
      </div>
    </template>
  </AppModal>
</template>
