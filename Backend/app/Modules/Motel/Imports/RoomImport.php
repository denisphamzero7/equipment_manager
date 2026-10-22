<?php

namespace App\Modules\Motel\Imports;

use App\Modules\Motel\Enums\RoomStatusEnum;
use App\Modules\Motel\Models\Room;
use App\Modules\Motel\Models\RoomType;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class RoomImport implements ToCollection, WithHeadingRow
{
    protected int $successCount = 0;
    protected int $failedCount = 0;
    protected array $errors = [];
    protected array $seenRoomNumbers = [];

    public function __construct(protected int $organizationId) {}

    public function collection(Collection $rows): void
    {
        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;
            $roomNumber = trim((string) ($row['so_phong'] ?? $row['room_number'] ?? ''));
            $status = trim((string) ($row['trang_thai'] ?? $row['status'] ?? RoomStatusEnum::Ready->value));
            $data = [
                'room_number' => $roomNumber,
                'name' => trim((string) ($row['ten_phong'] ?? $row['name'] ?? '')) ?: null,
                'block' => trim((string) ($row['khu_vuc'] ?? $row['block'] ?? '')) ?: null,
                'floor' => $this->nullableValue($row['tang'] ?? $row['floor'] ?? null),
                'room_type_id' => $this->nullableValue($row['id_hang_phong'] ?? $row['room_type_id'] ?? null),
                'status' => $status,
                'price' => $this->nullableValue($row['gia'] ?? $row['price'] ?? null),
                'bedroom_count' => $this->nullableValue($row['so_phong_ngu'] ?? $row['bedroom_count'] ?? 1),
                'living_room_count' => $this->nullableValue($row['so_phong_khach'] ?? $row['living_room_count'] ?? 0),
                'wifi_mode' => trim((string) ($row['che_do_wifi'] ?? $row['wifi_mode'] ?? 'dedicated')),
                'shared_wifi_room_id' => $this->nullableValue($row['id_phong_wifi_dung_chung'] ?? $row['shared_wifi_room_id'] ?? null),
                'shared_wifi_note' => trim((string) ($row['ghi_chu_wifi_dung_chung'] ?? $row['shared_wifi_note'] ?? '')) ?: null,
                'description' => trim((string) ($row['mo_ta'] ?? $row['description'] ?? '')) ?: null,
                'organization_id' => $this->organizationId,
            ];

            $validation = Validator::make($data, [
                'room_number' => 'required|string|max:50',
                'name' => 'nullable|string|max:255',
                'block' => 'nullable|string|max:100',
                'floor' => 'nullable|integer',
                'room_type_id' => 'nullable|integer',
                'status' => ['required', RoomStatusEnum::rule()],
                'price' => 'nullable|numeric|min:0',
                'bedroom_count' => 'nullable|integer|min:0|max:20',
                'living_room_count' => 'nullable|integer|min:0|max:20',
                'wifi_mode' => 'required|in:dedicated,shared,none',
                'shared_wifi_room_id' => 'nullable|integer',
                'shared_wifi_note' => 'nullable|string|max:255',
                'description' => 'nullable|string',
            ]);

            if ($validation->fails()) {
                $this->fail($rowNumber, implode(' ', $validation->errors()->all()));
                continue;
            }

            $roomKey = mb_strtolower($roomNumber);
            if (isset($this->seenRoomNumbers[$roomKey]) || Room::query()
                ->where('organization_id', $this->organizationId)
                ->where('room_number', $roomNumber)
                ->exists()) {
                $this->fail($rowNumber, "Số phòng '{$roomNumber}' đã tồn tại; bỏ qua, không ghi đè.");
                continue;
            }

            if ($data['room_type_id'] !== null && !RoomType::query()
                ->whereKey($data['room_type_id'])
                ->where(function ($query) {
                    $query->where('organization_id', $this->organizationId)
                        ->orWhereNull('organization_id');
                })
                ->exists()) {
                $this->fail($rowNumber, 'ID hạng phòng không tồn tại hoặc không thuộc tổ chức hiện tại.');
                continue;
            }

            if ($data['shared_wifi_room_id'] !== null && !Room::query()
                ->whereKey($data['shared_wifi_room_id'])
                ->where('organization_id', $this->organizationId)
                ->exists()) {
                $this->fail($rowNumber, 'ID phòng WiFi dùng chung không tồn tại trong tổ chức hiện tại.');
                continue;
            }

            $this->seenRoomNumbers[$roomKey] = true;
            Room::create($data);
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

    protected function nullableValue(mixed $value): mixed
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        return $value;
    }

    protected function fail(int $rowNumber, string $message): void
    {
        $this->failedCount++;
        $this->errors[] = "Dòng {$rowNumber}: {$message}";
    }
}
