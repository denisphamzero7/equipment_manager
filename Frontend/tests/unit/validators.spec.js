import { describe, it, expect } from 'vitest'
import { rules } from '@/core/helpers/validators'

describe('Validators Helper', () => {
  it('validates required fields', () => {
    expect(rules.required('test')).toBe(true)
    expect(rules.required('')).toContain('bắt buộc')
    expect(rules.required(null)).toContain('bắt buộc')
    expect(rules.required([])).toContain('bắt buộc')
  })

  it('validates email formats', () => {
    expect(rules.email('admin@example.com')).toBe(true)
    expect(rules.email('user@sub.domain.vn')).toBe(true)
    expect(rules.email('invalid-email')).toContain('Email không hợp lệ')
    expect(rules.email('admin@')).toContain('Email không hợp lệ')
  })

  it('validates minLength and maxLength', () => {
    const min6 = rules.minLength(6, 'Mật khẩu')
    expect(min6('123456')).toBe(true)
    expect(min6('12345')).toContain('ít nhất 6 ký tự')

    const max10 = rules.maxLength(10, 'Tên')
    expect(max10('1234567890')).toBe(true)
    expect(max10('12345678901')).toContain('không được vượt quá 10 ký tự')
  })

  it('validates serial numbers', () => {
    expect(rules.serialNumber('TV-LG-43_001')).toBe(true)
    expect(rules.serialNumber('SERIAL123')).toBe(true)
    expect(rules.serialNumber('TV@LG#43')).toContain('Số Serial chỉ chứa')
  })
})
