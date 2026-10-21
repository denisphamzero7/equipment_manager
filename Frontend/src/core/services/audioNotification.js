/**
 * Web Audio API synthesized soft chime notification.
 * Hoạt động mượt mà không cần tải file âm thanh ngoài (.mp3/.wav).
 */
export function playNotificationSound(type = 'info') {
  try {
    const AudioContext = window.AudioContext || window.webkitAudioContext
    if (!AudioContext) return

    const ctx = new AudioContext()
    const now = ctx.currentTime

    // Tạo âm thanh chuông 2 giai điệu (two-tone chime)
    const osc1 = ctx.createOscillator()
    const osc2 = ctx.createOscillator()
    const gainNode = ctx.createGain()

    osc1.type = 'sine'
    osc2.type = 'sine'

    if (type === 'urgent') {
      // Âm thanh cảnh báo khẩn cấp: 587Hz (D5) -> 880Hz (A5)
      osc1.frequency.setValueAtTime(587.33, now)
      osc2.frequency.setValueAtTime(880, now + 0.12)
    } else {
      // Âm thanh thông báo bình thường dễ chịu: 523Hz (C5) -> 659Hz (E5)
      osc1.frequency.setValueAtTime(523.25, now)
      osc2.frequency.setValueAtTime(659.25, now + 0.1)
    }

    gainNode.gain.setValueAtTime(0.08, now)
    gainNode.gain.exponentialRampToValueAtTime(0.001, now + 0.45)

    osc1.connect(gainNode)
    osc2.connect(gainNode)
    gainNode.connect(ctx.destination)

    osc1.start(now)
    osc1.stop(now + 0.15)

    osc2.start(now + 0.1)
    osc2.stop(now + 0.45)
  } catch (e) {
    // Audio autoplay có thể bị chặn bởi browser nếu user chưa tương tác
  }
}
