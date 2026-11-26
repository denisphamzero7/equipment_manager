<?php

namespace App\Modules\Motel\Imports;

use App\Modules\Core\Imports\Concerns\ValidatesSpreadsheetImport;
use App\Modules\Motel\Enums\RoomStatusEnum;
use App\Modules\Motel\Models\Room;
use App\Modules\Motel\Models\RoomType;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class RoomImport implements SkipsEmptyRows, ToCollection, WithEvents, WithHeadingRow
{
    use ValidatesSpreadsheetImport;

    private const EQUIPMENT_ONLY_HEADINGS = [
        'mau_thiet_bi',
        'model_name',
        'ma_model',
        'model_code',
        'ma_serial',
        'serial_number',
        'vi_tri_trong_phong',
        'position_note',
        'danh_muc',
        'category_name',
        'ngay_lap_dat',
        'installed_at',
        'ghi_chu_tinh_trang',
    ];

    protected array $seenRoomNumbers = [];

    public function __construct(protected int $organizationId) {}

    protected function validateHeadings(array $headings): void
    {
        if ($this->hasAnyHeading($headings, self::EQUIPMENT_ONLY_HEADINGS)) {
            throw ValidationException::withMessages([
                'file' => ['Tệp Excel bạn chọn có cấu trúc dữ liệu Thiết bị, không phải mẫu nhập Phòng. Vui lòng chọn đúng tệp mẫu Phòng hoặc nhập ở trang Danh sách thiết bị.'],
            ]);
        }

        if (! $this->hasAnyHeading($headings, ['so_phong', 'room_number'])) {
            throw ValidationException::withMessages([
                'file' => ['Tệp Excel không đúng mẫu nhập Phòng: thiếu cột bắt buộc "Số phòng" (so_phong hoặc room_number). Vui lòng tải tệp mẫu nhập Phòng để sử dụng.'],
            ]);
        }
    }

    public function collection(Collection $rows): void
    {
        $nonEmptyRows = $this->prepareNonEmptyRows($rows);
        if ($nonEmptyRows->isEmpty()) {
            return;
        }

        $existingRooms = Room::query()
            ->where('organization_id', $this->organizationId)
            ->pluck('id', 'room_number');

        $existingRoomNumbers = [];
        $existingRoomIds = [];
        foreach ($existingRooms as $number => $id) {
            $existingRoomNumbers[mb_strtolower(trim((string) $number))] = true;
            $existingRoomIds[(int) $id] = true;
        }

        $validRoomTypeIds = RoomType::query()
            ->where(function ($query) {
                $query->where('organization_id', $this->organizationId)
                    ->orWhereNull('organization_id');
            })
            ->pluck('id')
            ->flip()
            ->all();

        $rowsToInsert = [];
        $now = now();
        $userId = auth()->id();

        foreach ($nonEmptyRows as $index => $row) {
            $rowNumber = (int) $index + 2;
            $roomNumber = trim((string) ($row['so_phong'] ?? $row['room_number'] ?? ''));

            $rawStatus = $this->nullableValue($row['trang_thai'] ?? $row['status'] ?? null);
            $status = $rawStatus !== null ? $this->normalizeStatus((string) $rawStatus) : RoomStatusEnum::Ready->value;

            $rawWifiMode = $this->nullableValue($row['che_do_wifi'] ?? $row['wifi_mode'] ?? null);
            $wifiMode = $rawWifiMode !== null ? mb_strtolower(trim((string) $rawWifiMode)) : 'dedicated';

            $bedroomCount = $this->nullableValue($row['so_phong_ngu'] ?? $row['bedroom_count'] ?? null);
            $livingRoomCount = $this->nullableValue($row['so_phong_khach'] ?? $row['living_room_count'] ?? null);

            $data = [
                'room_number' => $roomNumber,
                'name' => trim((string) ($row['ten_phong'] ?? $row['name'] ?? '')) ?: null,
                'block' => trim((string) ($row['khu_vuc'] ?? $row['block'] ?? '')) ?: null,
                'floor' => $this->nullableValue($row['tang'] ?? $row['floor'] ?? null),
                'room_type_id' => $this->nullableValue($row['id_hang_phong'] ?? $row['room_type_id'] ?? null),
                'status' => $status,
                'price' => $this->nullableValue($row['gia'] ?? $row['price'] ?? null),
                'bedroom_count' => $bedroomCount !== null ? $bedroomCount : 1,
                'living_room_count' => $livingRoomCount !== null ? $livingRoomCount : 0,
                'wifi_mode' => $wifiMode,
                'shared_wifi_room_id' => $this->nullableValue($row['id_phong_wifi_dung_chung'] ?? $row['shared_wifi_room_id'] ?? null),
                'shared_wifi_note' => trim((string) ($row['ghi_chu_wifi_dung_chung'] ?? $row['shared_wifi_note'] ?? '')) ?: null,
                'description' => trim((string) ($row['mo_ta'] ?? $row['description'] ?? '')) ?: null,
                'organization_id' => $this->organizationId,
            ];

            $validation = Validator::make(
                $data,
                [
                    'room_number' => 'required|string|max:50',
                    'name' => 'nullable|string|max:255',
                    'block' => 'nullable|string|max:50',
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
                ],
                $this->validationMessages(),
                $this->validationAttributes()
            );

            if ($validation->fails()) {
                $this->fail($rowNumber, implode(' ', $validation->errors()->all()));
                continue;
            }

            $roomKey = mb_strtolower($roomNumber);
            if (isset($this->seenRoomNumbers[$roomKey]) || isset($existingRoomNumbers[$roomKey])) {
                $this->fail($rowNumber, "Số phòng '{$roomNumber}' đã tồn tại; bỏ qua, không ghi đè.");
                continue;
            }

            if ($data['room_type_id'] !== null && ! isset($validRoomTypeIds[(int) $data['room_type_id']])) {
                $this->fail($rowNumber, 'ID hạng phòng không tồn tại hoặc không thuộc tổ chức hiện tại.');
                continue;
            }

            if ($data['shared_wifi_room_id'] !== null && ! isset($existingRoomIds[(int) $data['shared_wifi_room_id']])) {
                $this->fail($rowNumber, 'ID phòng WiFi dùng chung không tồn tại trong tổ chức hiện tại.');
                continue;
            }

            $this->seenRoomNumbers[$roomKey] = true;

            $rowsToInsert[] = [
                'room_number' => $roomNumber,
                'name' => $data['name'] ?: ('Phòng '.$roomNumber),
                'block' => $data['block'],
                'floor' => $data['floor'] !== null ? (int) $data['floor'] : null,
                'room_type_id' => $data['room_type_id'] !== null ? (int) $data['room_type_id'] : null,
                'status' => $status,
                'price' => $data['price'],
                'bedroom_count' => (int) $data['bedroom_count'],
                'living_room_count' => (int) $data['living_room_count'],
                'wifi_mode' => $wifiMode,
                'wifi_router_count' => $wifiMode === 'dedicated' ? 1 : 0,
                'shared_wifi_room_id' => $data['shared_wifi_room_id'] !== null ? (int) $data['shared_wifi_room_id'] : null,
                'shared_wifi_note' => $data['shared_wifi_note'],
                'description' => $data['description'],
                'organization_id' => $this->organizationId,
                'created_by' => $userId,
                'updated_by' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $this->successCount++;
        }

        if (! empty($rowsToInsert)) {
            DB::transaction(function () use ($rowsToInsert): void {
                foreach (array_chunk($rowsToInsert, 200) as $chunk) {
                    Room::insert($chunk);
                }
            });
        }
    }

    protected function normalizeStatus(string $status): string
    {
        $normalized = mb_strtolower(trim($status));
        $statusMap = [
            'sẵn sàng đón khách' => RoomStatusEnum::Ready->value,
            'sẵn sàng' => RoomStatusEnum::Ready->value,
            'đang có khách' => RoomStatusEnum::Occupied->value,
            'có khách' => RoomStatusEnum::Occupied->value,
            'chờ dọn dẹp' => RoomStatusEnum::NotReady->value,
            'phòng bẩn' => RoomStatusEnum::NotReady->value,
            'chưa sẵn sàng' => RoomStatusEnum::NotReady->value,
            'đang bảo trì' => RoomStatusEnum::Maintenance->value,
            'bảo trì' => RoomStatusEnum::Maintenance->value,
        ];

        return $statusMap[$normalized] ?? $normalized;
    }

    protected function validationMessages(): array
    {
        return [
            'required' => ':attribute không được để trống.',
            'string' => ':attribute phải là chuỗi ký tự.',
            'integer' => ':attribute phải là số nguyên.',
            'numeric' => ':attribute phải là số hợp lệ.',
            'min' => ':attribute không được nhỏ hơn :min.',
            'max' => ':attribute không được vượt quá :max.',
            'status.in' => 'Trạng thái phòng không hợp lệ (chỉ chấp nhận: ready, occupied, not_ready, maintenance).',
            'wifi_mode.in' => 'Chế độ WiFi không hợp lệ (chỉ chấp nhận: dedicated, shared, none).',
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'room_number' => 'Số phòng',
            'name' => 'Tên phòng',
            'block' => 'Khu vực',
            'floor' => 'Tầng',
            'room_type_id' => 'ID hạng phòng',
            'status' => 'Trạng thái',
            'price' => 'Giá phòng',
            'bedroom_count' => 'Số phòng ngủ',
            'living_room_count' => 'Số phòng khách',
            'wifi_mode' => 'Chế độ WiFi',
            'shared_wifi_room_id' => 'ID phòng WiFi dùng chung',
            'shared_wifi_note' => 'Ghi chú WiFi dùng chung',
            'description' => 'Mô tả',
        ];
    }
}
