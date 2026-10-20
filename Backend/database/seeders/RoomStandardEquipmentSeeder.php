<?php

namespace Database\Seeders;

use App\Modules\Equipment\Models\EquipmentModel;
use App\Modules\Equipment\Models\RoomStandardEquipment;
use Illuminate\Database\Seeder;

class RoomStandardEquipmentSeeder extends Seeder
{
    public function run(): void
    {
        // Lấy ID của các model theo model_code
        $models = EquipmentModel::all()->keyBy('model_code');

        // Định mức chuẩn: [room_type_id => [model_code => standard_quantity]]
        $standards = [
            // 1. Tiêu chuẩn (Standard - STD)
            1 => [
                'UA43T6500' => 1,       // TV 43" Samsung
                'RM-GENERIC-V2' => 1,   // Remote đa năng
                'CU/CS-PU9AKH-8' => 1,  // Điều hòa 1.0 HP
                'LCG-50' => 1,          // Minibar 50L
                'Archer-C6-V3' => 1,    // Wifi TP-Link
                'EH-ND11' => 1,         // Máy sấy tóc
                'HD9306' => 1,          // Ấm siêu tốc
            ],
            // 2. Cao cấp (Deluxe - DLX)
            2 => [
                'UA55CU8000' => 1,      // TV 55" 4K Samsung
                'T550' => 1,            // FPT Play Box 4K
                'BN59-01385D' => 1,     // Remote SolarCell
                'FTKB35WAVMV' => 1,     // Điều hòa Daikin 1.5HP
                'LCG-50' => 1,          // Minibar 50L
                'RG-RAP1200(F)' => 1,   // Wifi Ruijie ốp trần
                'ADEL-200N' => 1,       // Két sắt điện tử
                'YDM-7116' => 1,        // Khóa thẻ từ Yale
                'EH-ND11' => 1,         // Máy sấy tóc
                'HD9306' => 1,          // Ấm siêu tốc
            ],
            // 3. Phòng VIP Tổng Thống (Executive Suite - SUT)
            3 => [
                'UA55CU8000' => 2,      // 2 TV 55" 4K (phòng khách + phòng ngủ)
                'T550' => 2,            // 2 FPT Play Box
                'BN59-01385D' => 2,     // 2 Remote SolarCell
                'FTKB35WAVMV' => 2,     // 2 Điều hòa Daikin 1.5HP
                'EUM0930BD-VN' => 1,    // Tủ lạnh 92L
                'RG-RAP1200(F)' => 1,   // Wifi Ruijie
                'ADEL-200N' => 1,       // Két sắt điện tử
                'YDM-7116' => 1,        // Khóa thẻ từ Yale
                'EH-ND11' => 1,         // Máy sấy tóc
                'HD9306' => 1,          // Ấm siêu tốc
            ],
            // 4. Căn hộ Gia Đình (Family Studio - FAM)
            4 => [
                'UA43T6500' => 2,       // 2 TV 43" (2 phòng ngủ)
                'DV9135' => 2,          // 2 Viettel Box 360
                'RM-GENERIC-V2' => 2,   // 2 Remote TV
                'CU/CS-PU9AKH-8' => 2,  // 2 Điều hòa 1.0HP
                'EUM0930BD-VN' => 1,    // Tủ lạnh 92L
                'RG-RAP1200(F)' => 1,   // Wifi Ruijie
                'YDM-7116' => 1,        // Khóa thẻ từ Yale
                'EH-ND11' => 2,         // 2 Máy sấy tóc
                'HD9306' => 1,          // Ấm siêu tốc
            ],
        ];

        foreach ($standards as $roomTypeId => $modelList) {
            foreach ($modelList as $code => $qty) {
                if (isset($models[$code])) {
                    RoomStandardEquipment::updateOrCreate(
                        [
                            'room_type_id' => $roomTypeId,
                            'equipment_model_id' => $models[$code]->id,
                        ],
                        [
                            'standard_quantity' => $qty,
                            'organization_id' => 22,
                        ]
                    );
                }
            }
        }
    }
}
