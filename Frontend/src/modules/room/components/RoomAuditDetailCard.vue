<script setup>
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { resolveMediaUrl } from '@/core/helpers/formatters'
import AppButton from '@/core/components/AppButton.vue'
import AppStatusBadge from '@/core/components/AppStatusBadge.vue'

const props = defineProps({
  roomDetail: {
    type: Object,
    default: null,
  },
  loading: {
    type: Boolean,
    default: false,
  },
})

const hasBrokenRouter = computed(() => {
  const allItems = props.roomDetail?.categories?.flatMap(c => c.items || []) || []
  return allItems.some(i => (i.model?.category?.code === 'wifi' || i.category_code === 'wifi') && i.status === 'broken')
})

const hasMaintenanceRouter = computed(() => {
  const allItems = props.roomDetail?.categories?.flatMap(c => c.items || []) || []
  return allItems.some(i => (i.model?.category?.code === 'wifi' || i.category_code === 'wifi') && i.status === 'maintenance')
})

const emit = defineEmits([
  'open-edit-room',
  'open-warehouse-modal',
  'open-assign-modal',
  'open-report-broken-modal',
  'confirm-retract',
  'open-lightbox',
])

const router = useRouter()

function getCategoryIcon(code) {
  const map = {
    tv: 'mdi-television',
    remote: 'mdi-remote',
    box: 'mdi-set-top-box',
    wifi: 'mdi-wifi',
    phone: 'mdi-phone-classic',
    ac: 'mdi-air-conditioner',
    air_conditioner: 'mdi-air-conditioner',
    refrigerator: 'mdi-fridge',
    kettle: 'mdi-kettle-outline',
    safe: 'mdi-safe',
    hairdryer: 'mdi-hair-dryer',
  }
  return map[code?.toLowerCase()] || 'mdi-devices'
}
</script>

<template>
  <div class="space-y-6">
    <!-- 1. Room Detail Skeleton Loader -->
    <div v-if="loading && !roomDetail" class="space-y-6">
      <div class="bg-white rounded-2xl p-6 border border-slate-200/80 shadow-xs flex flex-col sm:flex-row sm:items-center justify-between gap-4 animate-pulse">
        <div class="space-y-2">
          <div class="h-3 w-24 bg-slate-200 rounded"></div>
          <div class="h-7 w-48 bg-slate-200 rounded-lg"></div>
          <div class="h-3.5 w-32 bg-slate-100 rounded"></div>
        </div>
        <div class="h-10 w-48 bg-slate-200 rounded-xl"></div>
      </div>
      <div class="bg-white rounded-2xl p-6 border border-slate-200/80 shadow-xs space-y-4 animate-pulse">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
          <div class="h-5 w-48 bg-slate-200 rounded"></div>
          <div class="h-8 w-32 bg-slate-100 rounded-lg"></div>
        </div>
        <div class="space-y-3">
          <div v-for="n in 3" :key="n" class="h-16 bg-slate-50 border border-slate-100 rounded-xl"></div>
        </div>
      </div>
    </div>

    <!-- 2. Detailed Room Content -->
    <div v-else-if="roomDetail" class="space-y-6">
      <div v-if="loading" class="flex items-center gap-2 rounded-xl border border-blue-200 bg-blue-50 px-3.5 py-2 text-xs text-blue-800">
        <v-progress-circular indeterminate color="primary" size="16" />
        <span>Đang đồng bộ thông tin phòng...</span>
      </div>

      <!-- Room Header & Compliance Badge -->
      <div class="bg-white rounded-2xl p-4 sm:p-6 border border-slate-200/80 shadow-xs flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Đối soát phòng chi tiết</span>
          <h2 class="text-xl sm:text-2xl font-black text-slate-800 mt-0.5">
            Phòng {{ roomDetail.room_number }}
            <span v-if="roomDetail.room?.block" class="text-slate-400 text-lg font-normal">
              ({{ roomDetail.room?.block }} • Tầng {{ roomDetail.room?.floor }})
            </span>
          </h2>
          <p class="text-xs text-slate-500 mt-1">
            Hạng phòng: <strong class="text-slate-800">{{ roomDetail.room_type || 'Tiêu chuẩn' }}</strong>
          </p>

          <!-- Space Composition & Wifi Badges -->
          <div class="flex items-center gap-1.5 mt-3 flex-wrap">
            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-blue-50 border border-blue-200 text-blue-700 text-xs font-bold">
              <v-icon icon="mdi-bed" size="14" />
              {{ roomDetail.bedroom_count ?? 1 }} Phòng ngủ
              <template v-if="(roomDetail.living_room_count ?? 0) > 0">
                • <v-icon icon="mdi-sofa" size="14" /> {{ roomDetail.living_room_count }} Phòng khách
              </template>
            </span>
            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-slate-100 border border-slate-200 text-slate-700 text-xs font-semibold">
              <v-icon icon="mdi-television" size="14" class="text-blue-600" />
              Định mức TV, Box & Remote: {{ Math.max(1, (roomDetail.bedroom_count ?? 1) + (roomDetail.living_room_count ?? 0)) }} bộ
            </span>
            <span
              v-if="roomDetail.wifi_mode === 'shared'"
              class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg border text-xs font-bold transition-all"
              :class="roomDetail.shared_wifi_issue ? 'bg-rose-50 border-rose-300 text-rose-700 animate-pulse' : 'bg-purple-50 border-purple-200 text-purple-700'"
            >
              <v-icon :icon="roomDetail.shared_wifi_issue ? 'mdi-wifi-alert' : 'mdi-wifi-arrow-left-right'" size="14" />
              Dùng ké Wifi {{ roomDetail.shared_wifi_note || (roomDetail.shared_wifi_room ? `P.${roomDetail.shared_wifi_room.room_number}` : 'phòng bên cạnh') }}
              <template v-if="roomDetail.shared_wifi_issue"> (MẤT KẾT NỐI)</template>
            </span>
            <span v-else-if="roomDetail.wifi_mode === 'none'" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-slate-100 border border-slate-200 text-slate-500 text-xs">
              <v-icon icon="mdi-wifi-off" size="14" /> Không Wifi
            </span>
            <span v-else class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-700 text-xs font-semibold">
              <v-icon icon="mdi-wifi" size="14" /> Router riêng ({{ roomDetail.wifi_router_count || 1 }} bộ)
            </span>
            <span v-if="roomDetail.dependent_rooms && roomDetail.dependent_rooms.length" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-indigo-50 border border-indigo-200 text-indigo-700 text-xs font-semibold">
              <v-icon icon="mdi-access-point-network" size="14" />
              Đang phát Wifi cho {{ roomDetail.dependent_rooms.length }} phòng: {{ roomDetail.dependent_rooms.map(r => 'P.' + r.room_number).join(', ') }}
            </span>
          </div>
        </div>

        <div class="flex items-center gap-2 flex-wrap self-start sm:self-auto">
          <button
            v-if="$can('update', 'Room')"
            type="button"
            class="px-3.5 py-2 rounded-xl text-xs font-semibold border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 flex items-center gap-1.5 transition shadow-2xs cursor-pointer"
            title="Chỉnh sửa số phòng ngủ, phòng khách hoặc chế độ Wifi"
            @click="emit('open-edit-room')"
          >
            <v-icon icon="mdi-pencil-outline" size="15" color="primary" />
            <span>Sửa cơ cấu phòng</span>
          </button>

          <div
            class="px-3.5 py-2 rounded-xl text-xs font-black border flex items-center gap-1.5"
            :class="roomDetail.compliance_status === 'compliant' ? 'bg-emerald-50 text-emerald-800 border-emerald-300' : 'bg-rose-50 text-rose-800 border-rose-300'"
          >
            <v-icon
              :icon="roomDetail.compliance_status === 'compliant' ? 'mdi-check-decagram' : 'mdi-alert-decagram'"
              size="18"
            />
            <span>
              {{ roomDetail.compliance_status === 'compliant' ? 'Đạt chuẩn 100%' : 'Chưa đạt định mức' }}
            </span>
          </div>
        </div>
      </div>

      <!-- Cascade Shared Wifi Issue Warning Banner (Phòng dùng ké bị mất mạng do Router phòng phát bị hỏng) -->
      <div v-if="roomDetail.shared_wifi_issue" class="rounded-2xl p-4 sm:p-5 border bg-rose-50/95 border-rose-300 text-rose-900 shadow-xs">
        <div class="flex items-start gap-3.5">
          <div class="w-10 h-10 rounded-xl bg-rose-100 border border-rose-200 text-rose-600 flex items-center justify-center shrink-0">
            <v-icon icon="mdi-wifi-alert" size="24" />
          </div>
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 flex-wrap">
              <span class="px-2 py-0.5 rounded-md bg-rose-600 text-white font-black text-[10px] tracking-wider uppercase">Sự cố lan truyền</span>
              <h4 class="font-bold text-sm text-rose-900">MẤT KẾT NỐI WIFI DO SỰ CỐ TẠI PHÒNG PHÁT NGUỒN</h4>
            </div>
            <p class="text-xs text-rose-800 font-semibold mt-1">
              {{ roomDetail.shared_wifi_issue.message }}
            </p>
            <p class="text-[11px] text-slate-600 mt-1">
              Phòng {{ roomDetail.room_number }} sử dụng ké sóng Wifi từ <strong>Phòng {{ roomDetail.shared_wifi_issue.source_room_number }}</strong>. Khi Router phòng phát gặp sự cố, phòng này sẽ mất Internet hoàn toàn. Vui lòng ưu tiên kiểm tra, khởi động lại hoặc điều chuyển Router thay thế cho phòng phát.
            </p>
          </div>
        </div>
      </div>

      <!-- Source Room Multi-room Impact Banner (Router phòng này hỏng làm ảnh hưởng các phòng dùng ké) -->
      <div v-if="roomDetail.dependent_rooms && roomDetail.dependent_rooms.length && (hasBrokenRouter || hasMaintenanceRouter)" class="rounded-2xl p-4 sm:p-5 border bg-amber-50/95 border-amber-300 text-amber-950 shadow-xs">
        <div class="flex items-start gap-3.5">
          <div class="w-10 h-10 rounded-xl bg-amber-100 border border-amber-200 text-amber-700 flex items-center justify-center shrink-0">
            <v-icon icon="mdi-alert-octagon" size="24" />
          </div>
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 flex-wrap">
              <span class="px-2 py-0.5 rounded-md bg-amber-600 text-white font-black text-[10px] tracking-wider uppercase">Ưu tiên xử lý cao</span>
              <h4 class="font-bold text-sm text-amber-900">SỰ CỐ ROUTER ẢNH HƯỞNG ĐẾN NHIỀU PHÒNG KHÁC</h4>
            </div>
            <p class="text-xs text-amber-800 font-medium mt-1">
              Router tại phòng này đang phát Wifi cho <strong>{{ roomDetail.dependent_rooms.length }} phòng khác</strong>: 
              <span class="font-bold text-amber-950">{{ roomDetail.dependent_rooms.map(r => 'P.' + r.room_number).join(', ') }}</span>.
            </p>
            <p class="text-[11px] text-amber-700 mt-1">
              Sự cố Router tại phòng này khiến toàn bộ các phòng phụ thuộc ở trên mất mạng. Đề xuất kỹ thuật viên ưu tiên thay thế hoặc điều chuyển router dự phòng cấp cứu.
            </p>
          </div>
        </div>
      </div>

      <!-- Missing items banner -->
      <div v-if="roomDetail.missing_equipments && roomDetail.missing_equipments.length" class="bg-rose-50 border border-rose-200 rounded-2xl p-4 sm:p-5 text-xs text-rose-900 shadow-xs">
        <div class="flex items-center justify-between gap-2 mb-3">
          <h4 class="font-black uppercase tracking-wider flex items-center gap-2 text-rose-800">
            <v-icon icon="mdi-alert-circle" size="20" color="error" />
            <span>Cảnh báo thiết bị còn thiếu so với định mức:</span>
          </h4>
          <AppButton
            v-if="$can('assign', 'Equipment')"
            size="small"
            variant="primary"
            color="error"
            density="compact"
            @click="emit('open-warehouse-modal')"
          >
            <v-icon start icon="mdi-package-down" size="14" />
            Bù thiết bị từ kho
          </AppButton>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
          <div
            v-for="(mis, i) in roomDetail.missing_equipments"
            :key="i"
            class="bg-white/80 border rounded-xl p-2.5 flex items-center justify-between gap-2"
            :class="mis.is_cascade_error ? 'border-amber-300 bg-amber-50/60' : 'border-rose-200'"
          >
            <div>
              <div class="flex items-center gap-1.5 flex-wrap">
                <span class="font-bold text-slate-800 text-xs">{{ mis.category_name || mis.name }}</span>
                <span v-if="mis.is_cascade_error" class="px-1.5 py-0.2 rounded bg-amber-100 text-amber-800 font-bold text-[9px] uppercase tracking-wider">
                  Sự cố dây chuyền
                </span>
              </div>
              <p v-if="mis.error_message" class="text-[11px] text-amber-900 font-medium mt-0.5">
                {{ mis.error_message }}
              </p>
              <p v-else class="text-[11px] text-slate-500 mt-0.5">
                Quy chuẩn: <strong>{{ mis.standard_quantity || 1 }}</strong> • Hiện có: <strong>{{ mis.actual_quantity || 0 }}</strong>
              </p>
            </div>
            <span
              class="px-2 py-0.5 rounded-full font-black text-xs border shrink-0"
              :class="mis.is_cascade_error ? 'bg-amber-100 text-amber-900 border-amber-300' : 'bg-rose-100 text-rose-800 border-rose-200'"
            >
              {{ mis.is_cascade_error ? 'Mất sóng' : `Thiếu ${mis.missing_count || 1}` }}
            </span>
          </div>
        </div>
      </div>

      <!-- Actual Equipment Table by Category -->
      <div class="bg-white rounded-2xl p-4 sm:p-6 border border-slate-200/80 shadow-xs">
        <div class="flex items-center justify-between mb-5 pb-3 border-b border-slate-100">
          <div class="flex items-center gap-2">
            <div class="w-8 h-8 rounded-xl bg-primary/10 text-primary flex items-center justify-center">
              <v-icon icon="mdi-devices" size="18" />
            </div>
            <div>
              <h3 class="text-base font-bold text-slate-800">
                Danh mục Thiết bị Thực tế trong phòng
              </h3>
              <p class="text-[11px] text-slate-400">
                Toàn bộ trang thiết bị đang được lắp đặt và phân bổ trong phòng này
              </p>
            </div>
          </div>

          <AppButton
            v-if="$can('assign', 'Equipment')"
            size="small"
            variant="secondary"
            @click="emit('open-warehouse-modal')"
          >
            <v-icon start icon="mdi-plus" size="14" />
            Gán thêm thiết bị
          </AppButton>
        </div>

        <div v-if="roomDetail.categories && roomDetail.categories.length" class="space-y-4">
          <div
            v-for="cat in roomDetail.categories"
            :key="cat.category_code || cat.category_name"
            class="border border-slate-200 rounded-2xl p-4 bg-slate-50/40"
          >
            <div class="flex items-center justify-between mb-3">
              <h4 class="text-xs font-black text-slate-700 uppercase tracking-wider flex items-center gap-1.5">
                <v-icon :icon="cat.category_icon || getCategoryIcon(cat.category_code)" size="16" class="text-primary" />
                <span>{{ cat.category_name }}</span>
              </h4>
              <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-white border border-slate-200 text-slate-600">
                {{ cat.items?.length || 0 }} thiết bị
              </span>
            </div>

            <div class="space-y-2">
              <div
                v-for="item in cat.items"
                :key="item.id"
                class="flex flex-col sm:flex-row sm:items-center justify-between p-3 bg-white hover:bg-slate-50 transition rounded-xl text-xs gap-3 border border-slate-200/80 shadow-2xs"
              >
                <!-- Left: Thumbnail & Equipment Info -->
                <div class="flex items-center gap-3 min-w-0 flex-1">
                  <div
                    class="relative w-12 h-12 rounded-lg overflow-hidden shrink-0 border border-slate-200/80 bg-slate-100 flex items-center justify-center shadow-2xs group cursor-pointer"
                    :title="(item.photo_url || item.photos?.[0]?.url) ? 'Nhấp để xem ảnh lớn' : (item.model_name || 'Thiết bị')"
                    @click="emit('open-lightbox', item)"
                  >
                    <img
                      v-if="item.photo_url || item.photos?.[0]?.url"
                      :src="resolveMediaUrl(item.photo_url || item.photos?.[0]?.url)"
                      :alt="item.model_name || 'Thiết bị'"
                      class="w-full h-full object-cover transition-transform duration-200 group-hover:scale-110"
                      loading="lazy"
                    />
                    <div v-else class="flex flex-col items-center justify-center text-slate-400">
                      <v-icon :icon="cat.category_icon || getCategoryIcon(cat.category_code)" size="22" />
                    </div>

                    <div
                      v-if="item.photo_url || item.photos?.[0]?.url"
                      class="absolute inset-0 bg-slate-900/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center text-white"
                    >
                      <v-icon icon="mdi-magnify-plus" size="16" />
                    </div>
                  </div>

                  <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-1.5">
                      <router-link
                        :to="`/equipments/${item.id}`"
                        class="font-bold text-slate-800 text-sm hover:text-primary transition-colors truncate"
                      >
                        {{ item.model_name || 'Thiết bị' }}
                      </router-link>
                      <span v-if="item.photos?.length > 1" class="text-[10px] bg-slate-200/70 text-slate-600 px-1.5 py-0.2 rounded-full font-medium">
                        {{ item.photos.length }} ảnh
                      </span>
                    </div>
                    <p class="font-mono text-blue-600 font-semibold mt-0.5 truncate">
                      {{ item.serial_number || 'Chưa gắn Serial' }}
                    </p>
                    <p v-if="item.position_note" class="text-[11px] text-slate-400 mt-0.5 truncate">
                      Vị trí: {{ item.position_note }}
                    </p>
                  </div>
                </div>

                <!-- Right: Status & Actions Toolbar -->
                <div class="flex items-center gap-2 self-end sm:self-center shrink-0">
                  <AppStatusBadge :status="item.status" />

                  <div class="flex items-center gap-1 pl-2 border-l border-slate-200">
                    <!-- Xem chi tiết -->
                    <button
                      class="p-1.5 rounded-lg text-slate-500 hover:text-primary hover:bg-slate-100 transition cursor-pointer"
                      title="Xem chi tiết thiết bị"
                      @click="router.push(`/equipments/${item.id}`)"
                    >
                      <v-icon icon="mdi-eye-outline" size="16" />
                    </button>

                    <!-- Điều chuyển phòng -->
                    <button
                      v-if="$can('assign', 'Equipment')"
                      class="p-1.5 rounded-lg text-slate-500 hover:text-blue-600 hover:bg-slate-100 transition cursor-pointer"
                      title="Điều chuyển / Chuyển phòng"
                      @click="emit('open-assign-modal', item)"
                    >
                      <v-icon icon="mdi-transfer" size="16" />
                    </button>

                    <!-- Báo hỏng -->
                    <button
                      v-if="$can('report_broken', 'Equipment')"
                      class="p-1.5 rounded-lg text-slate-500 hover:text-amber-600 hover:bg-slate-100 transition cursor-pointer"
                      title="Báo hỏng thiết bị"
                      @click="emit('open-report-broken-modal', item)"
                    >
                      <v-icon icon="mdi-wrench-outline" size="16" />
                    </button>

                    <!-- Thu hồi về kho -->
                    <button
                      v-if="$can('assign', 'Equipment')"
                      class="p-1.5 rounded-lg text-slate-500 hover:text-rose-600 hover:bg-rose-50 transition cursor-pointer"
                      title="Thu hồi về kho"
                      @click="emit('confirm-retract', item)"
                    >
                      <v-icon icon="mdi-package-down" size="16" />
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div v-else class="text-center py-12 text-slate-400 text-xs">
          <v-icon icon="mdi-door-open" size="44" class="text-slate-300 mb-2 block mx-auto" />
          Chưa có thiết bị nào được gán vào phòng này.
          <div class="mt-3">
            <AppButton variant="secondary" size="small" @click="emit('open-warehouse-modal')">
              <v-icon start icon="mdi-plus" size="14" />
              Gán thiết bị đầu tiên
            </AppButton>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
