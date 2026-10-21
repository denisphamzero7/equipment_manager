/**
 * Format date, time, currency, numbers for Vietnamese locale.
 */
export function formatDate(dateString, format = 'DD/MM/YYYY') {
  if (!dateString) return '—'
  const date = new Date(dateString)
  if (isNaN(date.getTime())) return '—'

  const day = String(date.getDate()).padStart(2, '0')
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const year = date.getFullYear()
  const hours = String(date.getHours()).padStart(2, '0')
  const minutes = String(date.getMinutes()).padStart(2, '0')

  if (format === 'DD/MM/YYYY') {
    return `${day}/${month}/${year}`
  }
  if (format === 'DD/MM/YYYY HH:mm') {
    return `${day}/${month}/${year} ${hours}:${minutes}`
  }
  return date.toLocaleDateString('vi-VN')
}

export function formatCurrencyVND(amount) {
  if (amount === null || amount === undefined || isNaN(amount)) return '0 ₫'
  return new Intl.NumberFormat('vi-VN', {
    style: 'currency',
    currency: 'VND',
    maximumFractionDigits: 0,
  }).format(amount)
}

export const formatCurrency = formatCurrencyVND

export function formatNumber(val) {
  if (val === null || val === undefined || isNaN(val)) return '0'
  return new Intl.NumberFormat('vi-VN').format(val)
}

export function truncateText(text, length = 30) {
  if (!text) return ''
  return text.length > length ? text.substring(0, length) + '...' : text
}

export function resolveMediaUrl(url) {
  if (!url) return ''
  // 1. Clean up legacy subpath if present
  let cleanUrl = String(url).replace('/laravel_hq/quandh-core/public', '')

  // 2. Normalize full localhost/127.0.0.1 url on port 80 to relative /storage
  try {
    if (cleanUrl.startsWith('http://') || cleanUrl.startsWith('https://')) {
      const parsed = new URL(cleanUrl)
      if ((parsed.hostname === 'localhost' || parsed.hostname === '127.0.0.1') && (!parsed.port || parsed.port === '80')) {
        return parsed.pathname + parsed.search
      }
    }
  } catch {
    // fallback
  }

  return cleanUrl
}
