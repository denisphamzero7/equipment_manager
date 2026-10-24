<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('rooms') && !Schema::hasColumn('rooms', 'wifi_router_count')) {
            Schema::table('rooms', function (Blueprint $table) {
                $table->unsignedInteger('wifi_router_count')->default(1)->after('wifi_mode')->comment('Số lượng Router Wifi định mức cho phòng (1, 2, 3...)');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('rooms') && Schema::hasColumn('rooms', 'wifi_router_count')) {
            Schema::table('rooms', function (Blueprint $table) {
                $table->dropColumn('wifi_router_count');
            });
        }
    }
};
