import { reactive } from 'vue'

export const toastState = reactive({
  show: false,
  message: '',
  color: 'success', // success | error | warning | info
  icon: 'mdi-check-circle',
  timeout: 4000,
})

export const toast = {
  success(message, timeout = 3500) {
    toastState.message = message
    toastState.color = 'success'
    toastState.icon = 'mdi-check-circle'
    toastState.timeout = timeout
    toastState.show = true
  },

  error(message, timeout = 5000) {
    toastState.message = message || 'Có lỗi xảy ra, vui lòng thử lại!'
    toastState.color = 'error'
    toastState.icon = 'mdi-alert-circle'
    toastState.timeout = timeout
    toastState.show = true
  },

  warning(message, timeout = 4000) {
    toastState.message = message
    toastState.color = 'warning'
    toastState.icon = 'mdi-alert'
    toastState.timeout = timeout
    toastState.show = true
  },

  info(message, timeout = 3500) {
    toastState.message = message
    toastState.color = 'info'
    toastState.icon = 'mdi-information'
    toastState.timeout = timeout
    toastState.show = true
  },
}
