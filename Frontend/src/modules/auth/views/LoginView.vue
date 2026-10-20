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
const email = ref('admin@example.com')
const password = ref('quandcore**11')
const showPassword = ref(false)
const remember = ref(true)

async function handleLogin() {
  const { valid } = await formRef.value.validate()
  if (!valid) return

  try {
    await authStore.login({
      email: email.value,
      password: password.value,
    })
    toast.success('Đăng nhập thành công! Chào mừng trở lại.')
    router.push('/dashboard')
  } catch (e) {
    // Error notification handled by axios interceptor
  }
}

function fillDemoAccount() {
  email.value = 'admin@example.com'
  password.value = 'quandcore**11'
}
</script>

<template>
  <AuthLayout>
    <div class="bg-white rounded-3xl p-8 sm:p-10 border border-slate-200/80 shadow-xl shadow-slate-200/50">
      <div class="mb-8">
        <div class="w-12 h-12 rounded-2xl bg-blue-600 text-white flex items-center justify-center mb-4 shadow-lg shadow-blue-500/20">
          <v-icon icon="mdi-shield-lock-outline" size="26" />
        </div>
        <h2 class="text-2xl font-extrabold text-slate-800 tracking-tight">
          Đăng nhập hệ thống
        </h2>
        <p class="text-sm text-slate-500 mt-1">
          Nhập thông tin xác thực để truy cập quản lý trang thiết bị
        </p>
      </div>

      <!-- Quick Fill Demo Banner -->
      <div class="mb-6 p-3.5 bg-blue-50/70 border border-blue-100 rounded-xl flex items-center justify-between text-xs">
        <div class="flex items-center gap-2 text-blue-800 font-medium">
          <v-icon icon="mdi-information-outline" size="18" color="primary" />
          <span>Tài khoản Super Admin mặc định</span>
        </div>
        <button
          type="button"
          class="px-2.5 py-1 bg-blue-600 text-white rounded-lg font-bold hover:bg-blue-700 transition cursor-pointer"
          @click="fillDemoAccount"
        >
          Điền nhanh
        </button>
      </div>

      <v-form ref="formRef" @submit.prevent="handleLogin">
        <div class="space-y-4">
          <div>
            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
              Email / Tài khoản
            </label>
            <v-text-field
              v-model="email"
              placeholder="admin@example.com"
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

          <div class="flex items-center justify-between pt-1">
            <v-checkbox
              v-model="remember"
              label="Ghi nhớ đăng nhập"
              density="compact"
              color="primary"
              hide-details
            />
            <a href="#" class="text-xs font-semibold text-blue-600 hover:text-blue-700">
              Quên mật khẩu?
            </a>
          </div>

          <v-btn
            type="submit"
            color="primary"
            block
            size="large"
            :loading="authStore.loading"
            class="mt-2 text-sm font-bold h-12"
          >
            Đăng nhập ngay
          </v-btn>
        </div>
      </v-form>

      <div class="mt-8 pt-6 border-t border-slate-100 text-center text-xs text-slate-500">
        Chưa có tài khoản?
        <router-link to="/register" class="font-bold text-blue-600 hover:text-blue-700 ml-1">
          Đăng ký tài khoản mới
        </router-link>
      </div>
    </div>
  </AuthLayout>
</template>
