import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import AppExcelTransfer from '@/core/components/AppExcelTransfer.vue'

vi.mock('@/core/helpers/toast', () => ({
  toast: {
    success: vi.fn(),
    error: vi.fn(),
    warning: vi.fn(),
    info: vi.fn(),
  },
}))

import { toast } from '@/core/helpers/toast'

const AppModalStub = {
  props: ['modelValue', 'title', 'subtitle', 'maxWidth'],
  emits: ['update:modelValue', 'close'],
  template: '<div v-if="modelValue"><slot /><slot name="footer" /></div>',
}

const AppButtonStub = {
  props: ['variant', 'color', 'icon', 'loading', 'disabled', 'size'],
  emits: ['click'],
  template: '<button type="button" :disabled="disabled || loading" @click="$emit(\'click\', $event)"><slot /></button>',
}

function mountTransfer(overrides = {}) {
  const exporter = overrides.exporter || vi.fn().mockResolvedValue(new Blob(['ok']))
  const importer = overrides.importer || vi.fn().mockResolvedValue({
    message: 'Nhập dữ liệu hoàn tất: Thành công 2, Bỏ qua / thất bại 0.',
    data: { success_count: 2, failed_count: 0, errors: [] },
  })

  const wrapper = mount(AppExcelTransfer, {
    props: {
      title: 'Phòng',
      description: 'Mô tả test',
      exporter,
      importer,
      ...overrides,
    },
    global: {
      stubs: {
        AppModal: AppModalStub,
        AppButton: AppButtonStub,
        'v-icon': { template: '<span />' },
      },
    },
  })

  return { wrapper, exporter, importer }
}

async function openModal(wrapper) {
  const triggerBtn = wrapper.findAll('button').find(btn => btn.text().includes('Excel'))
  await triggerBtn.trigger('click')
}

async function selectFile(wrapper, file) {
  const input = wrapper.find('input[type="file"]')
  Object.defineProperty(input.element, 'files', {
    value: file ? [file] : [],
    configurable: true,
  })
  await input.trigger('change')
}

describe('AppExcelTransfer', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('rejects unsupported file extensions immediately on selection', async () => {
    const { wrapper, importer } = mountTransfer()
    await openModal(wrapper)

    const invalidFile = new File(['hello'], 'notes.pdf', { type: 'application/pdf' })
    await selectFile(wrapper, invalidFile)

    const alert = wrapper.find('[role="alert"]')
    expect(alert.exists()).toBe(true)
    expect(alert.text()).toContain('Định dạng tệp không hợp lệ')
    expect(toast.error).toHaveBeenCalledOnce()
    expect(importer).not.toHaveBeenCalled()

    const importBtn = wrapper.findAll('button').find(btn => btn.text().includes('Nhập tệp'))
    expect(importBtn.attributes('disabled')).toBeDefined()
  })

  it('rejects 0-byte and oversized files immediately on selection', async () => {
    const { wrapper, importer } = mountTransfer()
    await openModal(wrapper)

    const emptyFile = new File([], 'empty.xlsx')
    await selectFile(wrapper, emptyFile)
    expect(wrapper.find('[role="alert"]').text()).toContain('đang trống (0 byte)')

    const hugeFile = { name: 'huge.xlsx', size: 11 * 1024 * 1024 }
    await selectFile(wrapper, hugeFile)
    expect(wrapper.find('[role="alert"]').text()).toContain('10 MB')
    expect(importer).not.toHaveBeenCalled()
  })

  it('displays backend 422 template mismatch and row error details without emitting imported', async () => {
    const importer = vi.fn().mockRejectedValue({
      response: {
        status: 422,
        data: {
          message: 'Nhập dữ liệu thất bại: không có dòng nào hợp lệ (2 dòng lỗi / bỏ qua).',
          errors: {
            file: ['Dòng 2: Số phòng không được để trống.'],
          },
          data: {
            success_count: 0,
            failed_count: 2,
            errors: [
              'Dòng 2: Số phòng không được để trống.',
              'Dòng 3: Trạng thái phòng không hợp lệ.',
            ],
          },
        },
      },
    })

    const { wrapper } = mountTransfer({ importer })
    await openModal(wrapper)
    await selectFile(wrapper, new File(['content'], 'thiet-bi-dien-thoai-ban.xlsx'))

    const importBtn = wrapper.findAll('button').find(btn => btn.text().includes('Nhập tệp'))
    await importBtn.trigger('click')
    await flushPromises()

    expect(importer).toHaveBeenCalledOnce()
    expect(wrapper.emitted('imported')).toBeUndefined()
    expect(wrapper.find('[role="alert"]').text()).toContain('không có dòng nào hợp lệ')
    expect(wrapper.text()).toContain('Dòng 2: Số phòng không được để trống.')
    expect(wrapper.text()).toContain('Dòng 3: Trạng thái phòng không hợp lệ.')
    expect(toast.success).not.toHaveBeenCalled()
  })

  it('treats a 200 payload with 0 successes and >0 failures as an error without emitting imported', async () => {
    const importer = vi.fn().mockResolvedValue({
      message: 'Nhập dữ liệu hoàn tất: Thành công 0, Bỏ qua / thất bại 1.',
      data: {
        success_count: 0,
        failed_count: 1,
        errors: ['Dòng 2: Dữ liệu sai'],
      },
    })

    const { wrapper } = mountTransfer({ importer })
    await openModal(wrapper)
    await selectFile(wrapper, new File(['content'], 'rooms.xlsx'))

    const importBtn = wrapper.findAll('button').find(btn => btn.text().includes('Nhập tệp'))
    await importBtn.trigger('click')
    await flushPromises()

    expect(wrapper.emitted('imported')).toBeUndefined()
    expect(wrapper.find('[role="alert"]').exists()).toBe(true)
    expect(toast.error).toHaveBeenCalledOnce()
    expect(toast.success).not.toHaveBeenCalled()
  })

  it('shows warning toast for partial success and success toast for clean import', async () => {
    const partialImporter = vi.fn().mockResolvedValue({
      message: 'Thành công 1, Thất bại 1',
      data: { success_count: 1, failed_count: 1, errors: ['Dòng 3: Lỗi'] },
    })

    const { wrapper } = mountTransfer({ importer: partialImporter })
    await openModal(wrapper)
    await selectFile(wrapper, new File(['content'], 'rooms.xlsx'))
    await wrapper.findAll('button').find(btn => btn.text().includes('Nhập tệp')).trigger('click')
    await flushPromises()

    expect(wrapper.emitted('imported')).toHaveLength(1)
    expect(toast.warning).toHaveBeenCalledWith('Thành công 1, Thất bại 1')

    const cleanSetup = mountTransfer()
    await openModal(cleanSetup.wrapper)
    await selectFile(cleanSetup.wrapper, new File(['content'], 'rooms.xlsx'))
    await cleanSetup.wrapper.findAll('button').find(btn => btn.text().includes('Nhập tệp')).trigger('click')
    await flushPromises()

    expect(cleanSetup.wrapper.emitted('imported')).toHaveLength(1)
    expect(toast.success).toHaveBeenCalledOnce()
  })

  it('detects a stale file modified on disk before uploading and resets selection', async () => {
    const { wrapper, importer } = mountTransfer()
    await openModal(wrapper)

    const staleFile = {
      name: 'thiet-bi-dien-thoai-ban.xlsx',
      size: 128,
      slice: () => ({
        arrayBuffer: () => Promise.reject(new DOMException('File changed on disk', 'NotReadableError')),
      }),
    }
    await selectFile(wrapper, staleFile)

    await wrapper.findAll('button').find(btn => btn.text().includes('Nhập tệp')).trigger('click')
    await flushPromises()

    expect(importer).not.toHaveBeenCalled()
    expect(wrapper.find('[role="alert"]').text()).toContain('chọn lại phiên bản mới nhất')
    expect(toast.error).toHaveBeenCalledOnce()
  })

  it('translates Axios Network Error into clear Vietnamese re-select guidance', async () => {
    const importer = vi.fn().mockRejectedValue(new Error('Network Error'))
    const { wrapper } = mountTransfer({ importer })
    await openModal(wrapper)
    await selectFile(wrapper, new File(['content'], 'thiet-bi-dien-thoai-ban.xlsx'))

    await wrapper.findAll('button').find(btn => btn.text().includes('Nhập tệp')).trigger('click')
    await flushPromises()

    expect(wrapper.find('[role="alert"]').text()).toContain('lỗi kết nối hoặc tệp vừa được lưu lại')
    expect(toast.error).toHaveBeenCalledOnce()
  })
})
