<?php

namespace Database\Seeders;

use App\Modules\Equipment\Enums\EquipmentStatusEnum;
use App\Modules\Equipment\Models\EquipmentModel;
use App\Modules\Equipment\Models\RoomEquipment;
use App\Modules\Equipment\Models\RoomStandardEquipment;
use App\Modules\Motel\Models\Room;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class RoomEquipmentSeeder extends Seeder
{
    public function run(): void
    {
        $orgId = Room::value('organization_id') ?: 1;
        $rooms = Room::where('organization_id', $orgId)->get();
        $models = EquipmentModel::all()->keyBy('id');
        $serialCounter = 1000;

        foreach ($rooms as $room) {
            // Trường hợp 1: Phòng B-139 là phòng mới cải tạo chưa lắp thiết bị -> badge slate
            if ($room->block === 'Block B' && (string) $room->room_number === '139') {
                continue;
            }

            // Lấy định mức của loại phòng này
            $standards = RoomStandardEquipment::where('room_type_id', $room->room_type_id)->get();

            foreach ($standards as $std) {
                $model = $models->get($std->equipment_model_id);
                if (!$model) continue;

                $qty = $std->standard_quantity;

                // Trường hợp 2: Phòng A-321 thiếu 1 thiết bị minibar so với định mức
                if ($room->block === 'Block A' && (string) $room->room_number === '321' && $model->model_code === 'LCG-50') {
                    continue; // Bỏ qua không tạo để mô phỏng thiếu định mức
                }

                for ($i = 1; $i <= $qty; $i++) {
                    $serialCounter++;
                    $serialPrefix = strtoupper(substr($model->model_code ?? 'EQ', 0, 4));
                    $blockInitial = substr($room->block, 0, 1) === 'B' && strlen($room->block) > 6 ? $room->block[6] : 'R';
                    $serial = "SN-{$serialPrefix}-{$blockInitial}{$room->room_number}-{$i}";

                    // Xác định trạng thái thiết bị
                    $status = EquipmentStatusEnum::Good->value;
                    $note = null;
                    $installedAt = Carbon::now()->subMonths(rand(1, 12))->subDays(rand(1, 28));

                    $nameLower = mb_strtolower($model->name, 'UTF-8');

                    // Trường hợp 3: Phòng A-122 có TV bị hỏng -> badge red
                    if ($room->block === 'Block A' && (string) $room->room_number === '122' && str_contains($nameLower, 'tv')) {
                        $status = EquipmentStatusEnum::Broken->value;
                        $note = 'Khách lưu trú làm va đập dẫn tới nứt màn hình bên phải, không hiển thị.';
                    }

                    // Trường hợp 4: Phòng A-224 có điều hòa đang bảo trì -> badge yellow
                    if ($room->block === 'Block A' && (string) $room->room_number === '224' && (str_contains($nameLower, 'điều hòa') || str_contains($nameLower, 'máy lạnh'))) {
                        $status = EquipmentStatusEnum::Maintenance->value;
                        $note = 'Điều hòa yếu lạnh, đang chờ kỹ thuật nạp thêm gas R32.';
                    }

                    // Trường hợp 5: Phòng B-227 có remote bị hỏng nút bấm -> badge red
                    if ($room->block === 'Block B' && (string) $room->room_number === '227' && str_contains($nameLower, 'remote')) {
                        $status = EquipmentStatusEnum::Broken->value;
                        $note = 'Liệt nút phím nguồn và tăng giảm âm lượng.';
                    }

                    // Trường hợp 6: Phòng C-341 có bình nóng lạnh đang bảo trì -> badge yellow
                    if ($room->block === 'Block C' && (string) $room->room_number === '341' && str_contains($nameLower, 'bình')) {
                        $status = EquipmentStatusEnum::Maintenance->value;
                        $note = 'Đang kiểm tra rơ-le nhiệt và vệ sinh thanh đốt.';
                    }

                    $position = ($qty > 1) ? "Vị trí {$i}" : 'Vị trí chính';
                    if (str_contains($nameLower, 'tv')) {
                        $position = ($i === 1) ? 'Treo tường phòng khách' : 'Treo tường phòng ngủ';
                    } elseif (str_contains($nameLower, 'wifi')) {
                        $position = 'Gắn ốp trần trung tâm';
                    } elseif (str_contains($nameLower, 'két')) {
                        $position = 'Trong tủ quần áo âm tường';
                    }

                    RoomEquipment::updateOrCreate(
                        ['serial_number' => $serial],
                        [
                            'equipment_model_id' => $model->id,
                            'room_id' => $room->id,
                            'status' => $status,
                            'position_note' => $position,
                            'note' => $note,
                            'installed_at' => $installedAt,
                            'organization_id' => $orgId,
                            'created_by' => 1,
                            'updated_by' => 1,
                        ]
                    );
                }
            }
        }

        // Tạo 12 thiết bị dự phòng lưu trong kho (room_id = null, status = in_stock)
        $stockModels = $models->take(6);
        foreach ($stockModels as $m) {
            for ($k = 1; $k <= 2; $k++) {
                $serialCounter++;
                $code = strtoupper(substr($m->model_code ?? 'WH', 0, 4));
                RoomEquipment::updateOrCreate(
                    ['serial_number' => "KHO-{$code}-{$serialCounter}"],
                    [
                        'equipment_model_id' => $m->id,
                        'room_id' => null,
                        'status' => EquipmentStatusEnum::InStock->value,
                        'position_note' => 'Kệ A3 - Kho Tổng Tầng Hầm B1',
                        'note' => 'Thiết bị lưu kho dự phòng mới 100%, nguyên seal thùng',
                        'installed_at' => null,
                        'organization_id' => $orgId,
                        'created_by' => 1,
                        'updated_by' => 1,
                    ]
                );
            }
        }
    }
}
