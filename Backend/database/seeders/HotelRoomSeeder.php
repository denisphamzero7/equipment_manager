<?php

namespace Database\Seeders;

use App\Modules\Motel\Enums\RoomStatusEnum;
use App\Modules\Motel\Models\Room;
use App\Modules\Motel\Models\RoomType;
use Illuminate\Database\Seeder;

class HotelRoomSeeder extends Seeder
{
    public function run(): void
    {
        $orgId = 22;

        // Block A: 5 Tầng, tổng cộng 24 phòng
        // Tầng 1 - 2: Standard & Deluxe (101..105, 201..205)
        // Tầng 3 - 4: Deluxe & Family (301..305, 401..405)
        // Tầng 5: Suite VIP (501..504)
        $roomsBlockA = [
            // Tầng 1
            ['room_number' => 101, 'floor' => 1, 'col' => 1, 'type_id' => 1, 'status' => RoomStatusEnum::Ready->value, 'desc' => 'Phòng Tiêu chuẩn 1 giường King'],
            ['room_number' => 102, 'floor' => 1, 'col' => 2, 'type_id' => 1, 'status' => RoomStatusEnum::Occupied->value, 'desc' => 'Phòng Tiêu chuẩn 2 giường đơn'],
            ['room_number' => 103, 'floor' => 1, 'col' => 3, 'type_id' => 1, 'status' => RoomStatusEnum::Ready->value, 'desc' => 'Phòng Tiêu chuẩn 1 giường đôi'],
            ['room_number' => 104, 'floor' => 1, 'col' => 4, 'type_id' => 2, 'status' => RoomStatusEnum::NotReady->value, 'desc' => 'Phòng Deluxe view sân vườn'],
            ['room_number' => 105, 'floor' => 1, 'col' => 5, 'type_id' => 2, 'status' => RoomStatusEnum::Ready->value, 'desc' => 'Phòng Deluxe view hồ bơi'],

            // Tầng 2
            ['room_number' => 201, 'floor' => 2, 'col' => 1, 'type_id' => 1, 'status' => RoomStatusEnum::Ready->value, 'desc' => 'Phòng Tiêu chuẩn ban công'],
            ['room_number' => 202, 'floor' => 2, 'col' => 2, 'type_id' => 1, 'status' => RoomStatusEnum::Occupied->value, 'desc' => 'Phòng Tiêu chuẩn yên tĩnh'],
            ['room_number' => 203, 'floor' => 2, 'col' => 2, 'type_id' => 2, 'status' => RoomStatusEnum::Ready->value, 'desc' => 'Phòng Deluxe ban công thoáng mát'],
            ['room_number' => 204, 'floor' => 2, 'col' => 4, 'type_id' => 2, 'status' => RoomStatusEnum::Maintenance->value, 'desc' => 'Phòng Deluxe đang kiểm tra điều hòa'],
            ['room_number' => 205, 'floor' => 2, 'col' => 5, 'type_id' => 2, 'status' => RoomStatusEnum::Ready->value, 'desc' => 'Phòng Deluxe góc view thành phố'],

            // Tầng 3
            ['room_number' => 301, 'floor' => 3, 'col' => 1, 'type_id' => 2, 'status' => RoomStatusEnum::Ready->value, 'desc' => 'Phòng Deluxe giường đôi cỡ lớn'],
            ['room_number' => 302, 'floor' => 3, 'col' => 2, 'type_id' => 2, 'status' => RoomStatusEnum::Occupied->value, 'desc' => 'Phòng Deluxe hướng hồ'],
            ['room_number' => 303, 'floor' => 3, 'col' => 3, 'type_id' => 4, 'status' => RoomStatusEnum::Occupied->value, 'desc' => 'Căn hộ Gia Đình 2 phòng ngủ'],
            ['room_number' => 304, 'floor' => 3, 'col' => 4, 'type_id' => 4, 'status' => RoomStatusEnum::Ready->value, 'desc' => 'Căn hộ Gia Đình ban công rộng'],
            ['room_number' => 305, 'floor' => 3, 'col' => 5, 'type_id' => 2, 'status' => RoomStatusEnum::Ready->value, 'desc' => 'Phòng Deluxe view thành phố tầng cao'],

            // Tầng 4
            ['room_number' => 401, 'floor' => 4, 'col' => 1, 'type_id' => 2, 'status' => RoomStatusEnum::Ready->value, 'desc' => 'Phòng Deluxe cao cấp'],
            ['room_number' => 402, 'floor' => 4, 'col' => 2, 'type_id' => 2, 'status' => RoomStatusEnum::NotReady->value, 'desc' => 'Phòng Deluxe vừa trả phòng'],
            ['room_number' => 403, 'floor' => 4, 'col' => 3, 'type_id' => 4, 'status' => RoomStatusEnum::Ready->value, 'desc' => 'Căn hộ Studio gia đình hiện đại'],
            ['room_number' => 404, 'floor' => 4, 'col' => 4, 'type_id' => 4, 'status' => RoomStatusEnum::Occupied->value, 'desc' => 'Căn hộ Studio gia đình cao cấp'],
            ['room_number' => 405, 'floor' => 4, 'col' => 5, 'type_id' => 2, 'status' => RoomStatusEnum::Ready->value, 'desc' => 'Phòng Deluxe view hoàng hôn'],

            // Tầng 5 - Penthouse & VIP Suite
            ['room_number' => 501, 'floor' => 5, 'col' => 1, 'type_id' => 3, 'status' => RoomStatusEnum::Ready->value, 'desc' => 'Executive Suite VIP 01 - View Panorama'],
            ['room_number' => 502, 'floor' => 5, 'col' => 2, 'type_id' => 3, 'status' => RoomStatusEnum::Occupied->value, 'desc' => 'Executive Suite VIP 02 - Tổng Thống'],
            ['room_number' => 503, 'floor' => 5, 'col' => 3, 'type_id' => 3, 'status' => RoomStatusEnum::Ready->value, 'desc' => 'Executive Suite VIP 03 - Phòng đôi hạng sang'],
            ['room_number' => 504, 'floor' => 5, 'col' => 4, 'type_id' => 3, 'status' => RoomStatusEnum::Ready->value, 'desc' => 'Executive Suite VIP 04 - Đầy đủ tiện nghi cao cấp'],
        ];

        foreach ($roomsBlockA as $item) {
            Room::updateOrCreate(
                [
                    'organization_id' => $orgId,
                    'block' => 'Block A',
                    'room_number' => $item['room_number'],
                ],
                [
                    'name' => "Phòng {$item['room_number']} (Block A)",
                    'description' => $item['desc'],
                    'floor' => $item['floor'],
                    'grid_row' => 1,
                    'grid_col' => $item['col'],
                    'room_type_id' => $item['type_id'],
                    'status' => $item['status'],
                    'price' => match ($item['type_id']) {
                        1 => 500000,
                        2 => 950000,
                        3 => 2500000,
                        4 => 1600000,
                        default => 600000,
                    },
                ]
            );
        }

        // Block B: 3 Tầng, tổng cộng 12 phòng
        $roomsBlockB = [
            // Tầng 1
            ['room_number' => 101, 'floor' => 1, 'col' => 1, 'type_id' => 1, 'status' => RoomStatusEnum::Ready->value, 'desc' => 'Phòng Standard B101'],
            ['room_number' => 102, 'floor' => 1, 'col' => 2, 'type_id' => 1, 'status' => RoomStatusEnum::Occupied->value, 'desc' => 'Phòng Standard B102'],
            ['room_number' => 103, 'floor' => 1, 'col' => 3, 'type_id' => 1, 'status' => RoomStatusEnum::Ready->value, 'desc' => 'Phòng Standard B103'],
            ['room_number' => 104, 'floor' => 1, 'col' => 4, 'type_id' => 1, 'status' => RoomStatusEnum::Ready->value, 'desc' => 'Phòng Standard B104'],

            // Tầng 2
            ['room_number' => 201, 'floor' => 2, 'col' => 1, 'type_id' => 1, 'status' => RoomStatusEnum::Ready->value, 'desc' => 'Phòng Standard B201'],
            ['room_number' => 202, 'floor' => 2, 'col' => 2, 'type_id' => 1, 'status' => RoomStatusEnum::NotReady->value, 'desc' => 'Phòng Standard B202'],
            ['room_number' => 203, 'floor' => 2, 'col' => 3, 'type_id' => 2, 'status' => RoomStatusEnum::Ready->value, 'desc' => 'Phòng Deluxe B203'],
            ['room_number' => 204, 'floor' => 2, 'col' => 4, 'type_id' => 2, 'status' => RoomStatusEnum::Occupied->value, 'desc' => 'Phòng Deluxe B204'],

            // Tầng 3
            ['room_number' => 301, 'floor' => 3, 'col' => 1, 'type_id' => 2, 'status' => RoomStatusEnum::Ready->value, 'desc' => 'Phòng Deluxe B301'],
            ['room_number' => 302, 'floor' => 3, 'col' => 2, 'type_id' => 2, 'status' => RoomStatusEnum::Ready->value, 'desc' => 'Phòng Deluxe B302'],
            ['room_number' => 303, 'floor' => 3, 'col' => 3, 'type_id' => 4, 'status' => RoomStatusEnum::Ready->value, 'desc' => 'Căn hộ Gia Đình B303'],
            ['room_number' => 304, 'floor' => 3, 'col' => 4, 'type_id' => 4, 'status' => RoomStatusEnum::Ready->value, 'desc' => 'Căn hộ Gia Đình B304'],
        ];

        foreach ($roomsBlockB as $item) {
            Room::updateOrCreate(
                [
                    'organization_id' => $orgId,
                    'block' => 'Block B',
                    'room_number' => $item['room_number'],
                ],
                [
                    'name' => "Phòng B-{$item['room_number']} (Block B)",
                    'description' => $item['desc'],
                    'floor' => $item['floor'],
                    'grid_row' => 1,
                    'grid_col' => $item['col'],
                    'room_type_id' => $item['type_id'],
                    'status' => $item['status'],
                    'price' => match ($item['type_id']) {
                        1 => 500000,
                        2 => 950000,
                        4 => 1600000,
                        default => 550000,
                    },
                ]
            );
        }
    }
}
