import { beforeEach, describe, expect, it, vi } from 'vitest'

const { postMock, responseUseMock } = vi.hoisted(() => ({
  postMock: vi.fn(),
  responseUseMock: vi.fn(),
}))

vi.mock('@/core/helpers/toast', () => ({
  toast: {
    success: vi.fn(),
    error: vi.fn(),
    warning: vi.fn(),
    info: vi.fn(),
  },
}))

vi.mock('@/core/services/requestCache', () => ({
  getCachedRequest: vi.fn(),
  invalidateRequestCache: vi.fn(),
}))

describe('Excel import service resilience', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    vi.resetModules()
  })

  it('sends room and equipment Excel imports with _skipRetry enabled', async () => {
    vi.doMock('@/core/api/axiosClient', () => ({
      default: {
        post: postMock.mockResolvedValue({ data: { success: true, data: { success_count: 1, failed_count: 0 } } }),
        get: vi.fn(),
      },
    }))

    const { roomService } = await import('@/modules/room/services/roomService')
    const { equipmentService } = await import('@/modules/equipment/services/equipmentService')

    const file = new File(['abc'], 'rooms.xlsx')
    await roomService.importExcel(file)
    await equipmentService.importExcel(file)

    expect(postMock).toHaveBeenNthCalledWith(
      1,
      '/api/motel/import',
      expect.any(FormData),
      expect.objectContaining({ _skipRetry: true })
    )
    expect(postMock).toHaveBeenNthCalledWith(
      2,
      '/api/equipments/import',
      expect.any(FormData),
      expect.objectContaining({ _skipRetry: true })
    )
  })

  it('extracts string or array validation errors cleanly in axiosClient 422 interceptor', async () => {
    vi.doUnmock('@/core/api/axiosClient')
    vi.doMock('axios', () => ({
      default: {
        create: () => ({
          interceptors: {
            request: { use: vi.fn() },
            response: { use: responseUseMock },
          },
        }),
      },
    }))

    await import('@/core/api/axiosClient')
    const { toast } = await import('@/core/helpers/toast')

    const onError = responseUseMock.mock.calls[0][1]

    await expect(onError({
      config: { _skipRetry: true },
      response: {
        status: 422,
        data: {
          message: 'Fallback',
          errors: { file: ['Tệp Excel không đúng mẫu nhập Phòng.'] },
        },
      },
    })).rejects.toBeDefined()
    expect(toast.error).toHaveBeenLastCalledWith('Tệp Excel không đúng mẫu nhập Phòng.')

    await expect(onError({
      config: { _skipRetry: true },
      response: {
        status: 422,
        data: {
          message: 'Fallback',
          errors: ['Lỗi dạng chuỗi'],
        },
      },
    })).rejects.toBeDefined()
    expect(toast.error).toHaveBeenLastCalledWith('Lỗi dạng chuỗi')
  })
})
