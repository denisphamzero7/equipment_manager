<?php

namespace Database\Seeders;

use App\Modules\Motel\Models\RoomType;
use Illuminate\Database\Seeder;

class RoomTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            [
                'id' => 1,
                'name' => 'Phòng Tiêu Chuẩn (Standard)',
                'code' => 'STD',
                'description' => 'Phòng 1 giường đôi hoặc 2 giường đơn, diện tích 22m2, tiện nghi cơ bản.',
                'hourly_rate' => 100000,
                'daily_rate' => 500000,
            ],
            [
                'id' => 2,
                'name' => 'Phòng Cao Cấp (Deluxe)',
                'code' => 'DLX',
                'description' => 'Phòng ban công ngắm cảnh, diện tích 32m2, smart TV 55", minibar, bồn tắm nằm.',
                'hourly_rate' => 180000,
                'daily_rate' => 950000,
            ],
            [
                'id' => 3,
                'name' => 'Phòng VIP Tổng Thống (Executive Suite)',
                'code' => 'SUT',
                'description' => 'Phòng VIP 2 gian gồm phòng khách sang trọng và phòng ngủ riêng, diện tích 65m2, két sắt, 2 smart TV 4K.',
                'hourly_rate' => 450000,
                'daily_rate' => 2500000,
            ],
            [
                'id' => 4,
                'name' => 'Căn Hộ Gia Đình (Family Studio)',
                'code' => 'FAM',
                'description' => 'Căn hộ gia đình 2 phòng ngủ, diện tích 55m2, tủ lạnh 92L, 2 điều hòa nhiệt độ.',
                'hourly_rate' => 300000,
                'daily_rate' => 1600000,
            ],
        ];

        foreach ($types as $type) {
            RoomType::updateOrCreate(['id' => $type['id']], $type);
        }
    }
}
