<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useDisplay } from 'vuetify'
import { useAuthStore } from '@/modules/auth/stores/authStore'
import { useRealtimeStore } from '@/core/stores/realtimeStore'
import { toast } from '@/core/helpers/toast'

const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()
const realtimeStore = useRealtimeStore()
const { mobile } = useDisplay()

const user = computed(() => authStore.user)
const organizationId = computed(() => authStore.organizationId)
const availableOrgs = computed(() => authStore.availableOrganizations)
const currentOrg = computed(() => authStore.currentOrganization)

// Khởi tạo kết nối Real-time qua Reverb WebSocket
onMounted(() => {
  if (organizationId.value) {
    realtimeStore.initRealtime(organizationId.value)
  }
})

// Tự động chuyển kênh Realtime khi user đổi tổ chức
watch(organizationId, (newOrg) => {
  if (newOrg) {
    realtimeStore.initRealtime(newOrg)
  }
})

// Drawer is open by default on desktop, hidden on mobile
const drawer = ref(!mobile.value)
const rail = ref(false)

// Keep drawer state synced with viewport resize
watch(mobile, isMobile => {
  if (isMobile) {
    drawer.value = false
    rail.value = false
  } else {
    drawer.value = true
  }
})

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

async function switchOrg(orgId) {
  if (orgId === organizationId.value) return
  try {
    await authStore.selectOrganization(orgId)
    const orgName = availableOrgs.value.find(o => o.id === orgId)?.name || `#${orgId}`
    toast.success(`Đã chuyển sang tổ chức: ${orgName}`)
    // Reload page data
    window.location.reload()
  } catch (e) {
    console.error(e)
  }
}

function handleLogout() {
  authStore.logout()
  toast.info('Đã đăng xuất khỏi hệ thống.')
  router.push('/login')
}

function handleNavClick() {
  if (mobile.value) {
    drawer.value = false
  }
}
</script>

<template>
  <div class="min-h-screen bg-slate-50 flex">
    <!-- Sidebar Navigation Drawer -->
    <v-navigation-drawer
      v-model="drawer"
      :rail="!mobile && rail"
      :temporary="mobile"
      :permanent="!mobile"
      elevation="0"
      color="white"
      class="border-r border-slate-200/80"
      :width="260"
    >
      <!-- Logo / Header -->
      <div class="p-4 border-b border-slate-100 flex items-center justify-between">
        <div class="flex items-center gap-3 overflow-hidden">
          <div class="w-9 h-9 rounded-xl bg-blue-600 flex items-center justify-center flex-shrink-0 text-white shadow-sm shadow-blue-500/20">
            <v-icon icon="mdi-shield-home" size="20" />
          </div>
          <div v-if="mobile || !rail" class="overflow-hidden">
            <h2 class="text-sm font-bold text-slate-800 tracking-tight truncate">
              Equipment Hub
            </h2>
            <p class="text-[11px] text-slate-400 truncate">
              Quản lý Khách sạn
            </p>
          </div>
        </div>

        <v-btn
          v-if="!mobile"
          :icon="rail ? 'mdi-chevron-right' : 'mdi-chevron-left'"
          variant="text"
          density="comfortable"
          size="small"
          color="secondary"
          @click="rail = !rail"
        />

        <v-btn
          v-else
          icon="mdi-close"
          variant="text"
          density="comfortable"
          size="small"
          color="secondary"
          @click="drawer = false"
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
            :class="route.path === item.to || (item.to !== '/dashboard' && route.path.startsWith(item.to))
              ? 'bg-blue-50 text-blue-600 font-semibold shadow-xs' 
              : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'"
            @click="handleNavClick"
          >
            <v-icon
              :icon="item.icon"
              size="20"
              :color="route.path === item.to || (item.to !== '/dashboard' && route.path.startsWith(item.to)) ? 'primary' : 'secondary'"
            />
            <span v-if="mobile || !rail" class="truncate">{{ item.title }}</span>
          </router-link>
        </template>
      </div>

      <!-- Organization badge in Sidebar Bottom -->
      <template #append>
        <div v-if="mobile || !rail" class="p-4 border-t border-slate-100 bg-slate-50/50">
          <div class="flex items-center justify-between text-xs text-slate-500 mb-1">
            <span class="font-medium">Tổ chức hiện tại:</span>
            <span class="px-2 py-0.5 rounded bg-blue-100 text-blue-700 font-bold">
              #{{ organizationId }}
            </span>
          </div>
          <p class="text-xs font-bold text-slate-700 truncate">{{ currentOrg.name }}</p>
        </div>
      </template>
    </v-navigation-drawer>

    <!-- Main Content Area -->
    <div class="flex-1 flex flex-col min-w-0">
      <!-- Top Navbar -->
      <header class="h-16 bg-white border-b border-slate-200/80 px-3 sm:px-6 flex items-center justify-between sticky top-0 z-30">
        <div class="flex items-center gap-2 sm:gap-3 min-w-0">
          <v-btn
            icon="mdi-menu"
            variant="text"
            density="comfortable"
            size="small"
            class="md:hidden flex-shrink-0"
            @click="drawer = !drawer"
          />

          <!-- Organization Switcher Dropdown in Navbar -->
          <v-menu location="bottom start">
            <template #activator="{ props: menuProps }">
              <button
                v-bind="menuProps"
                class="flex items-center gap-1.5 sm:gap-2 px-2 sm:px-3 py-1.5 rounded-xl bg-slate-50 border border-slate-200 hover:bg-slate-100 transition text-xs font-semibold text-slate-700 cursor-pointer min-w-0"
                title="Đổi tổ chức làm việc"
              >
                <v-icon icon="mdi-office-building-outline" size="16" color="primary" class="flex-shrink-0" />
                <span class="truncate max-w-[110px] xs:max-w-[140px] sm:max-w-[220px] md:max-w-none">
                  {{ currentOrg.name }}
                </span>
                <span class="hidden sm:inline text-slate-400 font-normal">(#{{ organizationId }})</span>
                <v-icon icon="mdi-chevron-down" size="14" color="secondary" class="flex-shrink-0" />
              </button>
            </template>

            <div class="bg-white rounded-xl shadow-xl border border-slate-100 p-2 min-w-[240px] text-xs">
              <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider px-3 py-1.5 mb-1">
                Chuyển tổ chức làm việc
              </p>

              <div class="space-y-1">
                <button
                  v-for="org in availableOrgs"
                  :key="org.id"
                  class="w-full flex items-center justify-between px-3 py-2 rounded-lg transition text-left cursor-pointer"
                  :class="org.id === organizationId ? 'bg-blue-50 text-blue-700 font-bold' : 'hover:bg-slate-50 text-slate-700'"
                  @click="switchOrg(org.id)"
                >
                  <div class="truncate mr-2">
                    <p class="truncate">{{ org.name }}</p>
                    <p class="text-[10px] text-slate-400">ID: #{{ org.id }}</p>
                  </div>
                  <v-icon v-if="org.id === organizationId" icon="mdi-check" size="16" color="primary" />
                </button>
              </div>
            </div>
          </v-menu>
        </div>

        <!-- Right User Actions -->
        <div class="flex items-center gap-1 sm:gap-3 flex-shrink-0">
          <!-- Notification Bell Dropdown (Real-time Reverb) -->
          <v-menu location="bottom end" :close-on-content-click="false" transition="slide-y-transition">
            <template #activator="{ props: notifProps }">
              <v-badge
                :content="realtimeStore.unreadCount"
                :model-value="realtimeStore.unreadCount > 0"
                color="error"
                offset-x="3"
                offset-y="3"
              >
                <v-btn
                  v-bind="notifProps"
                  icon="mdi-bell-outline"
                  variant="text"
                  density="comfortable"
                  size="small"
                  :color="realtimeStore.unreadCount > 0 ? 'primary' : 'secondary'"
                  title="Thông báo hệ thống Real-time"
                />
              </v-badge>
            </template>

            <div class="bg-white rounded-2xl shadow-2xl border border-slate-200/90 w-[340px] sm:w-[380px] overflow-hidden">
              <!-- Header -->
              <div class="p-3.5 bg-slate-50 border-b border-slate-100 flex items-center justify-between">
                <div class="flex items-center gap-2">
                  <span class="w-2.5 h-2.5 rounded-full" :class="realtimeStore.connected ? 'bg-emerald-500 animate-pulse' : 'bg-slate-300'" />
                  <span class="font-bold text-xs text-slate-800">Thông báo Real-time</span>
                  <span class="text-[10px] font-mono px-1.5 py-0.5 rounded" :class="realtimeStore.connected ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-500'">
                    {{ realtimeStore.connected ? 'WS LIVE' : 'OFFLINE' }}
                  </span>
                </div>

                <div class="flex items-center gap-1">
                  <!-- Mute/Unmute sound -->
                  <button
                    class="p-1.5 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-200 transition cursor-pointer"
                    :title="realtimeStore.soundEnabled ? 'Tắt âm báo' : 'Bật âm báo'"
                    @click="realtimeStore.toggleSound"
                  >
                    <v-icon :icon="realtimeStore.soundEnabled ? 'mdi-volume-high' : 'mdi-volume-off'" size="16" />
                  </button>

                  <!-- Mark all read -->
                  <button
                    v-if="realtimeStore.unreadCount > 0"
                    class="text-[11px] font-semibold text-blue-600 hover:text-blue-700 px-2 py-1 rounded hover:bg-blue-50 transition cursor-pointer"
                    @click="realtimeStore.markAllAsRead"
                  >
                    Đã đọc
                  </button>
                </div>
              </div>

              <!-- Notifications list -->
              <div class="max-h-80 overflow-y-auto divide-y divide-slate-100">
                <div
                  v-for="item in realtimeStore.notifications"
                  :key="item.id"
                  class="p-3 hover:bg-slate-50 transition text-xs flex gap-3 items-start"
                  :class="!item.read ? 'bg-blue-50/40' : ''"
                >
                  <!-- Icon indicator -->
                  <div
                    class="w-8 h-8 rounded-xl flex items-center justify-center shrink-0 mt-0.5"
                    :class="{
                      'bg-rose-100 text-rose-700': item.type === 'broken_reported',
                      'bg-blue-100 text-blue-700': item.type === 'assigned',
                      'bg-amber-100 text-amber-700': item.type === 'status_changed',
                      'bg-emerald-100 text-emerald-700': item.type === 'created',
                      'bg-slate-100 text-slate-700': item.type === 'deleted' || item.type === 'updated',
                    }"
                  >
                    <v-icon
                      :icon="
                        item.type === 'broken_reported' ? 'mdi-alert-octagon' :
                        item.type === 'assigned' ? 'mdi-transfer' :
                        item.type === 'status_changed' ? 'mdi-swap-horizontal-bold' :
                        item.type === 'created' ? 'mdi-plus-circle' :
                        item.type === 'deleted' ? 'mdi-trash-can-outline' : 'mdi-pencil-circle'
                      "
                      size="18"
                    />
                  </div>

                  <!-- Text -->
                  <div class="flex-1 min-w-0">
                    <div class="flex items-center justify-between gap-1 mb-0.5">
                      <p class="font-bold text-slate-800 truncate">{{ item.title }}</p>
                      <span class="text-[10px] text-slate-400 shrink-0">{{ item.timestamp }}</span>
                    </div>
                    <p class="text-slate-600 line-clamp-2 text-[11px] leading-relaxed">{{ item.message }}</p>
                  </div>
                </div>

                <!-- Empty state -->
                <div v-if="realtimeStore.notifications.length === 0" class="py-8 text-center text-slate-400 text-xs">
                  <v-icon icon="mdi-bell-sleep-outline" size="32" class="text-slate-300 mb-2 block mx-auto" />
                  Chưa có thông báo mới nào
                </div>
              </div>

              <!-- Footer info -->
              <div class="p-2 bg-slate-50 border-t border-slate-100 text-center text-[10px] text-slate-400">
                Laravel Reverb • Đồng bộ theo thời gian thực
              </div>
            </div>
          </v-menu>

          <!-- User Menu -->
          <v-menu location="bottom end" transition="slide-y-transition">
            <template #activator="{ props }">
              <button
                v-bind="props"
                class="flex items-center gap-1.5 sm:gap-2.5 p-1 sm:p-1.5 rounded-xl hover:bg-slate-100 transition-colors cursor-pointer"
              >
                <div class="w-8 h-8 rounded-full bg-blue-100 text-blue-700 font-bold text-xs flex items-center justify-center border border-blue-200 flex-shrink-0">
                  {{ user?.name ? user.name.charAt(0).toUpperCase() : 'A' }}
                </div>
                <div class="hidden md:block text-left text-xs">
                  <p class="font-bold text-slate-800 leading-tight">{{ user?.name || 'Admin User' }}</p>
                  <p class="text-slate-400 text-[11px]">{{ user?.email || 'admin@example.com' }}</p>
                </div>
                <v-icon icon="mdi-chevron-down" size="16" color="secondary" class="hidden sm:inline" />
              </button>
            </template>

            <div class="bg-white rounded-xl shadow-xl border border-slate-100 p-2 min-w-[200px] text-xs">
              <div class="px-3 py-2 border-b border-slate-100 mb-1">
                <p class="font-bold text-slate-800">{{ user?.name || 'Administrator' }}</p>
                <p class="text-slate-400 text-[11px] truncate">{{ user?.email }}</p>
                <div class="mt-1.5 inline-block px-2 py-0.5 rounded bg-emerald-50 text-emerald-700 text-[10px] font-semibold border border-emerald-200">
                  Super Admin
                </div>
              </div>

              <button
                class="w-full flex items-center gap-2.5 px-3 py-2 rounded-lg text-rose-600 hover:bg-rose-50 transition-colors text-left mt-1 cursor-pointer"
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
      <main class="flex-1 p-3.5 sm:p-6 md:p-8 max-w-7xl w-full mx-auto">
        <slot />
      </main>
    </div>
  </div>
</template>
