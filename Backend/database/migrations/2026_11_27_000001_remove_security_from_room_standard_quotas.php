<?php

use App\Modules\Equipment\Models\RoomStandardEquipment;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (
            !Schema::hasTable('equipment_categories') ||
            !Schema::hasTable('equipment_models') ||
            !Schema::hasTable('room_standard_equipments')
        ) {
            return;
        }

        // Bỏ định mức bắt buộc cho nhóm không áp đặt chuẩn (bao gồm 'security': Két sắt & Khóa cửa điện tử)
        $excludedCategoryIds = DB::table('equipment_categories')
            ->whereIn('code', RoomStandardEquipment::NON_MANDATORY_STANDARD_CATEGORIES)
            ->pluck('id');

        if ($excludedCategoryIds->isEmpty()) {
            return;
        }

        $excludedModelIds = DB::table('equipment_models')
            ->whereIn('equipment_category_id', $excludedCategoryIds)
            ->pluck('id');

        if ($excludedModelIds->isNotEmpty()) {
            DB::table('room_standard_equipments')
                ->whereIn('equipment_model_id', $excludedModelIds)
                ->delete();
        }

        cache()->flush();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Không khôi phục định mức bắt buộc cho két sắt / khóa điện tử.
    }
};
