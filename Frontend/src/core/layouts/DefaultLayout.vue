<script setup>
import { ref, computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/modules/auth/stores/authStore'
import { toast } from '@/core/helpers/toast'

const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()

const drawer = ref(true)
const rail = ref(false)

const user = computed(() => authStore.user)
const organizationId = computed(() => authStore.organizationId)

const navItems = [
  {
    title: 'Bảng điều khiển',
    to: '/dashboard',
    icon: 'mdi-view-dashboard-outline',
    action: 'read',
    subject: 'Dashboard',
  },
  {
    title: 'Sơ đồ mặt bằng (Map)',
    to: '/equipments/map-view',
    icon: 'mdi-floor-plan',
    action: 'read',
    subject: 'Equipment',
  },
  {
    title: 'Danh sách thiết bị',
    to: '/equipments',
    icon: 'mdi-devices',
    action: 'read',
    subject: 'Equipment',
  },
  {
    title: 'Định mức thiết bị phòng',
    to: '/rooms/equipments',
    icon: 'mdi-door-sliding',
    action: 'read',
    subject: 'Room',
  },
]

function handleLogout() {
  authStore.logout()
  toast.info('Đã đăng xuất khỏi hệ thống.')
  router.push('/login')
}
</script>

<template>
  <div class="min-h-screen bg-slate-50 flex">
    <!-- Sidebar Navigation -->
    <v-navigation-drawer
      v-model="drawer"
      :rail="rail"
      permanent
      elevation="0"
      color="white"
      class="border-r border-slate-200/80"
      width="260"
    >
      <!-- Logo / Header -->
      <div class="p-4 border-b border-slate-100 flex items-center justify-between">
        <div class="flex items-center gap-3 overflow-hidden">
          <div class="w-9 h-9 rounded-xl bg-blue-600 flex items-center justify-center flex-shrink-0 text-white shadow-sm shadow-blue-500/20">
            <v-icon icon="mdi-shield-home" size="20" />
          </div>
          <div v-if="!rail" class="overflow-hidden">
            <h2 class="text-sm font-bold text-slate-800 tracking-tight truncate">
              Equipment Hub
            </h2>
            <p class="text-[11px] text-slate-400 truncate">
              Quản lý Khách sạn
            </p>
          </div>
        </div>

        <v-btn
          :icon="rail ? 'mdi-chevron-right' : 'mdi-chevron-left'"
          variant="text"
          density="comfortable"
          size="small"
          color="secondary"
          @click="rail = !rail"
        />
      </div>

      <!-- Navigation Links -->
      <div class="p-3 space-y-1">
        <template v-for="item in navItems" :key="item.to">
          <!-- CASL ability check -->
          <router-link
            v-if="$can(item.action, item.subject)"
            :to="item.to"
            class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium transition-all"
            :class="route.path === item.to || route.path.startsWith(item.to + '/') 
              ? 'bg-blue-50 text-blue-600 font-semibold shadow-xs' 
              : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'"
          >
            <v-icon
              :icon="item.icon"
              size="20"
              :color="route.path === item.to ? 'primary' : 'secondary'"
            />
            <span v-if="!rail" class="truncate">{{ item.title }}</span>
          </router-link>
        </template>
      </div>

      <!-- Organization badge in Sidebar Bottom -->
      <template #append>
        <div v-if="!rail" class="p-4 border-t border-slate-100 bg-slate-50/50">
          <div class="flex items-center justify-between text-xs text-slate-500 mb-1">
            <span class="font-medium">Tổ chức làm việc:</span>
            <span class="px-2 py-0.5 rounded bg-blue-100 text-blue-700 font-bold">
              #{{ organizationId }}
            </span>
          </div>
          <p class="text-[11px] text-slate-400">Header: X-Organization-Id</p>
        </div>
      </template>
    </v-navigation-drawer>

    <!-- Main Content Area -->
    <div class="flex-1 flex flex-col min-w-0">
      <!-- Top Navbar -->
      <header class="h-16 bg-white border-b border-slate-200/80 px-6 flex items-center justify-between sticky top-0 z-30">
        <div class="flex items-center gap-3">
          <v-btn
            icon="mdi-menu"
            variant="text"
            density="comfortable"
            size="small"
            class="md:hidden"
            @click="drawer = !drawer"
          />
          <div class="hidden sm:flex items-center gap-2 text-xs text-slate-400">
            <v-icon icon="mdi-office-building-outline" size="16" />
            <span>Chi nhánh chính: <strong>Default (#{{ organizationId }})</strong></span>
          </div>
        </div>

        <!-- Right User Actions -->
        <div class="flex items-center gap-3">
          <!-- Notification Bell -->
          <v-btn icon="mdi-bell-outline" variant="text" density="comfortable" size="small" color="secondary" />

          <!-- User Menu -->
          <v-menu location="bottom end" transition="slide-y-transition">
            <template #activator="{ props }">
              <button
                v-bind="props"
                class="flex items-center gap-2.5 p-1.5 rounded-xl hover:bg-slate-100 transition-colors cursor-pointer"
              >
                <div class="w-8 h-8 rounded-full bg-blue-100 text-blue-700 font-bold text-xs flex items-center justify-center border border-blue-200">
                  {{ user?.name ? user.name.charAt(0).toUpperCase() : 'A' }}
                </div>
                <div class="hidden md:block text-left text-xs">
                  <p class="font-bold text-slate-800 leading-tight">{{ user?.name || 'Admin User' }}</p>
                  <p class="text-slate-400 text-[11px]">{{ user?.email || 'admin@example.com' }}</p>
                </div>
                <v-icon icon="mdi-chevron-down" size="16" color="secondary" />
              </button>
            </template>

            <div class="bg-white rounded-xl shadow-xl border border-slate-100 p-2 min-w-[200px] text-xs">
              <div class="px-3 py-2 border-b border-slate-100 mb-1">
                <p class="font-bold text-slate-800">{{ user?.name || 'Administrator' }}</p>
                <p class="text-slate-400 text-[11px]">{{ user?.email }}</p>
                <div class="mt-1.5 inline-block px-2 py-0.5 rounded bg-emerald-50 text-emerald-700 text-[10px] font-semibold border border-emerald-200">
                  Super Admin
                </div>
              </div>

              <button
                class="w-full flex items-center gap-2.5 px-3 py-2 rounded-lg text-slate-700 hover:bg-slate-50 transition-colors text-left"
              >
                <v-icon icon="mdi-account-cog-outline" size="16" />
                <span>Hồ sơ cá nhân</span>
              </button>

              <button
                class="w-full flex items-center gap-2.5 px-3 py-2 rounded-lg text-rose-600 hover:bg-rose-50 transition-colors text-left mt-1"
                @click="handleLogout"
              >
                <v-icon icon="mdi-logout" size="16" color="error" />
                <span class="font-semibold">Đăng xuất</span>
              </button>
            </div>
          </v-menu>
        </div>
      </header>

      <!-- Page Content Slot -->
      <main class="flex-1 p-6 md:p-8 max-w-7xl w-full mx-auto">
        <slot />
      </main>
    </div>
  </div>
</template>
