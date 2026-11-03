<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useDisplay } from 'vuetify'
import { useAuthStore } from '@/modules/auth/stores/authStore'
import { useRealtimeStore } from '@/core/stores/realtimeStore'
import { useThemeStore } from '@/core/stores/themeStore'
import AppThemeToggle from '@/core/components/AppThemeToggle.vue'
import { toast } from '@/core/helpers/toast'

const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()
const realtimeStore = useRealtimeStore()
const themeStore = useThemeStore()
const { width, mobile } = useDisplay()

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

/**
 * Màn hình laptop 16 inch trở xuống (viewport width < 1680px, bao gồm 13", 14", 15", 15.6", 16"
 * ở các độ phân giải từ 1280px đến 1600px theo tỉ lệ scale Windows 100%, 125%, 150%).
 * Trên các màn hình này, menu mặc định đóng để tối đa 100% diện tích làm việc như Hình 2,
 * và hoạt động dưới dạng Drawer tạm thời (Temporary Overlay).
 */
const isCompactScreen = computed(() => width.value < 1680)

// Mặc định ẩn drawer trên màn hình <= 16 inch, mở cố định trên màn hình desktop siêu lớn (> 1680px)
const drawer = ref(!isCompactScreen.value)
const rail = ref(false)

// Đồng bộ trạng thái drawer khi thay đổi kích thước cửa sổ trình duyệt
watch(isCompactScreen, (compact) => {
  if (compact) {
    drawer.value = false
    rail.value = false
  } else {
    drawer.value = true
  }
}, { immediate: true })

const navSections = [
  {
    heading: 'BẢNG ĐIỀU KHIỂN',
    items: [
      {
        title: 'Tổng quan Dashboard',
        to: '/dashboard',
        icon: 'mdi-chart-pie-outline',
        action: 'read',
        subject: 'Dashboard',
      },
      {
        title: 'Sơ đồ mặt bằng (Map)',
        to: '/equipments/map-view',
        icon: 'mdi-map-marker-radius-outline',
        action: 'read',
        subject: 'Equipment',
      },
    ],
  },
  {
    heading: 'NGHIỆP VỤ QUẢN LÝ',
    items: [
      {
        title: 'Danh sách thiết bị',
        to: '/equipments',
        icon: 'mdi-cube-outline',
        action: 'read',
        subject: 'Equipment',
      },
      {
        title: 'Quản lý phòng & tầng',
        to: '/rooms',
        icon: 'mdi-home-city-outline',
        action: 'index',
        subject: 'Room',
      },
      {
        title: 'Định mức thiết bị phòng',
        to: '/rooms/equipments',
        icon: 'mdi-clipboard-check-outline',
        action: 'read',
        subject: 'Room',
      },
    ],
  },
  {
    heading: 'HỆ THỐNG & PHÂN QUYỀN',
    items: [
      {
        title: 'Quản lý người dùng',
        to: '/admin/users',
        icon: 'mdi-account-group-outline',
        action: 'read',
        subject: 'Users',
      },
      {
        title: 'Vai trò & Phân quyền',
        to: '/admin/roles',
        icon: 'mdi-shield-check-outline',
        action: 'read',
        subject: 'Roles',
      },
      {
        title: 'Cây danh mục quyền',
        to: '/admin/permissions',
        icon: 'mdi-key-variant',
        action: 'tree',
        subject: 'Permissions',
      },
    ],
  },
]

function isItemActive(item) {
  if (route.path === item.to) return true

  // Với trang Sơ đồ mặt bằng
  if (item.to === '/equipments/map-view') {
    return route.path === '/equipments/map-view'
  }

  // Với trang Danh sách thiết bị (loại trừ /equipments/map-view)
  if (item.to === '/equipments') {
    return route.path.startsWith('/equipments') && route.path !== '/equipments/map-view'
  }

  // Với trang Định mức thiết bị phòng
  if (item.to === '/rooms/equipments') {
    return route.path.startsWith('/rooms/equipments')
  }

  // Với trang Quản lý phòng & tầng (loại trừ /rooms/equipments)
  if (item.to === '/rooms') {
    return route.path.startsWith('/rooms') && !route.path.startsWith('/rooms/equipments')
  }

  // Với Dashboard
  if (item.to === '/dashboard') {
    return route.path === '/dashboard'
  }

  return route.path.startsWith(item.to)
}

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
  if (isCompactScreen.value) {
    drawer.value = false
  }
}
</script>

<template>
  <v-layout class="min-h-screen bg-slate-50 dark:bg-slate-950 transition-colors duration-200">
    <!-- Sidebar Navigation Drawer -->
    <v-navigation-drawer
      v-model="drawer"
      :rail="!isCompactScreen && rail"
      :rail-width="72"
      :temporary="isCompactScreen"
      :permanent="!isCompactScreen"
      elevation="0"
      :color="themeStore.isDark ? '#0f172a' : 'white'"
      class="border-r border-slate-200/80 dark:border-slate-800 select-none z-50 transition-colors duration-200"
      :width="265"
    >
      <!-- Logo / Header đồng bộ Royal Blue của hệ thống -->
      <div
        class="h-16 border-b border-slate-100 dark:border-slate-800 flex items-center transition-all overflow-hidden"
        :class="!isCompactScreen && rail ? 'justify-center px-2' : 'justify-between px-4'"
      >
        <div v-if="isCompactScreen || !rail" class="flex items-center gap-3 overflow-hidden min-w-0">
          <div class="w-9 h-9 rounded-xl bg-blue-600 flex items-center justify-center flex-shrink-0 text-white shadow-sm shadow-blue-500/20">
            <v-icon icon="mdi-shield-home" size="20" />
          </div>
          <div class="overflow-hidden">
            <h2 class="text-sm font-bold text-slate-800 dark:text-slate-100 tracking-tight truncate">
              Equipment Manager
            </h2>
            <p class="text-[11px] font-medium text-slate-400 dark:text-slate-500 truncate">
              Hệ thống Quản lý Khách sạn
            </p>
          </div>
        </div>

        <!-- Toggle Button khi mở rộng trên Desktop lớn (> 16in) -->
        <button
          v-if="!isCompactScreen && !rail"
          type="button"
          class="w-7 h-7 rounded-lg border border-slate-200/80 dark:border-slate-700 text-slate-400 hover:text-blue-600 dark:hover:text-blue-400 hover:border-blue-200 dark:hover:border-blue-800 hover:bg-blue-50/60 dark:hover:bg-slate-800 flex items-center justify-center transition cursor-pointer"
          title="Thu gọn menu"
          @click="rail = true"
        >
          <v-icon icon="mdi-chevron-left" size="18" />
        </button>

        <!-- Toggle Button khi thu gọn rail trên Desktop lớn (> 16in) -->
        <button
          v-else-if="!isCompactScreen && rail"
          type="button"
          class="w-9 h-9 rounded-xl hover:bg-blue-50 dark:hover:bg-slate-800 text-slate-500 dark:text-slate-400 hover:text-blue-600 dark:hover:text-blue-400 flex items-center justify-center transition cursor-pointer"
          title="Mở rộng menu"
          @click="rail = false"
        >
          <v-icon icon="mdi-chevron-right" size="20" />
        </button>

        <!-- Close Button trên Màn hình <= 16in / Mobile dạng Temporary Overlay -->
        <v-btn
          v-else
          icon="mdi-close"
          variant="text"
          density="comfortable"
          size="small"
          color="secondary"
          title="Đóng menu"
          @click="drawer = false"
        />
      </div>

      <!-- Navigation Links phân nhóm tinh tế, chuẩn tông màu Royal Blue -->
      <div
        class="py-3 overflow-y-auto max-h-[calc(100vh-8rem)]"
        :class="!isCompactScreen && rail ? 'px-1.5 space-y-2' : 'px-3 space-y-4'"
      >
        <template v-for="(section, sIndex) in navSections" :key="sIndex">
          <div :class="!isCompactScreen && rail ? 'space-y-2' : 'space-y-1'">
            <!-- Heading nhóm -->
            <div
              v-if="isCompactScreen || !rail"
              class="px-3 pt-1 pb-1 text-[11px] font-semibold text-slate-400 uppercase tracking-wider"
            >
              {{ section.heading }}
            </div>
            <div v-else-if="sIndex > 0" class="my-2 mx-auto w-6 border-t border-slate-200/80"></div>

            <!-- Items trong nhóm -->
            <template v-for="item in section.items" :key="item.to">
              <router-link
                v-if="$can(item.action, item.subject)"
                :to="item.to"
                class="group flex items-center transition-all no-underline"
                style="text-decoration: none !important;"
                :class="[
                  !isCompactScreen && rail
                    ? 'justify-center w-11 h-11 mx-auto rounded-xl'
                    : 'px-3 py-2 rounded-xl text-sm font-medium',
                  !isCompactScreen && rail
                    ? (isItemActive(item)
                        ? 'bg-blue-600 text-white shadow-sm shadow-blue-500/25'
                        : 'text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-blue-600 dark:hover:text-blue-400')
                    : (isItemActive(item)
                        ? 'bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 font-semibold'
                        : 'text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-white')
                ]"
                :title="!isCompactScreen && rail ? item.title : undefined"
                @click="handleNavClick"
              >
                <!-- Trạng thái thu gọn Rail: Icon vuông vắn 44x44, căn giữa hoàn hảo, không bị cắt xén -->
                <template v-if="!isCompactScreen && rail">
                  <v-icon
                    :icon="item.icon"
                    size="20"
                    :color="isItemActive(item) ? 'white' : 'secondary'"
                  />
                </template>

                <!-- Trạng thái mở rộng (Desktop đầy đủ hoặc Drawer Mobile/Laptop): Đầy đủ icon badge và text tiêu đề -->
                <template v-else>
                  <div class="flex items-center gap-2.5 overflow-hidden min-w-0">
                    <div
                      class="w-7 h-7 rounded-lg flex items-center justify-center flex-shrink-0 transition-colors"
                      :class="isItemActive(item)
                        ? 'bg-blue-600 text-white shadow-xs' 
                        : 'text-slate-500 dark:text-slate-400 group-hover:text-blue-600 dark:group-hover:text-blue-400'"
                    >
                      <v-icon
                        :icon="item.icon"
                        size="18"
                        :color="isItemActive(item) ? 'white' : 'secondary'"
                      />
                    </div>
                    <span class="truncate text-[13px] no-underline">
                      {{ item.title }}
                    </span>
                  </div>
                </template>
              </router-link>
            </template>
          </div>
        </template>
      </div>

      <!-- Organization badge in Sidebar Bottom -->
      <template #append>
        <div v-if="isCompactScreen || !rail" class="p-3.5 border-t border-slate-100 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-900/50">
          <div class="flex items-center justify-between text-xs text-slate-500 mb-1">
            <span class="font-medium text-[11px] text-slate-400 dark:text-slate-500 uppercase tracking-wider">Tổ chức:</span>
            <span class="px-2 py-0.5 rounded bg-blue-100 dark:bg-blue-900/50 text-blue-700 dark:text-blue-300 text-[10px] font-bold">
              #{{ organizationId }}
            </span>
          </div>
          <div class="flex items-center gap-2">
            <div class="w-2 h-2 rounded-full bg-emerald-500"></div>
            <p class="text-xs font-bold text-slate-700 dark:text-slate-200 truncate">{{ currentOrg.name }}</p>
          </div>
        </div>
        <div
          v-else
          class="p-2.5 border-t border-slate-100 dark:border-slate-800 flex justify-center bg-slate-50/60 dark:bg-slate-900/50"
          :title="`Tổ chức: ${currentOrg.name} (#${organizationId})`"
        >
          <span class="w-8 h-8 rounded-lg bg-blue-100 dark:bg-blue-900/50 text-blue-700 dark:text-blue-300 text-[11px] font-bold flex items-center justify-center">
            #{{ organizationId }}
          </span>
        </div>
      </template>
    </v-navigation-drawer>

    <!-- Main Content Area -->
    <v-main class="min-w-0 flex flex-col min-h-screen">
      <!-- Top Navbar -->
      <header class="h-16 bg-white dark:bg-slate-900 border-b border-slate-200/80 dark:border-slate-800 px-4 sm:px-6 flex items-center justify-between sticky top-0 z-30 transition-colors duration-200">
        <div class="flex items-center gap-2.5 sm:gap-4 min-w-0">
          <!-- Toggle Button for Sidebar (CHỈ hiển thị trên màn hình laptop <= 16in / Mobile để mở menu Drawer; màn hình Desktop lớn đã có nút thu gọn trong sidebar) -->
          <button
            v-if="isCompactScreen"
            type="button"
            class="w-9 h-9 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white flex items-center justify-center transition cursor-pointer flex-shrink-0 border border-slate-200/70 dark:border-slate-700 shadow-2xs"
            :title="drawer ? 'Đóng menu' : 'Mở danh mục'"
            @click="drawer = !drawer"
          >
            <v-icon icon="mdi-menu" size="20" />
          </button>

          <!-- Organization Switcher Dropdown in Navbar -->
          <v-menu location="bottom start">
            <template #activator="{ props: menuProps }">
              <button
                v-bind="menuProps"
                class="flex items-center gap-1.5 sm:gap-2 px-3 py-1.5 rounded-xl bg-slate-50 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-700/60 transition text-xs font-semibold text-slate-700 dark:text-slate-200 cursor-pointer min-w-0 shadow-2xs"
                title="Đổi tổ chức làm việc"
              >
                <v-icon icon="mdi-office-building-outline" size="16" color="primary" class="flex-shrink-0" />
                <span class="truncate max-w-[130px] sm:max-w-[240px] md:max-w-none">
                  {{ currentOrg.name }}
                </span>
                <span class="text-slate-400 dark:text-slate-500 font-normal text-[11px]">(#{{ organizationId }})</span>
                <v-icon icon="mdi-chevron-down" size="14" color="secondary" class="flex-shrink-0" />
              </button>
            </template>

            <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-xl border border-slate-200/80 dark:border-slate-700 p-2 min-w-[240px] text-xs">
              <p class="text-[11px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider px-3 py-1.5 mb-1 border-b border-slate-100 dark:border-slate-700/60">
                Chuyển tổ chức làm việc
              </p>

              <div class="space-y-1">
                <button
                  v-for="org in availableOrgs"
                  :key="org.id"
                  class="w-full flex items-center justify-between px-3 py-2 rounded-lg transition text-left cursor-pointer"
                  :class="org.id === organizationId ? 'bg-blue-50 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300 font-bold' : 'hover:bg-slate-50 dark:hover:bg-slate-700/50 text-slate-700 dark:text-slate-300'"
                  @click="switchOrg(org.id)"
                >
                  <div class="truncate mr-2">
                    <p class="truncate">{{ org.name }}</p>
                    <p class="text-[10px] text-slate-400 dark:text-slate-500">ID: #{{ org.id }}</p>
                  </div>
                  <v-icon v-if="org.id === organizationId" icon="mdi-check" size="16" color="primary" />
                </button>
              </div>
            </div>
          </v-menu>
        </div>

        <!-- Right User Actions -->
        <div class="flex items-center gap-1.5 sm:gap-3 flex-shrink-0">
          <!-- Bật / Tắt Giao diện Sáng - Tối (Dark / Light Mode) -->
          <AppThemeToggle />

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

            <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-2xl border border-slate-200/90 dark:border-slate-700 w-[340px] sm:w-[380px] overflow-hidden text-slate-800 dark:text-slate-100">
              <!-- Header -->
              <div class="p-3.5 bg-slate-50 dark:bg-slate-900/80 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between">
                <div class="flex items-center gap-2">
                  <span class="w-2.5 h-2.5 rounded-full" :class="realtimeStore.connected ? 'bg-emerald-500 animate-pulse' : 'bg-slate-300 dark:bg-slate-600'" />
                  <span class="font-bold text-xs text-slate-800 dark:text-slate-100">Thông báo Real-time</span>
                  <span class="text-[10px] font-mono px-1.5 py-0.5 rounded" :class="realtimeStore.connected ? 'bg-emerald-100 dark:bg-emerald-950/60 text-emerald-800 dark:text-emerald-300' : 'bg-slate-100 dark:bg-slate-700 text-slate-500 dark:text-slate-400'">
                    {{ realtimeStore.connected ? 'WS LIVE' : 'OFFLINE' }}
                  </span>
                </div>

                <div class="flex items-center gap-1">
                  <!-- Mute/Unmute sound -->
                  <button
                    class="p-1.5 rounded-lg text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 hover:bg-slate-200 dark:hover:bg-slate-700 transition cursor-pointer"
                    :title="realtimeStore.soundEnabled ? 'Tắt âm báo' : 'Bật âm báo'"
                    @click="realtimeStore.toggleSound"
                  >
                    <v-icon :icon="realtimeStore.soundEnabled ? 'mdi-volume-high' : 'mdi-volume-off'" size="16" />
                  </button>

                  <!-- Mark all read -->
                  <button
                    v-if="realtimeStore.unreadCount > 0"
                    class="text-[11px] font-semibold text-blue-600 dark:text-blue-400 hover:text-blue-700 px-2 py-1 rounded hover:bg-blue-50 dark:hover:bg-blue-900/40 transition cursor-pointer"
                    @click="realtimeStore.markAllAsRead"
                  >
                    Đã đọc
                  </button>
                </div>
              </div>

              <!-- Notifications list -->
              <div class="max-h-80 overflow-y-auto divide-y divide-slate-100 dark:divide-slate-700/60">
                <div
                  v-for="item in realtimeStore.notifications"
                  :key="item.id"
                  class="p-3 hover:bg-slate-50 dark:hover:bg-slate-700/40 transition text-xs flex gap-3 items-start"
                  :class="!item.read ? 'bg-blue-50/40 dark:bg-blue-900/20' : ''"
                >
                  <!-- Icon indicator -->
                  <div
                    class="w-8 h-8 rounded-xl flex items-center justify-center shrink-0 mt-0.5"
                    :class="{
                      'bg-rose-100 dark:bg-rose-950/60 text-rose-700 dark:text-rose-300': item.type === 'broken_reported',
                      'bg-blue-100 dark:bg-blue-950/60 text-blue-700 dark:text-blue-300': item.type === 'assigned',
                      'bg-amber-100 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300': item.type === 'status_changed',
                      'bg-emerald-100 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300': item.type === 'created',
                      'bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300': item.type === 'deleted' || item.type === 'updated',
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
                      <p class="font-bold text-slate-800 dark:text-slate-100 truncate">{{ item.title }}</p>
                      <span class="text-[10px] text-slate-400 dark:text-slate-500 shrink-0">{{ item.timestamp }}</span>
                    </div>
                    <p class="text-slate-600 dark:text-slate-300 line-clamp-2 text-[11px] leading-relaxed">{{ item.message }}</p>
                  </div>
                </div>

                <!-- Empty state -->
                <div v-if="realtimeStore.notifications.length === 0" class="py-8 text-center text-slate-400 dark:text-slate-500 text-xs">
                  <v-icon icon="mdi-bell-sleep-outline" size="32" class="text-slate-300 dark:text-slate-600 mb-2 block mx-auto" />
                  Chưa có thông báo mới nào
                </div>
              </div>

              <!-- Footer info -->
              <div class="p-2 bg-slate-50 dark:bg-slate-900/80 border-t border-slate-100 dark:border-slate-700 text-center text-[10px] text-slate-400 dark:text-slate-500">
                Thông báo được cập nhật theo thời gian thực
              </div>
            </div>
          </v-menu>

          <!-- User Menu -->
          <v-menu location="bottom end" transition="slide-y-transition">
            <template #activator="{ props }">
              <button
                v-bind="props"
                class="flex items-center gap-1.5 sm:gap-2.5 p-1 sm:p-1.5 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors cursor-pointer"
              >
                <div class="w-8 h-8 rounded-full bg-blue-100 dark:bg-blue-900/50 text-blue-700 dark:text-blue-300 font-bold text-xs flex items-center justify-center border border-blue-200 dark:border-blue-700 flex-shrink-0">
                  {{ user?.name ? user.name.charAt(0).toUpperCase() : 'A' }}
                </div>
                <div class="hidden md:block text-left text-xs">
                  <p class="font-bold text-slate-800 dark:text-slate-100 leading-tight">{{ user?.name || 'Admin User' }}</p>
                  <p class="text-slate-400 dark:text-slate-500 text-[11px]">{{ user?.email || 'admin@example.com' }}</p>
                </div>
                <v-icon icon="mdi-chevron-down" size="16" color="secondary" class="hidden sm:inline" />
              </button>
            </template>

            <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-xl border border-slate-100 dark:border-slate-700 p-2 min-w-[200px] text-xs">
              <div class="px-3 py-2 border-b border-slate-100 dark:border-slate-700 mb-1">
                <p class="font-bold text-slate-800 dark:text-slate-100">{{ user?.name || 'Administrator' }}</p>
                <p class="text-slate-400 dark:text-slate-500 text-[11px] truncate">{{ user?.email }}</p>
                <div class="mt-1.5 inline-block px-2 py-0.5 rounded bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 text-[10px] font-semibold border border-emerald-200 dark:border-emerald-800">
                  Super Admin
                </div>
              </div>

              <button
                class="w-full flex items-center gap-2.5 px-3 py-2 rounded-lg text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/40 transition-colors text-left mt-1 cursor-pointer"
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
    </v-main>
  </v-layout>
</template>

<style scoped>
:deep(.v-navigation-drawer--rail) {
  width: 72px !important;
}
:deep(.v-navigation-drawer--rail .v-navigation-drawer__content) {
  overflow-x: hidden !important;
}
</style>
