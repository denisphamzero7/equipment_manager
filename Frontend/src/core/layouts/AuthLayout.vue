<template>
  <div class="min-h-screen w-full flex bg-slate-50">
    <!-- Left Hero Banner (Hidden on mobile) -->
    <div class="hidden lg:flex lg:w-1/2 bg-gradient-to-br from-blue-700 via-indigo-800 to-slate-900 p-12 flex-col justify-between text-white relative overflow-hidden">
      <!-- Background subtle decorative shapes -->
      <div class="absolute -right-24 -bottom-24 w-96 h-96 rounded-full bg-blue-500/10 blur-3xl pointer-events-none" />
      <div class="absolute left-1/4 top-1/4 w-80 h-80 rounded-full bg-indigo-500/20 blur-3xl pointer-events-none" />

      <!-- Top Branding -->
      <div class="relative z-10 flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-white/10 backdrop-blur border border-white/20 flex items-center justify-center">
          <v-icon icon="mdi-shield-home" size="24" color="white" />
        </div>
        <div>
          <h2 class="text-xl font-bold tracking-tight">Equipment Manager</h2>
          <p class="text-xs text-blue-200">Hệ thống Quản lý Trang thiết bị Khách sạn</p>
        </div>
      </div>

      <!-- Center Value Proposition -->
      <div class="relative z-10 max-w-lg">
        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/10 border border-white/20 text-xs font-medium mb-6">
          <v-icon icon="mdi-sparkles" size="14" color="amber" />
          <span>Vận hành chuẩn xác & thông minh</span>
        </div>
        <h1 class="text-3xl font-extrabold leading-snug tracking-tight mb-4">
          Kiểm soát toàn bộ vòng đời thiết bị khách sạn từ Sơ đồ mặt bằng.
        </h1>
        <p class="text-sm text-blue-100/90 leading-relaxed">
          Quản lý cá thể theo Serial/QR, đối soát định mức tiêu chuẩn theo loại phòng, theo dõi bảo trì, điều chuyển kho và báo cáo thống kê trực quan.
        </p>

        <!-- Feature pills -->
        <div class="grid grid-cols-2 gap-3 mt-8">
          <div class="flex items-center gap-2 text-xs bg-white/5 border border-white/10 rounded-lg p-2.5">
            <v-icon icon="mdi-map-marker-radius" size="18" color="cyan" />
            <span>Sơ đồ Hotel Map View</span>
          </div>
          <div class="flex items-center gap-2 text-xs bg-white/5 border border-white/10 rounded-lg p-2.5">
            <v-icon icon="mdi-barcode-scan" size="18" color="emerald" />
            <span>Đối soát định mức phòng</span>
          </div>
          <div class="flex items-center gap-2 text-xs bg-white/5 border border-white/10 rounded-lg p-2.5">
            <v-icon icon="mdi-wrench-clock" size="18" color="amber" />
            <span>Báo hỏng & Lịch sử sửa</span>
          </div>
          <div class="flex items-center gap-2 text-xs bg-white/5 border border-white/10 rounded-lg p-2.5">
            <v-icon icon="mdi-file-excel" size="18" color="teal" />
            <span>Xuất nhập Excel siêu tốc</span>
          </div>
        </div>
      </div>

      <!-- Bottom Footer -->
      <div class="relative z-10 text-xs text-blue-200/70">
        © 2026 Equipment Manager System. All rights reserved.
      </div>
    </div>

    <!-- Right Form Section -->
    <div class="w-full lg:w-1/2 flex items-center justify-center p-4 sm:p-8 md:p-12">
      <div class="w-full max-w-md">
        <slot />
      </div>
    </div>
  </div>
</template>
