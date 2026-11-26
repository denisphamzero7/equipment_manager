<?php

namespace App\Modules\Equipment\Imports;

use App\Modules\Core\Imports\Concerns\ValidatesSpreadsheetImport;
use App\Modules\Equipment\Enums\EquipmentStatusEnum;
use App\Modules\Equipment\Models\EquipmentModel;
use App\Modules\Equipment\Models\RoomEquipment;
use App\Modules\Motel\Models\Room;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class EquipmentImport implements SkipsEmptyRows, ToCollection, WithEvents, WithHeadingRow
{
    use ValidatesSpreadsheetImport;

    private const ROOM_ONLY_HEADINGS = [
        'ten_phong',
        'id_hang_phong',
        'room_type_id',
        'hang_phong',
        'room_type',
        'so_phong_ngu',
        'bedroom_count',
        'so_phong_khach',
        'living_room_count',
        'che_do_wifi',
        'wifi_mode',
        'id_phong_wifi_dung_chung',
        'shared_wifi_room_id',
        'ghi_chu_wifi_dung_chung',
        'shared_wifi_note',
        'gia',
        'price',
        'khu_vuc',
        'block',
        'tang',
        'floor',
    ];

    protected array $seenSerials = [];

    public function __construct(protected ?int $organizationId = null) {}

    protected function validateHeadings(array $headings): void
    {
        $hasModelColumn = $this->hasAnyHeading($headings, ['mau_thiet_bi', 'model_name']);
        $hasRoomOnlyColumn = $this->hasAnyHeading($headings, self::ROOM_ONLY_HEADINGS);

        if (! $hasModelColumn && $hasRoomOnlyColumn) {
            throw ValidationException::withMessages([
                'file' => ['Tệp Excel bạn chọn có cấu trúc dữ liệu Phòng, không phải mẫu nhập Thiết bị. Vui lòng chọn đúng tệp mẫu Thiết bị hoặc nhập ở trang Quản lý phòng.'],
            ]);
        }

        if (! $hasModelColumn) {
            throw ValidationException::withMessages([
                'file' => ['Tệp Excel không đúng mẫu nhập Thiết bị: thiếu cột bắt buộc "Mẫu thiết bị" (mau_thiet_bi hoặc model_name). Vui lòng tải tệp mẫu nhập Thiết bị để sử dụng.'],
            ]);
        }

        if ($hasRoomOnlyColumn) {
            throw ValidationException::withMessages([
                'file' => ['Tệp Excel có chứa cột cấu trúc Phòng không hợp lệ cho mẫu nhập Thiết bị. Vui lòng sử dụng đúng tệp mẫu Thiết bị.'],
            ]);
        }
    }

    public function collection(Collection $rows): void
    {
        $nonEmptyRows = $this->prepareNonEmptyRows($rows);
        if ($nonEmptyRows->isEmpty()) {
            return;
        }

        $modelsByName = $this->preloadModelsByName();
        $roomsByNumber = $this->preloadRoomsByNumber();
        $existingSerials = $this->preloadExistingSerials($nonEmptyRows);

        $rowsToInsert = [];
        $now = now();
        $userId = auth()->id();

        foreach ($nonEmptyRows as $index => $row) {
            $prepared = $this->buildValidatedRow(
                (int) $index + 2,
                $row,
                $modelsByName,
                $roomsByNumber,
                $existingSerials,
                $now,
                $userId
            );

            if ($prepared !== null) {
                $rowsToInsert[] = $prepared;
                $this->successCount++;
            }
        }

        $this->insertInChunks(RoomEquipment::class, $rowsToInsert);
    }

    protected function preloadModelsByName(): array
    {
        $models = EquipmentModel::query()
            ->when($this->organizationId, fn ($query) => $query->where(function ($query) {
                $query->where('organization_id', $this->organizationId)->orWhereNull('organization_id');
            }))
            ->orderByRaw('organization_id IS NULL')
            ->pluck('id', 'name');

        return $this->buildLowercaseIdMap($models);
    }

    protected function preloadRoomsByNumber(): array
    {
        $rooms = Room::query()
            ->when($this->organizationId, fn ($query) => $query->where('organization_id', $this->organizationId))
            ->pluck('id', 'room_number');

        return $this->buildLowercaseIdMap($rooms);
    }

    protected function preloadExistingSerials(Collection $rows): array
    {
        $candidateSerials = $rows
            ->map(fn ($row) => trim((string) ($row['ma_serial'] ?? $row['serial_number'] ?? '')))
            ->filter(fn (string $serial) => $serial !== '')
            ->unique()
            ->values()
            ->all();

        $existingSerials = [];
        foreach (array_chunk($candidateSerials, 500) as $serialChunk) {
            $matched = RoomEquipment::withTrashed()
                ->whereIn('serial_number', $serialChunk)
                ->pluck('serial_number');
            foreach ($matched as $serial) {
                $existingSerials[mb_strtolower(trim((string) $serial))] = true;
            }
        }

        return $existingSerials;
    }

    protected function buildValidatedRow(
        int $rowNum,
        mixed $row,
        array $modelsByName,
        array $roomsByNumber,
        array $existingSerials,
        Carbon $now,
        ?int $userId
    ): ?array {
        $modelName = trim((string) ($row['mau_thiet_bi'] ?? $row['model_name'] ?? ''));
        $serialNumber = trim((string) ($row['ma_serial'] ?? $row['serial_number'] ?? ''));
        $roomNumber = trim((string) ($row['so_phong'] ?? $row['room_number'] ?? ''));
        $rawStatus = $this->nullableValue($row['trang_thai'] ?? $row['status'] ?? null);
        $status = $rawStatus !== null ? trim((string) $rawStatus) : EquipmentStatusEnum::Good->value;
        $positionNote = trim((string) ($row['vi_tri_trong_phong'] ?? $row['position_note'] ?? ''));
        $note = trim((string) ($row['ghi_chu'] ?? $row['ghi_chu_tinh_trang'] ?? $row['note'] ?? ''));

        if ($modelName === '') {
            $this->fail($rowNum, 'Tên mẫu thiết bị (model) không được để trống.');

            return null;
        }

        $modelId = $modelsByName[mb_strtolower($modelName)] ?? null;
        if ($modelId === null) {
            $this->fail($rowNum, "Không tìm thấy mẫu thiết bị '{$modelName}' trong hệ thống.");

            return null;
        }

        $roomId = null;
        if ($roomNumber !== '') {
            $roomId = $roomsByNumber[mb_strtolower($roomNumber)] ?? null;
            if ($roomId === null) {
                $this->fail($rowNum, "Không tìm thấy phòng '{$roomNumber}' trong tổ chức hiện tại.");

                return null;
            }
        }

        $statusEnum = $this->resolveStatus($status);
        if (! $statusEnum) {
            $this->fail($rowNum, "Trạng thái '{$status}' không hợp lệ.");

            return null;
        }

        if ($serialNumber !== '' && mb_strlen($serialNumber) > 100) {
            $this->fail($rowNum, 'Mã Serial không được vượt quá 100 ký tự.');

            return null;
        }

        if (mb_strlen($positionNote) > 255) {
            $this->fail($rowNum, 'Vị trí trong phòng không được vượt quá 255 ký tự.');

            return null;
        }

        if (mb_strlen($note) > 1000) {
            $this->fail($rowNum, 'Ghi chú không được vượt quá 1000 ký tự.');

            return null;
        }

        if ($serialNumber === '') {
            $serialNumber = 'EQ-'.strtoupper(Str::uuid()->toString());
        }

        $serialKey = mb_strtolower($serialNumber);
        if (isset($this->seenSerials[$serialKey]) || isset($existingSerials[$serialKey])) {
            $this->fail($rowNum, "Mã Serial '{$serialNumber}' đã tồn tại; bỏ qua, không ghi đè.");

            return null;
        }
        $this->seenSerials[$serialKey] = true;

        return [
            'serial_number' => $serialNumber,
            'equipment_model_id' => $modelId,
            'room_id' => $roomId,
            'status' => $statusEnum->value,
            'position_note' => $positionNote !== '' ? $positionNote : null,
            'note' => $note !== '' ? $note : null,
            'installed_at' => $roomId ? $now : null,
            'organization_id' => $this->organizationId,
            'created_by' => $userId,
            'updated_by' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    protected function resolveStatus(string $status): ?EquipmentStatusEnum
    {
        $normalized = mb_strtolower(trim($status));
        $statusMap = [
            'hoạt động tốt' => EquipmentStatusEnum::Good->value,
            'tốt' => EquipmentStatusEnum::Good->value,
            'hư hỏng' => EquipmentStatusEnum::Broken->value,
            'hỏng' => EquipmentStatusEnum::Broken->value,
            'đang bảo trì' => EquipmentStatusEnum::Maintenance->value,
            'bảo trì' => EquipmentStatusEnum::Maintenance->value,
            'thất lạc / báo mất' => EquipmentStatusEnum::Lost->value,
            'thất lạc' => EquipmentStatusEnum::Lost->value,
            'báo mất' => EquipmentStatusEnum::Lost->value,
            'trong kho' => EquipmentStatusEnum::InStock->value,
            'đã thanh lý' => EquipmentStatusEnum::Disposed->value,
            'thanh lý' => EquipmentStatusEnum::Disposed->value,
        ];

        return EquipmentStatusEnum::tryFrom($statusMap[$normalized] ?? $normalized);
    }
}
