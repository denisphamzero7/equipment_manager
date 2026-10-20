<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/authStore'
import { rules } from '@/core/helpers/validators'
import { toast } from '@/core/helpers/toast'
import AuthLayout from '@/core/layouts/AuthLayout.vue'

const router = useRouter()
const authStore = useAuthStore()

// Step state: 'credentials' | 'select_org'
const step = ref('credentials')

// Step 1: Credentials
const formRef = ref(null)
const email = ref('admin@example.com')
const password = ref('quandcore**11')
const showPassword = ref(false)
const remember = ref(true)

// Step 2: Organizations
const availableOrgs = ref([])
const selectedOrgId = ref(null)
const selectingOrg = ref(false)

async function handleLogin() {
  const { valid } = await formRef.value.validate()
  if (!valid) return

  try {
    const result = await authStore.login({
      email: email.value,
      password: password.value,
    })

    const orgs = result.availableOrganizations || []
    availableOrgs.value = orgs
    selectedOrgId.value = result.currentOrganizationId || orgs[0]?.id || 22

    if (orgs.length > 0) {
      // Chuyển sang Bước 2: Hiển thị danh sách tổ chức để người dùng chọn
      step.value = 'select_org'
      toast.info('Xác thực tài khoản thành công! Vui lòng chọn tổ chức làm việc.')
    } else {
      // Nếu user chưa được gán tổ chức nào
      toast.warning('Tài khoản chưa được phân vào tổ chức nào. Vui lòng liên hệ quản trị.')
      router.push('/dashboard')
    }
  } catch (e) {
    // Error notification handled by axios interceptor
  }
}

async function handleConfirmOrganization() {
  if (!selectedOrgId.value) {
    toast.warning('Vui lòng chọn một tổ chức để tiếp tục.')
    return
  }

  selectingOrg.value = true
  try {
    await authStore.selectOrganization(selectedOrgId.value)
    const orgName = availableOrgs.value.find(o => o.id === selectedOrgId.value)?.name || `#${selectedOrgId.value}`
    toast.success(`Đăng nhập thành công vào tổ chức "${orgName}"!`)
    router.push('/dashboard')
  } catch (e) {
    console.error(e)
  } finally {
    selectingOrg.value = false
  }
}

function handleBackToCredentials() {
  step.value = 'credentials'
}

function fillDemoAccount() {
  email.value = 'admin@example.com'
  password.value = 'quandcore**11'
}
</script>

<template>
  <AuthLayout>
    <div class="bg-white rounded-3xl p-8 sm:p-10 border border-slate-200/80 shadow-xl shadow-slate-200/50">
      
      <!-- ================= STEP 1: FORM ĐĂNG NHẬP ================= -->
      <div v-if="step === 'credentials'">
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
              Tiếp tục
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

      <!-- ================= STEP 2: CHỌN TỔ CHỨC LÀM VIỆC ================= -->
      <div v-else-if="step === 'select_org'">
        <div class="mb-6">
          <div class="w-12 h-12 rounded-2xl bg-indigo-600 text-white flex items-center justify-center mb-4 shadow-lg shadow-indigo-500/20">
            <v-icon icon="mdi-office-building" size="26" />
          </div>
          <h2 class="text-2xl font-extrabold text-slate-800 tracking-tight">
            Chọn Tổ chức làm việc
          </h2>
          <p class="text-sm text-slate-500 mt-1">
            Tài khoản của bạn có quyền truy cập vào các tổ chức / chi nhánh dưới đây:
          </p>
        </div>

        <!-- Organization List -->
        <div class="space-y-3 mb-6 max-h-72 overflow-y-auto pr-1">
          <div
            v-for="org in availableOrgs"
            :key="org.id"
            class="p-4 rounded-2xl border-2 transition-all cursor-pointer flex items-center justify-between"
            :class="selectedOrgId === org.id 
              ? 'border-blue-600 bg-blue-50/60 shadow-sm' 
              : 'border-slate-200 hover:border-slate-300 hover:bg-slate-50/70'"
            @click="selectedOrgId = org.id"
          >
            <div class="flex items-center gap-3.5">
              <div
                class="w-10 h-10 rounded-xl flex items-center justify-center transition"
                :class="selectedOrgId === org.id ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-600'"
              >
                <v-icon icon="mdi-domain" size="22" />
              </div>
              <div>
                <p class="font-bold text-slate-800 text-sm">
                  {{ org.name }}
                </p>
                <p class="text-xs text-slate-400 mt-0.5">
                  {{ org.description || 'Chi nhánh / Khách sạn thành viên' }} • ID #{{ org.id }}
                </p>
              </div>
            </div>

            <!-- Radio indicator -->
            <div
              class="w-5 h-5 rounded-full border-2 flex items-center justify-center"
              :class="selectedOrgId === org.id ? 'border-blue-600 bg-blue-600 text-white' : 'border-slate-300'"
            >
              <v-icon v-if="selectedOrgId === org.id" icon="mdi-check" size="14" />
            </div>
          </div>
        </div>

        <div class="space-y-2.5">
          <v-btn
            color="primary"
            block
            size="large"
            :loading="selectingOrg"
            class="text-sm font-bold h-12"
            @click="handleConfirmOrganization"
          >
            Vào hệ thống làm việc
          </v-btn>

          <v-btn
            variant="text"
            color="secondary"
            block
            class="text-xs font-semibold"
            @click="handleBackToCredentials"
          >
            <v-icon icon="mdi-arrow-left" start size="small" />
            Đổi tài khoản khác
          </v-btn>
        </div>
      </div>

    </div>
  </AuthLayout>
</template>
