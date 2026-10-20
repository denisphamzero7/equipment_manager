<?php

namespace Database\Seeders;

use App\Modules\Equipment\Models\EquipmentCategory;
use App\Modules\Equipment\Models\EquipmentModel;
use Illuminate\Database\Seeder;

class EquipmentMasterSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Khởi tạo danh mục nhóm thiết bị
        $categories = [
            [
                'code' => 'tv',
                'name' => 'Tivi & Màn hình',
                'icon' => 'mdi-television',
                'description' => 'Màn hình Tivi các kích thước (Smart TV, LED TV)',
                'sort_order' => 1,
            ],
            [
                'code' => 'remote',
                'name' => 'Điều khiển cầm tay',
                'icon' => 'mdi-remote',
                'description' => 'Remote TV, Remote Điều hòa, Remote Box',
                'sort_order' => 2,
            ],
            [
                'code' => 'box',
                'name' => 'TV Box & Đầu thu',
                'icon' => 'mdi-set-top-box',
                'description' => 'Android TV Box, FPT Box, TV360 Box',
                'sort_order' => 3,
            ],
            [
                'code' => 'wifi',
                'name' => 'Thiết bị mạng (Wifi Router)',
                'icon' => 'mdi-wifi',
                'description' => 'Router Wifi, Access Point ốp trần phòng khách sạn',
                'sort_order' => 4,
            ],
            [
                'code' => 'ac',
                'name' => 'Điều hòa & Máy lạnh',
                'icon' => 'mdi-air-conditioner',
                'description' => 'Điều hòa Inverter treo tường 1HP - 2HP',
                'sort_order' => 5,
            ],
            [
                'code' => 'fridge',
                'name' => 'Tủ lạnh & Minibar',
                'icon' => 'mdi-fridge-outline',
                'description' => 'Tủ lạnh mini buồng phòng, tủ minibar mặt kính',
                'sort_order' => 6,
            ],
            [
                'code' => 'security',
                'name' => 'Két sắt & Khóa cửa điện tử',
                'icon' => 'mdi-safe',
                'description' => 'Két sắt mã số điện tử, khóa thẻ từ thông minh',
                'sort_order' => 7,
            ],
            [
                'code' => 'appliance',
                'name' => 'Thiết bị buồng phòng gia dụng',
                'icon' => 'mdi-hair-dryer',
                'description' => 'Máy sấy tóc, ấm đun nước siêu tốc, bàn ủi hơi nước',
                'sort_order' => 8,
            ],
        ];

        $catMap = [];
        foreach ($categories as $cat) {
            $created = EquipmentCategory::updateOrCreate(['code' => $cat['code']], $cat);
            $catMap[$cat['code']] = $created->id;
        }

        // 2. Khởi tạo danh sách các Models chi tiết
        $models = [
            // TV
            [
                'name' => 'TV Samsung 43 inch Smart FHD',
                'equipment_category_id' => $catMap['tv'],
                'brand' => 'Samsung',
                'model_code' => 'UA43T6500',
                'specs' => ['size' => '43 inch', 'resolution' => 'Full HD', 'type' => 'Smart TV Tizen OS'],
            ],
            [
                'name' => 'TV Samsung Crystal 4K 55 inch',
                'equipment_category_id' => $catMap['tv'],
                'brand' => 'Samsung',
                'model_code' => 'UA55CU8000',
                'specs' => ['size' => '55 inch', 'resolution' => '4K Ultra HD', 'hdr' => 'HDR10+'],
            ],
            [
                'name' => 'TV LG 43 inch 4K AI ThinQ',
                'equipment_category_id' => $catMap['tv'],
                'brand' => 'LG',
                'model_code' => '43UR7550',
                'specs' => ['size' => '43 inch', 'resolution' => '4K UHD', 'os' => 'webOS 23'],
            ],

            // Remote
            [
                'name' => 'Remote Tivi Samsung SolarCell',
                'equipment_category_id' => $catMap['remote'],
                'brand' => 'Samsung',
                'model_code' => 'BN59-01385D',
                'specs' => ['charging' => 'Solar + Type-C', 'voice' => true],
            ],
            [
                'name' => 'Remote Tivi đa năng hồng ngoại',
                'equipment_category_id' => $catMap['remote'],
                'brand' => 'Universal',
                'model_code' => 'RM-GENERIC-V2',
                'specs' => ['type' => 'Infrared', 'range' => '8m'],
            ],
            [
                'name' => 'Remote Điều hòa đa năng',
                'equipment_category_id' => $catMap['remote'],
                'brand' => 'ChungHop',
                'model_code' => 'K-1028E',
                'specs' => ['type' => 'AC Remote', 'compatible' => 'Daikin, Panasonic, LG'],
            ],

            // TV Box
            [
                'name' => 'FPT Play Box 4K',
                'equipment_category_id' => $catMap['box'],
                'brand' => 'FPT',
                'model_code' => 'T550',
                'specs' => ['ram' => '2GB', 'storage' => '16GB', 'os' => 'Android TV 11'],
            ],
            [
                'name' => 'Viettel TV360 Box 4K VIP',
                'equipment_category_id' => $catMap['box'],
                'brand' => 'Viettel',
                'model_code' => 'DV9135',
                'specs' => ['ram' => '2GB', 'resolution' => '4K 60fps', 'voice_remote' => true],
            ],

            // Wifi
            [
                'name' => 'Wifi Router TP-Link Archer C6',
                'equipment_category_id' => $catMap['wifi'],
                'brand' => 'TP-Link',
                'model_code' => 'Archer-C6-V3',
                'specs' => ['speed' => 'AC1200', 'bands' => '2.4GHz (300Mbps) + 5GHz (867Mbps)'],
            ],
            [
                'name' => 'Access Point Ruijie Reyee Ốp Trần',
                'equipment_category_id' => $catMap['wifi'],
                'brand' => 'Ruijie',
                'model_code' => 'RG-RAP1200(F)',
                'specs' => ['speed' => 'AC1300', 'poe' => true, 'mesh' => true],
            ],

            // Điều hòa
            [
                'name' => 'Điều hòa Daikin Inverter 1.5 HP',
                'equipment_category_id' => $catMap['ac'],
                'brand' => 'Daikin',
                'model_code' => 'FTKB35WAVMV',
                'specs' => ['capacity' => '1.5 HP (12.000 BTU)', 'gas' => 'R32', 'inverter' => true],
            ],
            [
                'name' => 'Điều hòa Panasonic Inverter 1.0 HP',
                'equipment_category_id' => $catMap['ac'],
                'brand' => 'Panasonic',
                'model_code' => 'CU/CS-PU9AKH-8',
                'specs' => ['capacity' => '1.0 HP (9.000 BTU)', 'gas' => 'R32', 'nanoe_g' => true],
            ],

            // Tủ lạnh / Minibar
            [
                'name' => 'Tủ lạnh Minibar Alaska 50 Lít',
                'equipment_category_id' => $catMap['fridge'],
                'brand' => 'Alaska',
                'model_code' => 'LCG-50',
                'specs' => ['capacity' => '50 Lít', 'door' => 'Cửa kính cường lực', 'silent' => true],
            ],
            [
                'name' => 'Tủ lạnh Electrolux 92 Lít',
                'equipment_category_id' => $catMap['fridge'],
                'brand' => 'Electrolux',
                'model_code' => 'EUM0930BD-VN',
                'specs' => ['capacity' => '92 Lít', 'type' => '1 cửa', 'cooling' => 'Trực tiếp'],
            ],

            // Két sắt & Khóa cửa
            [
                'name' => 'Két sắt điện tử Adel Hotel Safe',
                'equipment_category_id' => $catMap['security'],
                'brand' => 'Adel',
                'model_code' => 'ADEL-200N',
                'specs' => ['lock' => 'Mã số LED + Chìa cơ khẩn cấp', 'size' => '200x420x370mm'],
            ],
            [
                'name' => 'Khóa cửa thẻ từ Yale Hotel Lock',
                'equipment_category_id' => $catMap['security'],
                'brand' => 'Yale',
                'model_code' => 'YDM-7116',
                'specs' => ['card' => 'RFID Mifare', 'material' => 'Hợp kim kẽm nguyên khối'],
            ],

            // Thiết bị buồng phòng gia dụng
            [
                'name' => 'Máy sấy tóc Panasonic 1200W',
                'equipment_category_id' => $catMap['appliance'],
                'brand' => 'Panasonic',
                'model_code' => 'EH-ND11',
                'specs' => ['power' => '1200W', 'speeds' => '2 tốc độ sấy'],
            ],
            [
                'name' => 'Ấm đun nước siêu tốc Philips 1.8L',
                'equipment_category_id' => $catMap['appliance'],
                'brand' => 'Philips',
                'model_code' => 'HD9306',
                'specs' => ['capacity' => '1.8 Lít', 'material' => 'Inox 304 không gỉ', 'power' => '1800W'],
            ],
        ];

        foreach ($models as $m) {
            EquipmentModel::updateOrCreate(
                ['model_code' => $m['model_code']],
                $m
            );
        }
    }
}
