<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/authStore'
import { rules } from '@/core/helpers/validators'
import { toast } from '@/core/helpers/toast'
import AuthLayout from '@/core/layouts/AuthLayout.vue'

const router = useRouter()
const authStore = useAuthStore()

const formRef = ref(null)
const name = ref('')
const email = ref('')
const password = ref('')
const passwordConfirmation = ref('')
const showPassword = ref(false)

async function handleRegister() {
  const { valid } = await formRef.value.validate()
  if (!valid) return

  if (password.value !== passwordConfirmation.value) {
    toast.error('Mật khẩu xác nhận không trùng khớp!')
    return
  }

  try {
    await authStore.register({
      name: name.value,
      email: email.value,
      password: password.value,
      password_confirmation: passwordConfirmation.value,
    })
    toast.success('Đăng ký tài khoản thành công! Vui lòng đăng nhập.')
    router.push('/login')
  } catch (e) {
    // Handled by axios interceptor
  }
}
</script>

<template>
  <AuthLayout>
    <div class="bg-white rounded-3xl p-8 sm:p-10 border border-slate-200/80 shadow-xl shadow-slate-200/50">
      <div class="mb-8">
        <div class="w-12 h-12 rounded-2xl bg-indigo-600 text-white flex items-center justify-center mb-4 shadow-lg shadow-indigo-500/20">
          <v-icon icon="mdi-account-plus-outline" size="26" />
        </div>
        <h2 class="text-2xl font-extrabold text-slate-800 tracking-tight">
          Đăng ký tài khoản
        </h2>
        <p class="text-sm text-slate-500 mt-1">
          Tạo tài khoản quản trị viên mới cho hệ thống khách sạn
        </p>
      </div>

      <v-form ref="formRef" @submit.prevent="handleRegister">
        <div class="space-y-4">
          <div>
            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
              Họ và tên
            </label>
            <v-text-field
              v-model="name"
              placeholder="Nguyễn Văn A"
              prepend-inner-icon="mdi-account-outline"
              :rules="[rules.required]"
              hide-details="auto"
            />
          </div>

          <div>
            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
              Địa chỉ Email
            </label>
            <v-text-field
              v-model="email"
              placeholder="admin@hotel.com"
              prepend-inner-icon="mdi-email-outline"
              :rules="[rules.required, rules.email]"
              hide-details="auto"
            />
          </div>

          <div>
            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
              Mật khẩu
            </label>
            <v-text-field
              v-model="password"
              :type="showPassword ? 'text' : 'password'"
              placeholder="••••••••"
              prepend-inner-icon="mdi-lock-outline"
              :append-inner-icon="showPassword ? 'mdi-eye-off-outline' : 'mdi-eye-outline'"
              :rules="[rules.required, rules.minLength(6)]"
              hide-details="auto"
              @click:append-inner="showPassword = !showPassword"
            />
          </div>

          <div>
            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
              Xác nhận mật khẩu
            </label>
            <v-text-field
              v-model="passwordConfirmation"
              :type="showPassword ? 'text' : 'password'"
              placeholder="••••••••"
              prepend-inner-icon="mdi-lock-check-outline"
              :rules="[rules.required]"
              hide-details="auto"
            />
          </div>

          <v-btn
            type="submit"
            color="primary"
            block
            size="large"
            :loading="authStore.loading"
            class="mt-4 text-sm font-bold h-12"
          >
            Tạo tài khoản
          </v-btn>
        </div>
      </v-form>

      <div class="mt-8 pt-6 border-t border-slate-100 text-center text-xs text-slate-500">
        Đã có tài khoản?
        <router-link to="/login" class="font-bold text-blue-600 hover:text-blue-700 ml-1">
          Đăng nhập ngay
        </router-link>
      </div>
    </div>
  </AuthLayout>
</template>
