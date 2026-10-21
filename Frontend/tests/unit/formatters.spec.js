import { describe, it, expect } from 'vitest'
import { formatDate, formatCurrencyVND, formatNumber, truncateText, resolveMediaUrl } from '@/core/helpers/formatters'

describe('Formatters Helper', () => {
  it('formats date to DD/MM/YYYY correctly', () => {
    const formatted = formatDate('2026-10-20T10:00:00Z', 'DD/MM/YYYY')
    expect(formatted).toMatch(/20\/10\/2026/)
  })

  it('handles null/invalid date gracefully', () => {
    expect(formatDate(null)).toBe('—')
    expect(formatDate('invalid-date')).toBe('—')
  })

  it('formats VND currency correctly', () => {
    const result = formatCurrencyVND(250000)
    expect(result).toContain('250.000')
    expect(result).toContain('₫')
  })

  it('handles null/undefined currency', () => {
    expect(formatCurrencyVND(null)).toBe('0 ₫')
  })

  it('formats numbers with thousand separators', () => {
    expect(formatNumber(12500)).toBe('12.500')
    expect(formatNumber(0)).toBe('0')
  })

  it('truncates text with ellipsis', () => {
    expect(truncateText('Hệ thống Quản lý Trang thiết bị Khách sạn', 10)).toBe('Hệ thống Q...')
    expect(truncateText('Ngắn', 10)).toBe('Ngắn')
  })

  it('resolves and normalizes media URLs correctly', () => {
    expect(resolveMediaUrl('')).toBe('')
    expect(resolveMediaUrl('http://localhost/laravel_hq/quandh-core/public/storage/1/photo.jpg')).toBe('/storage/1/photo.jpg')
    expect(resolveMediaUrl('http://localhost/storage/1/photo.jpg')).toBe('/storage/1/photo.jpg')
    expect(resolveMediaUrl('https://cdn.example.com/photo.jpg')).toBe('https://cdn.example.com/photo.jpg')
  })
})
