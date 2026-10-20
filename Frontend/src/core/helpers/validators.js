/**
 * Reusable form validation rules for Vuetify inputs.
 */
export const rules = {
  required: (v, fieldName = 'Trường này') => {
    if (v === null || v === undefined) return `${fieldName} là bắt buộc.`
    if (typeof v === 'string' && v.trim() === '') return `${fieldName} là bắt buộc.`
    if (Array.isArray(v) && v.length === 0) return `${fieldName} là bắt buộc.`
    return true
  },

  email: v => {
    if (!v) return true
    const pattern = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/
    return pattern.test(v) || 'Email không hợp lệ.'
  },

  minLength: (min, fieldName = 'Trường này') => v => {
    if (!v) return true
    return v.length >= min || `${fieldName} phải có ít nhất ${min} ký tự.`
  },

  maxLength: (max, fieldName = 'Trường này') => v => {
    if (!v) return true
    return v.length <= max || `${fieldName} không được vượt quá ${max} ký tự.`
  },

  numeric: v => {
    if (!v) return true
    return !isNaN(parseFloat(v)) && isFinite(v) || 'Giá trị phải là số.'
  },

  serialNumber: v => {
    if (!v) return true
    const pattern = /^[a-zA-Z0-9_-]+$/
    return pattern.test(v) || 'Số Serial chỉ chứa chữ cái, chữ số, dấu gạch nối hoặc gạch dưới.'
  },
}
