<script setup>
import { ref, onMounted } from 'vue'
import { roomService } from '../services/roomService'
import { equipmentService } from '@/modules/equipment/services/equipmentService'
import DefaultLayout from '@/core/layouts/DefaultLayout.vue'
import AppPageHeader from '@/core/components/AppPageHeader.vue'
import AppButton from '@/core/components/AppButton.vue'
import AppStatusBadge from '@/core/components/AppStatusBadge.vue'

const loading = ref(false)
const rooms = ref([])
const selectedRoomId = ref(null)
const roomDetail = ref(null)

onMounted(async () => {
  await loadRooms()
})

async function loadRooms() {
  try {
    const data = await equipmentService.getMapView().catch(() => [])
    rooms.value = data || []
    if (rooms.value.length > 0) {
      selectedRoomId.value = rooms.value[0].id
      await loadRoomDetail(selectedRoomId.value)
    }
  } catch (e) {
    console.error('Failed to load rooms:', e)
  }
}

async function loadRoomDetail(id) {
  if (!id) return
  loading.value = true
  try {
    roomDetail.value = await roomService.getRoomEquipmentDetail(id)
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <DefaultLayout>
    <AppPageHeader
      title="Đối soát Định mức Thiết bị Phòng"
      subtitle="Kiểm tra mức độ đáp ứng định mức trang thiết bị tiêu chuẩn cho từng phòng khách sạn"
      :breadcrumbs="[
        { title: 'Trang chủ', to: '/dashboard' },
        { title: 'Định mức thiết bị phòng' },
      ]"
    />

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
      <!-- Left: Room Selector Sidebar -->
      <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-xs h-fit space-y-4">
        <div>
          <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">
            Chọn phòng đối soát
          </label>
          <v-select
            v-model="selectedRoomId"
            :items="rooms.map(r => ({ value: r.id, title: `Phòng ${r.room_number} - Tầng ${r.floor || 1}` }))"
            item-title="title"
            item-value="value"
            density="compact"
            hide-details
            prepend-inner-icon="mdi-door"
            @update:model-value="loadRoomDetail"
          />
        </div>

        <div class="border-t border-slate-100 pt-3">
          <p class="text-xs font-semibold text-slate-500 mb-2">Danh sách phòng nhanh:</p>
          <div class="space-y-1 max-h-96 overflow-y-auto pr-1">
            <button
              v-for="room in rooms"
              :key="room.id"
              class="w-full text-left p-2.5 rounded-xl text-xs font-medium flex items-center justify-between transition cursor-pointer"
              :class="selectedRoomId === room.id ? 'bg-blue-50 text-blue-700 font-bold border border-blue-200' : 'hover:bg-slate-50 text-slate-700'"
              @click="selectedRoomId = room.id; loadRoomDetail(room.id)"
            >
              <span>Phòng {{ room.room_number }}</span>
              <span
                class="w-2.5 h-2.5 rounded-full"
                :class="room.badge_color === 'green' ? 'bg-emerald-500' : room.badge_color === 'red' ? 'bg-rose-500' : 'bg-amber-500'"
              />
            </button>
          </div>
        </div>
      </div>

      <!-- Right: Detailed Audit Card -->
      <div class="lg:col-span-3">
        <div v-if="loading" class="h-96 bg-slate-200 rounded-2xl animate-pulse" />

        <div v-else-if="roomDetail" class="space-y-6">
          <!-- Room Header & Compliance Badge -->
          <div class="bg-white rounded-2xl p-6 border border-slate-200/80 shadow-xs flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
              <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Đối soát phòng</span>
              <h2 class="text-2xl font-extrabold text-slate-800">
                Phòng {{ roomDetail.room_number }}
              </h2>
              <p class="text-xs text-slate-500 mt-0.5">
                Loại phòng: <strong>{{ roomDetail.room_type || 'Tiêu chuẩn' }}</strong>
              </p>
            </div>

            <div
              class="px-4 py-2 rounded-xl text-xs font-bold border flex items-center gap-2 self-start sm:self-auto"
              :class="roomDetail.compliance_status === 'compliant' ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : 'bg-rose-50 text-rose-800 border-rose-200'"
            >
              <v-icon
                :icon="roomDetail.compliance_status === 'compliant' ? 'mdi-check-decagram' : 'mdi-alert-decagram'"
                size="20"
              />
              <span>
                {{ roomDetail.compliance_status === 'compliant' ? 'Đạt chuẩn 100% định mức' : 'Chưa đạt định mức tiêu chuẩn' }}
              </span>
            </div>
          </div>

          <!-- Missing items banner -->
          <div v-if="roomDetail.missing_equipments && roomDetail.missing_equipments.length" class="bg-rose-50 border border-rose-200 rounded-2xl p-5 text-xs text-rose-900">
            <h4 class="font-bold uppercase tracking-wider mb-2 flex items-center gap-2">
              <v-icon icon="mdi-alert-circle" size="18" color="error" />
              <span>Cảnh báo thiết bị còn thiếu so với định mức:</span>
            </h4>
            <div class="space-y-1.5 pl-6 list-disc">
              <div v-for="(mis, i) in roomDetail.missing_equipments" :key="i">
                Thiếu <strong>{{ mis.missing_count || 1 }}</strong> thiết bị <strong>{{ mis.category_name || mis.name }}</strong> (Quy chuẩn yêu cầu: {{ mis.standard_quantity || 1 }}, hiện có: {{ mis.actual_quantity || 0 }})
              </div>
            </div>
          </div>

          <!-- Actual Equipment Table by Category -->
          <div class="bg-white rounded-2xl p-6 border border-slate-200/80 shadow-xs">
            <h3 class="text-base font-bold text-slate-800 mb-4">
              Danh mục Thiết bị Thực tế trong phòng
            </h3>

            <div v-if="roomDetail.categories && roomDetail.categories.length" class="space-y-4">
              <div
                v-for="cat in roomDetail.categories"
                :key="cat.category_code || cat.category_name"
                class="border border-slate-200 rounded-xl p-4"
              >
                <div class="flex items-center justify-between mb-3">
                  <h4 class="text-xs font-bold text-slate-700 uppercase tracking-wider">
                    {{ cat.category_name }}
                  </h4>
                  <span class="text-xs text-slate-400">{{ cat.items?.length || 0 }} thiết bị</span>
                </div>

                <div class="space-y-2">
                  <div
                    v-for="item in cat.items"
                    :key="item.id"
                    class="flex items-center justify-between p-3 bg-slate-50 rounded-xl text-xs"
                  >
                    <div>
                      <p class="font-bold text-slate-800 text-sm">{{ item.model_name || 'Thiết bị' }}</p>
                      <p class="font-mono text-blue-600 font-semibold mt-0.5">
                        {{ item.serial_number || 'Chưa gắn Serial' }}
                      </p>
                      <p v-if="item.position_note" class="text-[11px] text-slate-400 mt-0.5">
                        Vị trí: {{ item.position_note }}
                      </p>
                    </div>

                    <AppStatusBadge :status="item.status" />
                  </div>
                </div>
              </div>
            </div>

            <div v-else class="text-center py-12 text-slate-400 text-xs">
              <v-icon icon="mdi-door-open" size="44" class="text-slate-300 mb-2 block mx-auto" />
              Chưa có thiết bị nào được gán vào phòng này.
            </div>
          </div>
        </div>
      </div>
    </div>
  </DefaultLayout>
</template>
