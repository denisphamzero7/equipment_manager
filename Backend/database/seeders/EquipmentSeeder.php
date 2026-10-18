<?php

namespace Database\Seeders;

use App\Modules\Equipment\Enums\EquipmentStatusEnum;
use App\Modules\Equipment\Models\EquipmentCategory;
use App\Modules\Equipment\Models\EquipmentModel;
use App\Modules\Equipment\Models\RoomEquipment;
use App\Modules\Equipment\Models\RoomStandardEquipment;
use App\Modules\Motel\Models\Room;
use Illuminate\Database\Seeder;

class EquipmentSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Khởi tạo danh mục nhóm thiết bị
        $catTv = EquipmentCategory::firstOrCreate(['code' => 'tv'], [
            'name' => 'Tivi (TV)',
            'icon' => 'tv',
            'description' => 'Màn hình Tivi các kích thước',
            'sort_order' => 1,
        ]);

        $catRemote = EquipmentCategory::firstOrCreate(['code' => 'remote'], [
            'name' => 'Điều khiển (Remote)',
            'icon' => 'remote',
            'description' => 'Remote TV, Remote Điều hòa, Remote Box',
            'sort_order' => 2,
        ]);

        $catBox = EquipmentCategory::firstOrCreate(['code' => 'box'], [
            'name' => 'TV Box / Đầu thu',
            'icon' => 'box',
            'description' => 'Android TV Box, FPT Box, MyTV',
            'sort_order' => 3,
        ]);

        $catWifi = EquipmentCategory::firstOrCreate(['code' => 'wifi'], [
            'name' => 'Thiết bị mạng (Wifi Router)',
            'icon' => 'wifi',
            'description' => 'Router Wifi, Access Point từng phòng',
            'sort_order' => 4,
        ]);

        // 2. Khởi tạo các mẫu thiết bị (Models)
        $modelTv32 = EquipmentModel::firstOrCreate(['name' => 'TV Samsung 32 inch'], [
            'equipment_category_id' => $catTv->id,
            'brand' => 'Samsung',
            'model_code' => 'UA32T4500',
            'specs' => ['size' => '32 inch', 'resolution' => 'HD', 'type' => 'Smart TV'],
        ]);

        $modelTv40 = EquipmentModel::firstOrCreate(['name' => 'TV LG 40 inch'], [
            'equipment_category_id' => $catTv->id,
            'brand' => 'LG',
            'model_code' => '40LM5700',
            'specs' => ['size' => '40 inch', 'resolution' => 'Full HD', 'type' => 'Smart TV'],
        ]);

        $modelBoxFpt = EquipmentModel::firstOrCreate(['name' => 'FPT Play Box 4K'], [
            'equipment_category_id' => $catBox->id,
            'brand' => 'FPT',
            'model_code' => 'T550',
            'specs' => ['ram' => '2GB', 'storage' => '16GB', 'os' => 'Android TV 11'],
        ]);

        $modelRemoteTv = EquipmentModel::firstOrCreate(['name' => 'Remote Tivi đa năng'], [
            'equipment_category_id' => $catRemote->id,
            'brand' => 'Samsung / LG',
            'model_code' => 'RM-GENERIC',
            'specs' => ['type' => 'Infrared / Bluetooth'],
        ]);

        $modelRemoteBox = EquipmentModel::firstOrCreate(['name' => 'Remote Box giọng nói'], [
            'equipment_category_id' => $catRemote->id,
            'brand' => 'FPT Voice',
            'model_code' => 'RM-VOICE',
            'specs' => ['voice_control' => true, 'bluetooth' => true],
        ]);

        $modelWifi = EquipmentModel::firstOrCreate(['name' => 'Wifi Router TP-Link Archer C6'], [
            'equipment_category_id' => $catWifi->id,
            'brand' => 'TP-Link',
            'model_code' => 'Archer-C6',
            'specs' => ['speed' => 'AC1200', 'bands' => '2.4GHz & 5GHz'],
        ]);

        // 3. Khởi tạo định mức mẫu cho phòng (Ví dụ phòng có room_type_id = 1 hoặc phòng mẫu)
        // Tiêu chuẩn 1 phòng VIP: 2 TV 32", 1 TV 40", 4 Box, 4 Remote, 1 Wifi
        $sampleRoomTypeId = 1;
        RoomStandardEquipment::updateOrCreate(
            ['room_type_id' => $sampleRoomTypeId, 'equipment_model_id' => $modelTv32->id],
            ['standard_quantity' => 2]
        );
        RoomStandardEquipment::updateOrCreate(
            ['room_type_id' => $sampleRoomTypeId, 'equipment_model_id' => $modelTv40->id],
            ['standard_quantity' => 1]
        );
        RoomStandardEquipment::updateOrCreate(
            ['room_type_id' => $sampleRoomTypeId, 'equipment_model_id' => $modelBoxFpt->id],
            ['standard_quantity' => 4]
        );
        RoomStandardEquipment::updateOrCreate(
            ['room_type_id' => $sampleRoomTypeId, 'equipment_model_id' => $modelRemoteTv->id],
            ['standard_quantity' => 4]
        );
        RoomStandardEquipment::updateOrCreate(
            ['room_type_id' => $sampleRoomTypeId, 'equipment_model_id' => $modelWifi->id],
            ['standard_quantity' => 1]
        );
    }
}
