<script setup>
import { computed, ref, watch } from 'vue'
import AppButton from './AppButton.vue'
import AppModal from './AppModal.vue'
import { toast } from '@/core/helpers/toast'

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

watch(open, isOpen => {
  if (!isOpen) {
    selectedFile.value = null
    result.value = null
    importError.value = ''
  }
})

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
  return responseData?.message || error.message || 'Không thể hoàn tất thao tác Excel.'
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

async function importFile() {
  if (!selectedFile.value) {
    importError.value = 'Vui lòng chọn tệp Excel hoặc CSV.'
    return
  }

  importing.value = true
  importError.value = ''
  try {
    result.value = await props.importer(selectedFile.value)
    emit('imported', resultData.value)
    selectedFile.value = null
    if (fileInput.value) fileInput.value.value = ''
    toast.success(result.value?.message || 'Đã hoàn tất nhập dữ liệu.')
  } catch (error) {
    console.error('Excel import failed:', error)
    importError.value = error.response?.data?.message || error.message || 'Không thể nhập dữ liệu.'
  } finally {
    importing.value = false
  }
}
</script>

<template>
  <div>
    <AppButton
      variant="secondary"
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
        <section v-if="allowExport" class="rounded-xl border border-slate-200 bg-slate-50 p-4">
          <h4 class="text-sm font-bold text-slate-800">Xuất dữ liệu</h4>
          <p class="mt-1 text-xs leading-5 text-slate-600">
            Tệp xuất theo bộ lọc hiện tại; nếu chưa lọc, hệ thống xuất toàn bộ dữ liệu của tổ chức.
          </p>
          <div class="mt-3 flex flex-col gap-2 sm:flex-row">
            <AppButton
              variant="primary"
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

        <section v-if="allowImport" class="rounded-xl border border-slate-200 p-4">
          <h4 class="text-sm font-bold text-slate-800">Nhập dữ liệu</h4>
          <p class="mt-1 text-xs leading-5 text-slate-600">
            Dòng có mã định danh trùng sẽ được bỏ qua và ghi rõ trong kết quả; dữ liệu hiện có không bị ghi đè.
          </p>
          <input
            ref="fileInput"
            type="file"
            accept=".xlsx,.xls,.csv"
            class="mt-3 block w-full cursor-pointer rounded-lg border border-slate-300 bg-white p-2 text-xs text-slate-700 file:mr-3 file:rounded-md file:border-0 file:bg-blue-50 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-blue-700"
            :disabled="importing || exporting"
            @change="selectedFile = $event.target.files?.[0] || null; result = null; importError = ''"
          />
          <p class="mt-1 text-xs text-slate-500">Hỗ trợ .xlsx, .xls, .csv (tối đa 10 MB).</p>
          <p v-if="importError" role="alert" class="mt-2 text-sm text-rose-700">
            {{ importError }}
          </p>
          <div v-if="result" class="mt-3 rounded-lg bg-emerald-50 p-3 text-sm text-emerald-900">
            <p>
              Nhập thành công: <strong>{{ resultData.success_count ?? 0 }}</strong>;
              bỏ qua / lỗi: <strong>{{ resultData.failed_count ?? 0 }}</strong>.
            </p>
            <ul v-if="errors.length" class="mt-2 max-h-36 list-inside list-disc space-y-1 overflow-y-auto text-xs">
              <li v-for="(error, index) in errors" :key="`${index}-${error}`">{{ error }}</li>
            </ul>
          </div>
          <div class="mt-3 flex justify-end">
            <AppButton
              variant="primary"
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
        <AppButton variant="secondary" @click="open = false">Đóng</AppButton>
      </template>
    </AppModal>
  </div>
</template>
