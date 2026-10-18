<?php

namespace App\Modules\Equipment\Imports;

use App\Modules\Equipment\Enums\EquipmentStatusEnum;
use App\Modules\Equipment\Models\EquipmentModel;
use App\Modules\Equipment\Models\RoomEquipment;
use App\Modules\Motel\Models\Room;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class EquipmentImport implements ToCollection, WithHeadingRow
{
    protected int $successCount = 0;
    protected int $failedCount = 0;
    protected array $errors = [];

    public function collection(Collection $rows)
    {
        foreach ($rows as $index => $row) {
            $rowNum = $index + 2;

            $modelName = trim($row['mau_thiet_bi'] ?? $row['model_name'] ?? '');
            $serialNumber = trim($row['ma_serial'] ?? $row['serial_number'] ?? '');
            $roomNumber = trim($row['so_phong'] ?? $row['room_number'] ?? '');
            $status = trim($row['trang_thai'] ?? $row['status'] ?? 'good');
            $positionNote = trim($row['vi_tri_trong_phong'] ?? $row['position_note'] ?? '');
            $note = trim($row['ghi_chu'] ?? $row['note'] ?? '');

            if (empty($modelName)) {
                $this->failedCount++;
                $this->errors[] = "Dòng {$rowNum}: Tên mẫu thiết bị (model) không được để trống.";
                continue;
            }

            // Tìm hoặc tra cứu Model
            $model = EquipmentModel::where('name', $modelName)->first();
            if (!$model) {
                $this->failedCount++;
                $this->errors[] = "Dòng {$rowNum}: Không tìm thấy mẫu thiết bị '{$modelName}' trong hệ thống.";
                continue;
            }

            // Tìm phòng nếu có chỉ định
            $roomId = null;
            if (!empty($roomNumber)) {
                $room = Room::where('room_number', $roomNumber)->first();
                $roomId = $room?->id;
            }

            // Map status
            $statusEnum = EquipmentStatusEnum::tryFrom($status) ?? EquipmentStatusEnum::Good;

            if (empty($serialNumber)) {
                $serialNumber = 'EQ-'.strtoupper(uniqid());
            }

            RoomEquipment::updateOrCreate(
                ['serial_number' => $serialNumber],
                [
                    'equipment_model_id' => $model->id,
                    'room_id' => $roomId,
                    'status' => $statusEnum,
                    'position_note' => $positionNote ?: null,
                    'note' => $note ?: null,
                    'installed_at' => $roomId ? now() : null,
                ]
            );

            $this->successCount++;
        }
    }

    public function getResult(): array
    {
        return [
            'success_count' => $this->successCount,
            'failed_count' => $this->failedCount,
            'errors' => $this->errors,
        ];
    }
}
