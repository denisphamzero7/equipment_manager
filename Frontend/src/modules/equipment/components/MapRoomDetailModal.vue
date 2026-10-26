<script setup>
import { useRouter } from 'vue-router'
import { resolveMediaUrl } from '@/core/helpers/formatters'
import AppModal from '@/core/components/AppModal.vue'
import AppButton from '@/core/components/AppButton.vue'
import AppStatusBadge from '@/core/components/AppStatusBadge.vue'

defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  roomDetail: {
    type: Object,
    default: null,
  },
  roomSummary: {
    type: Object,
    default: null,
  },
  loading: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits([
  'update:modelValue',
  'open-edit-room',
  'open-lightbox',
  'assign-more',
])

const router = useRouter()

function getCategoryIcon(code) {
  const map = {
    tv: 'mdi-television',
    remote: 'mdi-remote',
    box: 'mdi-set-top-box',
    wifi: 'mdi-wifi',
    phone: 'mdi-phone-classic',
    telephone: 'mdi-phone-classic',
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
  <AppModal
    :model-value="modelValue"
    :title="`Chi tiết Phòng ${roomDetail?.room_number || roomSummary?.room_number || ''}`"
    :subtitle="roomDetail?.room_type || roomSummary?.room_type ? `Loại phòng: ${roomDetail?.room_type || roomSummary?.room_type}` : 'Đối soát định mức trang thiết bị tiêu chuẩn'"
    max-width="640"
    @update:model-value="val => emit('update:modelValue', val)"
  >
    <!-- Skeleton loader / summary while loading -->
    <div v-if="loading" class="mb-4 rounded-xl border border-blue-200 bg-blue-50 p-3.5">
      <div class="flex items-center gap-2 text-xs font-semibold text-blue-800">
        <v-progress-circular indeterminate color="primary" size="18" />
        <span>Đang tải danh sách thiết bị đầy đủ...</span>
      </div>
      <div v-if="roomSummary" class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-600">
        <span>{{ roomSummary.block }} • Tầng {{ roomSummary.floor }}</span>
        <span>{{ roomSummary.stats?.total_equipments ?? 0 }} thiết bị</span>
        <span v-if="roomSummary.stats?.missing_count">Thiếu {{ roomSummary.stats.missing_count }}</span>
        <span v-if="roomSummary.stats?.broken_count">Hỏng {{ roomSummary.stats.broken_count }}</span>
      </div>
    </div>

    <div v-if="roomDetail" class="space-y-6">
      <!-- Compliance Status Banner -->
      <div
        class="p-4 rounded-xl border flex items-center justify-between text-xs"
        :class="roomDetail.compliance_status === 'compliant' ? 'bg-emerald-50 border-emerald-200 text-emerald-800' : 'bg-rose-50 border-rose-200 text-rose-800'"
      >
        <div class="flex items-center gap-2.5">
          <v-icon
            :icon="roomDetail.compliance_status === 'compliant' ? 'mdi-check-decagram' : 'mdi-alert-decagram'"
            size="22"
            :color="roomDetail.compliance_status === 'compliant' ? 'success' : 'error'"
          />
          <div>
            <p class="font-bold">
              {{ roomDetail.compliance_status === 'compliant' ? 'Đạt chuẩn 100% định mức phòng' : 'Chưa đạt định mức tiêu chuẩn phòng' }}
            </p>
            <p class="text-[11px] opacity-80 mt-0.5">
              {{ roomDetail.compliance_status === 'compliant' ? 'Toàn bộ thiết bị đều đầy đủ và hoạt động tốt.' : 'Phát hiện thiếu hụt thiết bị so với cấu hình chuẩn của loại phòng này.' }}
            </p>
          </div>
        </div>
      </div>

      <!-- Room Space Composition & Dynamic Quota Info Banner -->
      <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200/80 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 text-xs">
        <div class="space-y-1">
          <div class="flex items-center gap-2">
            <span class="font-bold text-slate-800 flex items-center gap-1">
              <v-icon icon="mdi-home-analytics" size="16" color="primary" />
              Cơ cấu không gian:
            </span>
            <span class="px-2 py-0.5 rounded-full bg-blue-100 text-blue-800 font-bold text-[11px]">
              {{ roomDetail.bedroom_count ?? 1 }} Phòng ngủ
              <template v-if="(roomDetail.living_room_count ?? 0) > 0">
                • {{ roomDetail.living_room_count }} Phòng khách
              </template>
            </span>
          </div>
          <div class="text-[11px] text-slate-500 flex items-center gap-2.5 flex-wrap">
            <span>📺 Định mức TV, Box & Remote: <strong>{{ Math.max(1, (roomDetail.bedroom_count ?? 1) + (roomDetail.living_room_count ?? 0)) }} bộ</strong></span>
            <span>•</span>
            <span v-if="roomDetail.wifi_mode === 'shared'" class="text-purple-700 font-semibold flex items-center gap-1">
              <v-icon icon="mdi-wifi-arrow-left-right" size="13" />
              Dùng ké Wifi: {{ roomDetail.shared_wifi_note || (roomDetail.shared_wifi_room ? `Phòng ${roomDetail.shared_wifi_room.room_number}` : 'Phòng bên cạnh') }}
            </span>
            <span v-else-if="roomDetail.wifi_mode === 'none'" class="text-slate-400">
              🚫 Không có Wifi
            </span>
            <span v-else class="text-emerald-700 font-semibold flex items-center gap-1">
              <v-icon icon="mdi-wifi" size="13" />
              Router riêng (1 bộ)
            </span>
            <span>•</span>
            <span class="text-indigo-700 font-semibold flex items-center gap-1">
              <v-icon icon="mdi-phone-classic" size="13" />
              Điện thoại bàn (1 máy)
            </span>
          </div>
        </div>

        <button
          v-if="$can('update', 'Room')"
          type="button"
          class="px-2.5 py-1.5 rounded-lg border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 font-semibold text-xs flex items-center gap-1 shrink-0 transition shadow-2xs cursor-pointer"
          title="Chỉnh sửa số phòng ngủ, phòng khách hoặc chế độ Wifi"
          @click="emit('open-edit-room')"
        >
          <v-icon icon="mdi-pencil-outline" size="14" color="primary" />
          <span>Sửa cơ cấu phòng</span>
        </button>
      </div>

      <!-- Cascade Shared Wifi Alert Banner -->
      <div v-if="roomDetail.shared_wifi_issue" class="rounded-xl p-3.5 border bg-rose-50 border-rose-300 text-rose-900 flex items-start gap-3 shadow-2xs">
        <v-icon icon="mdi-wifi-alert" size="22" class="text-rose-600 shrink-0 mt-0.5" />
        <div class="flex-1 min-w-0">
          <div class="font-bold text-xs text-rose-900 flex items-center gap-1.5 flex-wrap">
            <span class="px-1.5 py-0.2 rounded bg-rose-600 text-white text-[9px] uppercase font-black">Cảnh báo dây chuyền</span>
            <span>MẤT WIFI DO SỰ CỐ TẠI PHÒNG PHÁT</span>
          </div>
          <p class="text-xs text-rose-800 font-bold mt-1">{{ roomDetail.shared_wifi_issue.message }}</p>
          <div v-if="roomDetail.shared_wifi_issue.broken_reason" class="mt-2 p-2 rounded-lg bg-rose-100/90 border border-rose-200 text-xs text-rose-950 font-medium flex items-center gap-2">
            <v-icon icon="mdi-alert-octagon-outline" size="16" class="text-rose-600 shrink-0" />
            <span>Lý do hỏng: <strong class="font-bold text-rose-900">{{ roomDetail.shared_wifi_issue.broken_reason }}</strong></span>
          </div>
          <p class="text-[11px] text-slate-600 mt-1.5">Phòng này phụ thuộc mạng từ <strong>Phòng {{ roomDetail.shared_wifi_issue.source_room_number }}</strong>. Khi Router phòng đó gặp sự cố, phòng này sẽ bị gián đoạn Internet.</p>
        </div>
      </div>

      <!-- Sharing wifi info banner -->
      <div v-if="roomDetail.dependent_rooms && roomDetail.dependent_rooms.length" class="rounded-xl p-3 border bg-indigo-50 border-indigo-200 text-indigo-950 flex items-center gap-2.5 text-xs shadow-2xs">
        <v-icon icon="mdi-access-point-network" size="20" class="text-indigo-600 shrink-0" />
        <div class="flex-1 min-w-0">
          <span class="font-bold text-indigo-900">Phòng phát Wifi trung tâm:</span>
          <span class="text-indigo-800 ml-1">Đang cấp mạng cho {{ roomDetail.dependent_rooms.length }} phòng khác ({{ roomDetail.dependent_rooms.map(r => 'P.' + r.room_number).join(', ') }}).</span>
        </div>
      </div>

      <!-- Missing Equipments Alert (if any) -->
      <div v-if="roomDetail.missing_equipments && roomDetail.missing_equipments.length" class="bg-amber-50 p-4 rounded-xl border border-amber-200">
        <h4 class="text-xs font-bold text-amber-900 uppercase tracking-wider mb-2 flex items-center gap-1.5">
          <v-icon icon="mdi-alert-circle" size="16" color="warning" />
          <span>Thiết bị còn thiếu theo định mức:</span>
        </h4>
        <ul class="space-y-1 text-xs text-amber-800 list-disc list-inside">
          <li v-for="(mis, i) in roomDetail.missing_equipments" :key="i">
            <strong>{{ mis.category_name || mis.name }}</strong>: Thiếu {{ mis.missing_count || 1 }} chiếc (Tiêu chuẩn: {{ mis.standard_quantity || 1 }}, Thực tế: {{ mis.actual_quantity || 0 }})
          </li>
        </ul>
      </div>

      <!-- Actual Equipments in Room -->
      <div>
        <h4 class="text-xs font-bold text-slate-800 uppercase tracking-wider mb-3">
          Thiết bị thực tế trong phòng
        </h4>

        <div v-if="roomDetail.categories && roomDetail.categories.length" class="space-y-4">
          <div
            v-for="cat in roomDetail.categories"
            :key="cat.category_code || cat.category_name"
            class="border border-slate-200 rounded-xl p-3.5"
          >
            <p class="text-xs font-bold text-slate-700 mb-2 flex items-center justify-between">
              <span>{{ cat.category_name }}</span>
              <span class="text-[11px] text-slate-400 font-normal">{{ cat.items?.length || 0 }} thiết bị</span>
            </p>

            <div class="space-y-2">
              <div
                v-for="item in cat.items"
                :key="item.id"
                class="flex items-center justify-between gap-3 p-2.5 bg-slate-50 hover:bg-slate-100/80 transition-colors rounded-xl text-xs border border-slate-100"
              >
                <!-- Equipment Thumbnail -->
                <div
                  class="relative w-12 h-12 rounded-lg overflow-hidden shrink-0 border border-slate-200/80 bg-white flex items-center justify-center shadow-2xs group cursor-pointer"
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

                  <!-- Hover zoom overlay if has image -->
                  <div
                    v-if="item.photo_url || item.photos?.[0]?.url"
                    class="absolute inset-0 bg-slate-900/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center text-white"
                  >
                    <v-icon icon="mdi-magnify-plus" size="16" />
                  </div>
                </div>

                <!-- Equipment Info -->
                <div class="min-w-0 flex-1">
                  <div class="flex items-center gap-1.5">
                    <router-link
                      :to="`/equipments/${item.id}`"
                      class="font-bold text-slate-800 hover:text-primary transition-colors truncate"
                      title="Xem chi tiết thiết bị"
                    >
                      {{ item.model_name || 'Thiết bị' }}
                    </router-link>
                    <span v-if="item.photos?.length > 1" class="text-[10px] bg-slate-200/70 text-slate-600 px-1.5 py-0.2 rounded-full font-medium">
                      {{ item.photos.length }} ảnh
                    </span>
                  </div>
                  <p class="text-[11px] font-mono text-blue-600 font-medium truncate mt-0.5">
                    {{ item.serial_number || 'Chưa gắn Serial' }}
                  </p>
                  <p v-if="item.position_note" class="text-[10px] text-slate-400 truncate mt-0.5">
                    Vị trí: {{ item.position_note }}
                  </p>
                </div>

                <!-- Right: Status & Link -->
                <div class="flex items-center gap-1.5 shrink-0">
                  <AppStatusBadge :status="item.status" />
                  <button
                    type="button"
                    class="p-1 rounded-lg text-slate-400 hover:text-primary hover:bg-white transition cursor-pointer"
                    title="Xem trang chi tiết thiết bị"
                    @click="router.push(`/equipments/${item.id}`)"
                  >
                    <v-icon icon="mdi-chevron-right" size="18" />
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div v-else class="text-center py-6 text-slate-400 text-xs">
          Phòng này chưa có bất kỳ thiết bị nào được gán.
        </div>
      </div>
    </div>

    <template #footer>
      <v-btn
        variant="tonal"
        color="secondary"
        @click="emit('update:modelValue', false)"
      >
        Đóng
      </v-btn>
      <AppButton
        v-if="roomDetail?.room_id || roomDetail?.id || roomSummary?.id"
        variant="tonal"
        color="primary"
        icon="mdi-floor-plan"
        @click="router.push({ path: '/equipments/map-view', query: { block: roomDetail?.block || roomSummary?.block, floor: roomDetail?.floor || roomSummary?.floor } })"
      >
        Xem trên Sơ đồ Map
      </AppButton>
      <AppButton
        v-if="$can('assign', 'Equipment')"
        color="primary"
        icon="mdi-plus"
        @click="emit('assign-more', roomDetail?.room_id || roomDetail?.id || roomSummary?.id)"
      >
        Gán thêm thiết bị
      </AppButton>
    </template>
  </AppModal>
</template>
