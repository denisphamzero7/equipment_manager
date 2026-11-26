<script setup>
import { computed, ref, watch } from 'vue'
import AppButton from './AppButton.vue'
import AppModal from './AppModal.vue'
import { toast } from '@/core/helpers/toast'
import { extractFirstValidationError } from '@/core/helpers/validators'

const ALLOWED_EXTENSIONS = ['.xlsx', '.xls', '.csv']
const MAX_FILE_SIZE_BYTES = 10 * 1024 * 1024

const props = defineProps({
  title: {
    type: String,
    required: true,
  },
  description: {
    type: String,
    required: true,
  },
  exporter: {
    type: Function,
    required: true,
  },
  importer: {
    type: Function,
    required: true,
  },
  filters: {
    type: Object,
    default: () => ({}),
  },
  allowImport: {
    type: Boolean,
    default: true,
  },
  allowExport: {
    type: Boolean,
    default: true,
  },
})

const emit = defineEmits(['imported'])

const open = ref(false)
const selectedFile = ref(null)
const fileInput = ref(null)
const exporting = ref(false)
const importing = ref(false)
const result = ref(null)
const importError = ref('')

const resultData = computed(() => result.value?.data || result.value || {})
const errors = computed(() => Array.isArray(resultData.value.errors) ? resultData.value.errors : [])
const successCount = computed(() => Number(resultData.value.success_count ?? 0))
const failedCount = computed(() => Number(resultData.value.failed_count ?? 0))
const hasResultSummary = computed(() => (
  Boolean(result.value) && (successCount.value > 0 || failedCount.value > 0 || errors.value.length > 0)
))
const resultBoxClass = computed(() => {
  if (successCount.value === 0) {
    return 'border-rose-200 bg-rose-50 text-rose-900 dark:border-rose-800 dark:bg-rose-950/40 dark:text-rose-200'
  }
  if (failedCount.value > 0) {
    return 'border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200'
  }
  return 'border-emerald-200 bg-emerald-50 text-emerald-900 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200'
})

watch(open, isOpen => {
  if (!isOpen) {
    resetSelection()
    result.value = null
    importError.value = ''
  }
})

function resetSelection() {
  selectedFile.value = null
  if (fileInput.value) fileInput.value.value = ''
}

function validateSelectedFile(file) {
  if (!file) {
    return 'Vui lòng chọn tệp Excel hoặc CSV.'
  }

  const fileName = (file.name || '').toLowerCase()
  const hasValidExtension = ALLOWED_EXTENSIONS.some(ext => fileName.endsWith(ext))
  if (!hasValidExtension) {
    return 'Định dạng tệp không hợp lệ. Chỉ hỗ trợ tệp .xlsx, .xls hoặc .csv.'
  }

  if (typeof file.size === 'number' && file.size === 0) {
    return 'Tệp được chọn đang trống (0 byte). Vui lòng kiểm tra lại.'
  }

  if (typeof file.size === 'number' && file.size > MAX_FILE_SIZE_BYTES) {
    return 'Dung lượng tệp không được vượt quá 10 MB.'
  }

  return ''
}

function handleFileChange(event) {
  const file = event.target?.files?.[0] || null
  result.value = null
  importError.value = ''

  if (!file) {
    selectedFile.value = null
    return
  }

  const validationMessage = validateSelectedFile(file)
  if (validationMessage) {
    resetSelection()
    importError.value = validationMessage
    toast.error(validationMessage)
    return
  }

  selectedFile.value = file
}

function saveBlob(blob, filename) {
  if (!(blob instanceof Blob)) {
    throw new Error('Máy chủ không trả về tệp Excel hợp lệ.')
  }

  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = filename
  document.body.append(link)
  link.click()
  link.remove()
  window.setTimeout(() => URL.revokeObjectURL(url), 0)
}

async function getRequestErrorMessage(error) {
  const responseData = error.response?.data
  if (responseData instanceof Blob) {
    try {
      const payload = JSON.parse(await responseData.text())
      if (payload.message) return payload.message
    } catch (parseError) {
      console.error('Could not read Excel request error response:', parseError)
    }
  }
  if (!responseData?.data?.errors?.length) {
    const firstError = extractFirstValidationError(responseData?.errors)
    if (firstError) return firstError
  }
  if (error.code === 'ECONNABORTED') {
    return 'Quá thời gian xử lý tệp Excel. Vui lòng kiểm tra lại dữ liệu tệp.'
  }
  if (!error.response && error.message === 'Network Error') {
    return 'Không thể tải tệp lên máy chủ (lỗi kết nối hoặc tệp vừa được lưu lại trên máy sau khi chọn). Vui lòng bấm "Chọn tệp" để chọn lại rồi thử lại.'
  }
  return responseData?.message || error.message || 'Không thể hoàn tất thao tác Excel.'
}

async function ensureFileReadable(file) {
  if (!file || typeof file.slice !== 'function') return ''

  try {
    const chunk = file.slice(0, 1)
    if (typeof chunk.arrayBuffer === 'function') {
      await chunk.arrayBuffer()
    }
    return ''
  } catch (error) {
    console.warn('Selected file is no longer readable:', error)
    return 'Tệp vừa bị thay đổi hoặc lưu lại trên máy sau khi chọn. Vui lòng bấm "Chọn tệp" để chọn lại phiên bản mới nhất.'
  }
}

async function downloadExport(template = false) {
  exporting.value = true
  try {
    const blob = await props.exporter(
      template ? { template: 1 } : { ...props.filters }
    )
    const suffix = template ? '-mau' : ''
    saveBlob(blob, `${props.title.toLocaleLowerCase('vi').replaceAll(' ', '-')}${suffix}.xlsx`)
    if (!template) toast.success('Đã tải tệp Excel.')
  } catch (error) {
    console.error('Excel export failed:', error)
    toast.error(await getRequestErrorMessage(error))
  } finally {
    exporting.value = false
  }
}

function applyImportResult(payload) {
  const data = payload?.data || payload || {}
  const importedSuccessCount = Number(data.success_count ?? 0)
  const importedFailedCount = Number(data.failed_count ?? 0)

  if (importedSuccessCount === 0 && importedFailedCount > 0) {
    result.value = payload
    importError.value = payload?.message || `Nhập dữ liệu thất bại (${importedFailedCount} dòng lỗi / bỏ qua).`
    toast.error(importError.value)
    return
  }

  if (importedSuccessCount === 0 && importedFailedCount === 0) {
    importError.value = 'Tệp Excel không có dữ liệu hợp lệ để nhập.'
    toast.error(importError.value)
    return
  }

  result.value = payload
  emit('imported', data)
  resetSelection()

  if (importedFailedCount > 0) {
    toast.warning(payload?.message || 'Đã nhập dữ liệu (có một số dòng bị bỏ qua/lỗi).')
    return
  }

  toast.success(payload?.message || 'Đã hoàn tất nhập dữ liệu.')
}

async function applyImportFailure(error) {
  console.error('Excel import failed:', error)
  const responseData = error.response?.data
  if (responseData?.data) {
    result.value = responseData
  }
  if (!error.response && error.message === 'Network Error') {
    resetSelection()
  }
  importError.value = await getRequestErrorMessage(error)
  if (!error.response) {
    toast.error(importError.value)
  }
}

async function importFile() {
  const validationMessage = validateSelectedFile(selectedFile.value)
  if (validationMessage) {
    importError.value = validationMessage
    toast.error(validationMessage)
    return
  }

  const readabilityMessage = await ensureFileReadable(selectedFile.value)
  if (readabilityMessage) {
    resetSelection()
    importError.value = readabilityMessage
    toast.error(readabilityMessage)
    return
  }

  importing.value = true
  importError.value = ''
  result.value = null
  try {
    const payload = await props.importer(selectedFile.value)
    applyImportResult(payload)
  } catch (error) {
    await applyImportFailure(error)
  } finally {
    importing.value = false
  }
}
</script>

<template>
  <div>
    <AppButton
      variant="tonal"
      color="secondary"
      icon="mdi-microsoft-excel"
      class="w-full sm:w-auto"
      @click="open = true"
    >
      Excel
    </AppButton>

    <AppModal
      v-model="open"
      :title="`Nhập / Xuất Excel - ${title}`"
      :subtitle="description"
      max-width="600"
    >
      <div class="space-y-4">
        <section v-if="allowExport" class="rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800/40">
          <h4 class="text-sm font-bold text-slate-800 dark:text-slate-100">Xuất dữ liệu</h4>
          <p class="mt-1 text-xs leading-5 text-slate-600 dark:text-slate-400">
            Tệp xuất theo bộ lọc hiện tại; nếu chưa lọc, hệ thống xuất toàn bộ dữ liệu của tổ chức.
          </p>
          <div class="mt-3 flex flex-col gap-2 sm:flex-row">
            <AppButton
              variant="flat"
              color="primary"
              icon="mdi-download"
              :loading="exporting"
              :disabled="exporting || importing"
              class="w-full sm:w-auto"
              @click="downloadExport(false)"
            >
              Xuất theo bộ lọc
            </AppButton>
            <AppButton
              v-if="allowImport"
              variant="outlined"
              color="primary"
              icon="mdi-file-download-outline"
              :loading="exporting"
              :disabled="exporting || importing"
              class="w-full sm:w-auto"
              @click="downloadExport(true)"
            >
              Tải mẫu nhập
            </AppButton>
          </div>
        </section>

        <section v-if="allowImport" class="rounded-xl border border-slate-200 p-4 dark:border-slate-700">
          <h4 class="text-sm font-bold text-slate-800 dark:text-slate-100">Nhập dữ liệu</h4>
          <p class="mt-1 text-xs leading-5 text-slate-600 dark:text-slate-400">
            Dòng có mã định danh trùng sẽ được bỏ qua và ghi rõ trong kết quả; dữ liệu hiện có không bị ghi đè.
          </p>
          <input
            ref="fileInput"
            type="file"
            accept=".xlsx,.xls,.csv"
            class="mt-3 block w-full cursor-pointer rounded-lg border border-slate-300 bg-white p-2 text-xs text-slate-700 file:mr-3 file:rounded-md file:border-0 file:bg-blue-50 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-blue-700 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200"
            :disabled="importing || exporting"
            @change="handleFileChange"
          />
          <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Hỗ trợ .xlsx, .xls, .csv (tối đa 10 MB).</p>
          <div
            v-if="importError"
            role="alert"
            class="mt-2 flex items-start gap-2 rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm text-rose-800 dark:border-rose-800 dark:bg-rose-950/40 dark:text-rose-200"
          >
            <v-icon icon="mdi-alert-circle-outline" size="18" class="mt-0.5 shrink-0" />
            <span>{{ importError }}</span>
          </div>
          <div
            v-if="hasResultSummary"
            class="mt-3 rounded-lg border p-3 text-sm"
            :class="resultBoxClass"
          >
            <p>
              Nhập thành công: <strong>{{ successCount }}</strong>;
              bỏ qua / lỗi: <strong>{{ failedCount }}</strong>.
            </p>
            <ul v-if="errors.length" class="mt-2 max-h-36 list-inside list-disc space-y-1 overflow-y-auto text-xs">
              <li v-for="(error, index) in errors" :key="`${index}-${error}`">{{ error }}</li>
            </ul>
          </div>
          <div class="mt-3 flex justify-end">
            <AppButton
              variant="flat"
              color="primary"
              icon="mdi-upload"
              :loading="importing"
              :disabled="!selectedFile || importing || exporting"
              class="w-full sm:w-auto"
              @click="importFile"
            >
              Nhập tệp
            </AppButton>
          </div>
        </section>
      </div>

      <template #footer>
        <AppButton variant="tonal" color="secondary" @click="open = false">Đóng</AppButton>
      </template>
    </AppModal>
  </div>
</template>
