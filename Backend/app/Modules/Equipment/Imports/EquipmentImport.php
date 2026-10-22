<?php

namespace App\Modules\Equipment\Imports;

use App\Modules\Equipment\Enums\EquipmentStatusEnum;
use App\Modules\Equipment\Models\EquipmentModel;
use App\Modules\Equipment\Models\RoomEquipment;
use App\Modules\Motel\Models\Room;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Str;

class EquipmentImport implements ToCollection, WithHeadingRow
{
    protected int $successCount = 0;
    protected int $failedCount = 0;
    protected array $errors = [];
    protected array $seenSerials = [];

    public function __construct(protected ?int $organizationId = null) {}

    public function collection(Collection $rows)
    {
        foreach ($rows as $index => $row) {
            $rowNum = $index + 2;

            $modelName = trim($row['mau_thiet_bi'] ?? $row['model_name'] ?? '');
            $serialNumber = trim((string) ($row['ma_serial'] ?? $row['serial_number'] ?? ''));
            $roomNumber = trim((string) ($row['so_phong'] ?? $row['room_number'] ?? ''));
            $status = trim((string) ($row['trang_thai'] ?? $row['status'] ?? 'good'));
            $positionNote = trim($row['vi_tri_trong_phong'] ?? $row['position_note'] ?? '');
            $note = trim($row['ghi_chu'] ?? $row['note'] ?? '');

            if (empty($modelName)) {
                $this->failedCount++;
                $this->errors[] = "Dòng {$rowNum}: Tên mẫu thiết bị (model) không được để trống.";
                continue;
            }

            // Tìm hoặc tra cứu Model
            $model = EquipmentModel::query()
                ->where('name', $modelName)
                ->when($this->organizationId, fn ($query) => $query->where(function ($query) {
                    $query->where('organization_id', $this->organizationId)->orWhereNull('organization_id');
                }))
                ->orderByRaw('organization_id IS NULL')
                ->first();
            if (!$model) {
                $this->failedCount++;
                $this->errors[] = "Dòng {$rowNum}: Không tìm thấy mẫu thiết bị '{$modelName}' trong hệ thống.";
                continue;
            }

            // Tìm phòng nếu có chỉ định
            $roomId = null;
            if (!empty($roomNumber)) {
                $room = Room::query()
                    ->where('room_number', $roomNumber)
                    ->when($this->organizationId, fn ($query) => $query->where('organization_id', $this->organizationId))
                    ->first();
                if (!$room) {
                    $this->failedCount++;
                    $this->errors[] = "Dòng {$rowNum}: Không tìm thấy phòng '{$roomNumber}' trong tổ chức hiện tại.";
                    continue;
                }
                $roomId = $room->id;
            }

            $statusMap = [
                'Hoạt động tốt' => EquipmentStatusEnum::Good->value,
                'Hư hỏng' => EquipmentStatusEnum::Broken->value,
                'Đang bảo trì' => EquipmentStatusEnum::Maintenance->value,
                'Thất lạc / Báo mất' => EquipmentStatusEnum::Lost->value,
                'Trong kho' => EquipmentStatusEnum::InStock->value,
                'Đã thanh lý' => EquipmentStatusEnum::Disposed->value,
            ];
            $statusEnum = EquipmentStatusEnum::tryFrom($statusMap[$status] ?? $status);
            if (!$statusEnum) {
                $this->failedCount++;
                $this->errors[] = "Dòng {$rowNum}: Trạng thái '{$status}' không hợp lệ.";
                continue;
            }

            if (empty($serialNumber)) {
                $serialNumber = 'EQ-'.strtoupper(Str::uuid()->toString());
            }

            $serialKey = mb_strtolower($serialNumber);
            if (isset($this->seenSerials[$serialKey]) || RoomEquipment::withTrashed()
                ->where('serial_number', $serialNumber)
                ->exists()) {
                $this->failedCount++;
                $this->errors[] = "Dòng {$rowNum}: Mã Serial '{$serialNumber}' đã tồn tại; bỏ qua, không ghi đè.";
                continue;
            }
            $this->seenSerials[$serialKey] = true;

            RoomEquipment::create([
                'serial_number' => $serialNumber,
                'equipment_model_id' => $model->id,
                'room_id' => $roomId,
                'status' => $statusEnum,
                'position_note' => $positionNote ?: null,
                'note' => $note ?: null,
                'installed_at' => $roomId ? now() : null,
                'organization_id' => $this->organizationId,
            ]);

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
