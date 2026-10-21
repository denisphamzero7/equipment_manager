<?php

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
        // 1. Tạo hoặc cập nhật danh mục 'phone' (Điện thoại bàn)
        $phoneCat = DB::table('equipment_categories')->where('code', 'phone')->first();
        if (!$phoneCat) {
            $phoneCatId = DB::table('equipment_categories')->insertGetId([
                'name' => 'Điện thoại bàn',
                'code' => 'phone',
                'icon' => 'mdi-phone-classic',
                'description' => 'Điện thoại bàn nội bộ buồng phòng',
                'sort_order' => 5,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $phoneCatId = $phoneCat->id;
            DB::table('equipment_categories')->where('id', $phoneCatId)->update([
                'name' => 'Điện thoại bàn',
                'icon' => 'mdi-phone-classic',
                'description' => 'Điện thoại bàn nội bộ buồng phòng',
                'updated_at' => now(),
            ]);
        }

        // 2. Tạo hoặc cập nhật model Điện thoại bàn mặc định
        $phoneModel = DB::table('equipment_models')
            ->where('equipment_category_id', $phoneCatId)
            ->first();

        if (!$phoneModel) {
            $phoneModelId = DB::table('equipment_models')->insertGetId([
                'equipment_category_id' => $phoneCatId,
                'name' => 'Điện thoại bàn Panasonic KX-TS500',
                'brand' => 'Panasonic',
                'model_code' => 'TEL-PANA-500',
                'specs' => json_encode([
                    'type' => 'Điện thoại cố định / Nội bộ',
                    'features' => 'Gọi lễ tân, nội bộ buồng phòng',
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $phoneModelId = $phoneModel->id;
        }

        // 3. Bỏ định mức: Xóa các định mức cho 'ac' (Điều hòa), 'fridge' (Tủ lạnh & Minibar), 'appliance' (Thiết bị gia dụng buồng phòng)
        $unwantedCategoryIds = DB::table('equipment_categories')
            ->whereIn('code', ['ac', 'fridge', 'appliance'])
            ->pluck('id');

        $unwantedModelIds = DB::table('equipment_models')
            ->whereIn('equipment_category_id', $unwantedCategoryIds)
            ->pluck('id');

        if ($unwantedModelIds->isNotEmpty()) {
            DB::table('room_standard_equipments')
                ->whereIn('equipment_model_id', $unwantedModelIds)
                ->delete();
        }

        // 4. Thiết lập định mức: mỗi 1 phòng có 1 điện thoại bàn (cho tất cả các hạng phòng room_types)
        if (Schema::hasTable('room_types')) {
            $roomTypes = DB::table('room_types')->get();
            foreach ($roomTypes as $rt) {
                DB::table('room_standard_equipments')->updateOrInsert(
                    [
                        'room_type_id' => $rt->id,
                        'equipment_model_id' => $phoneModelId,
                    ],
                    [
                        'standard_quantity' => 1,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }
        }

        // Xóa cache liên quan
        cache()->flush();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Có thể rollback nếu cần
    }
};
