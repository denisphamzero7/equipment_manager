<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { dashboardService } from '../services/dashboardService'
import DefaultLayout from '@/core/layouts/DefaultLayout.vue'
import AppPageHeader from '@/core/components/AppPageHeader.vue'
import AppStatCard from '@/core/components/AppStatCard.vue'
import AppButton from '@/core/components/AppButton.vue'
import EquipmentOverviewChart from '../components/EquipmentOverviewChart.vue'
import RoomAlertList from '../components/RoomAlertList.vue'

const router = useRouter()
const loading = ref(true)
const stats = ref({
  total_equipments: 0,
  by_status: {},
  by_category: [],
})
const mapRooms = ref([])

async function loadDashboardData() {
  loading.value = true
  try {
    const [statsData, roomsData] = await Promise.all([
      dashboardService.getStats().catch(() => ({})),
      dashboardService.getMapOverview().catch(() => []),
    ])
    stats.value = statsData || {}
    mapRooms.value = roomsData || []
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  loadDashboardData()
})
</script>

<template>
  <DefaultLayout>
    <AppPageHeader
      title="Bảng điều khiển Tổng quan"
      subtitle="Theo dõi tình trạng trang thiết bị, sơ đồ mặt bằng và đối soát phòng theo thời gian thực"
    >
      <template #actions>
        <AppButton
          v-if="$can('read', 'Equipment')"
          variant="tonal"
          color="secondary"
          icon="mdi-floor-plan"
          @click="router.push('/equipments/map-view')"
        >
          Sơ đồ Mặt bằng
        </AppButton>
        <AppButton
          v-if="$can('create', 'Equipment')"
          color="primary"
          icon="mdi-plus"
          @click="router.push('/equipments?action=create')"
        >
          Thêm thiết bị mới
        </AppButton>
      </template>
    </AppPageHeader>

    <!-- Top 4 KPI Stat Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
      <AppStatCard
        title="Tổng số thiết bị"
        :value="stats.total_equipments || 0"
        subtitle="Quản lý toàn bộ khách sạn"
        icon="mdi-devices"
        color="primary"
        :loading="loading"
      />
      <AppStatCard
        title="Đang hoạt động tốt"
        :value="stats.by_status?.good || 0"
        subtitle="Sẵn sàng phục vụ khách"
        icon="mdi-check-circle-outline"
        color="success"
        :loading="loading"
      />
      <AppStatCard
        title="Báo hỏng / Cần bảo trì"
        :value="(stats.by_status?.broken || 0) + (stats.by_status?.maintenance || 0)"
        subtitle="Cần kỹ thuật xử lý"
        icon="mdi-wrench-alert"
        color="error"
        :loading="loading"
      />
      <AppStatCard
        title="Tồn trong kho"
        :value="stats.by_status?.in_stock || 0"
        subtitle="Chưa gán phòng"
        icon="mdi-package-variant-closed"
        color="info"
        :loading="loading"
      />
    </div>

    <!-- Center 2 Columns: Status Distribution Chart & Room Alerts -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
      <EquipmentOverviewChart :stats="stats" />
      <RoomAlertList :rooms="mapRooms" :loading="loading" />
    </div>

    <!-- Category Breakdown Cards -->
    <div v-if="stats.by_category && stats.by_category.length" class="mt-8">
      <div class="flex items-center justify-between mb-4">
        <div>
          <h3 class="text-base font-bold text-slate-800">
            Số lượng Thiết bị theo Nhóm Danh mục
          </h3>
          <p class="text-xs text-slate-400">
            Tivi, Điều hòa, Tủ lạnh, Bộ phát Wifi, Thiết bị ngoại vi
          </p>
        </div>
      </div>

      <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4">
        <div
          v-for="cat in stats.by_category"
          :key="cat.category_id || cat.name"
          class="bg-white p-4 rounded-xl border border-slate-200/80 shadow-xs hover:border-blue-300 transition-all text-center"
        >
          <div class="w-10 h-10 rounded-lg bg-slate-50 text-slate-700 flex items-center justify-center mx-auto mb-2 font-bold text-xs border border-slate-100">
            <v-icon icon="mdi-tag-outline" size="18" color="primary" />
          </div>
          <p class="text-xs font-semibold text-slate-600 truncate mb-1" :title="cat.name">
            {{ cat.name }}
          </p>
          <p class="text-lg font-extrabold text-slate-800">
            {{ cat.count || 0 }}
          </p>
        </div>
      </div>
    </div>
  </DefaultLayout>
</template>
