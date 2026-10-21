<?php

namespace App\Modules\Motel\Services;

use App\Modules\Equipment\Enums\EquipmentStatusEnum;
use App\Modules\Equipment\Models\RoomEquipment;
use App\Modules\Motel\Enums\RoomStatusEnum;
use App\Modules\Motel\Events\RoomChanged;
use App\Modules\Motel\Models\Room as RoomModel;
use App\Modules\Motel\Models\RoomType as RoomTypeModel;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class RoomService
{
    protected function getActiveOrgId(?int $orgId = null): int
    {
        return (int) ($orgId ?: (function_exists('getPermissionsTeamId') ? getPermissionsTeamId() : 22));
    }

    public function invalidateMapCache(?int $orgId = null): void
    {
        $orgId = $this->getActiveOrgId($orgId);
        cache()->forget('hotel_blocks_' . $orgId);
        cache()->forget('hotel_blocks_all');
        cache()->forget('hotel_floors_' . $orgId . '_all');
        cache()->forget('hotel_floors_all_all');
        cache()->forget('equipment_room_options_' . $orgId);
        cache()->forget('equipment_room_options_all');
    }

    public function stats(array $filters = []): array
    {
        $orgId = $this->getActiveOrgId($filters['organization_id'] ?? null);
        $base = RoomModel::where('organization_id', $orgId);

        return [
            'total' => (clone $base)->count(),
            'ready' => (clone $base)->where('status', RoomStatusEnum::Ready->value)->count(),
            'occupied' => (clone $base)->where('status', RoomStatusEnum::Occupied->value)->count(),
            'not_ready' => (clone $base)->where('status', RoomStatusEnum::NotReady->value)->count(),
            'maintenance' => (clone $base)->where('status', RoomStatusEnum::Maintenance->value)->count(),
        ];
    }

    public function index(array $filters = [], int $limit = 15): LengthAwarePaginator
    {
        $orgId = $this->getActiveOrgId($filters['organization_id'] ?? null);
        $filters['organization_id'] = $orgId;

        $sortBy = $filters['sort_by'] ?? 'room_number';
        $sortOrder = strtolower($filters['sort_order'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        return RoomModel::with(['roomType', 'sharedWifiRoom'])
            ->withCount('equipments')
            ->filter($filters)
            ->orderBy($sortBy, $sortOrder)
            ->paginate($limit);
    }

    public function getRoomTypes(?int $orgId = null)
    {
        $activeOrg = $this->getActiveOrgId($orgId);
        return RoomTypeModel::where('organization_id', $activeOrg)
            ->orWhereNull('organization_id')
            ->orderBy('name', 'asc')
            ->get();
    }

    public function getRoomById(int $id): RoomModel
    {
        return RoomModel::with(['roomType', 'sharedWifiRoom', 'equipments.model.category'])->findOrFail($id);
    }

    public function createRoom(array $data): RoomModel
    {
        if (empty($data['organization_id'])) {
            $data['organization_id'] = $this->getActiveOrgId();
        }
        if (empty($data['name']) && !empty($data['room_number'])) {
            $data['name'] = 'Phòng ' . $data['room_number'];
        }
        if (empty($data['status'])) {
            $data['status'] = RoomStatusEnum::Ready->value;
        }

        $room = RoomModel::create($data);
        $room->load(['roomType'])->loadCount('equipments');

        $this->invalidateMapCache($room->organization_id);

        // Phát Broadcast Real-time
        event(new RoomChanged('created', $room, "Phòng {$room->room_number} ({$room->block} - Tầng {$room->floor}) vừa được tạo mới."));

        return $room;
    }

    public function updateRoom(int $id, array $data): RoomModel
    {
        $room = RoomModel::findOrFail($id);
        $room->update($data);
        $room->load(['roomType'])->loadCount('equipments');

        $this->invalidateMapCache($room->organization_id);

        // Phát Broadcast Real-time
        event(new RoomChanged('updated', $room, "Thông tin Phòng {$room->room_number} vừa được cập nhật."));

        return $room;
    }

    public function deleteRoom(int $id, bool $retractEquipments = true): bool
    {
        return DB::transaction(function () use ($id, $retractEquipments) {
            $room = RoomModel::findOrFail($id);
            $roomNumber = $room->room_number;
            $orgId = $room->organization_id;

            if ($retractEquipments) {
                // Tự động thu hồi toàn bộ thiết bị trong phòng về kho để bảo toàn dữ liệu
                RoomEquipment::where('room_id', $id)->update([
                    'room_id' => null,
                    'status' => EquipmentStatusEnum::InStock->value,
                    'position_note' => null,
                    'note' => "Thu hồi về kho do xóa phòng {$roomNumber} vào " . now()->format('d/m/Y H:i'),
                ]);
            }

            $deleted = (bool) $room->delete();

            $this->invalidateMapCache($orgId);

            // Phát Broadcast Real-time
            event(new RoomChanged('deleted', ['id' => $id, 'room_number' => $roomNumber], "Phòng {$roomNumber} đã được xóa khỏi hệ thống.", $orgId));

            return $deleted;
        });
    }

    public function bulkDestroy(array $ids, bool $retractEquipments = true): int
    {
        return DB::transaction(function () use ($ids, $retractEquipments) {
            if ($retractEquipments) {
                RoomEquipment::whereIn('room_id', $ids)->update([
                    'room_id' => null,
                    'status' => EquipmentStatusEnum::InStock->value,
                    'position_note' => null,
                ]);
            }

            $count = RoomModel::whereIn('id', $ids)->delete();

            $this->invalidateMapCache();

            event(new RoomChanged('bulk_deleted', ['ids' => $ids], "Đã xóa {$count} phòng khỏi hệ thống."));

            return $count;
        });
    }

    public function bulkUpdateStatus(array $ids, string $status): int
    {
        $count = RoomModel::whereIn('id', $ids)->update(['status' => $status]);
        $this->invalidateMapCache();
        event(new RoomChanged('bulk_status_updated', ['ids' => $ids, 'status' => $status], "Đã cập nhật trạng thái {$count} phòng thành công."));
        return $count;
    }

    /**
     * Sinh phòng nhanh hàng loạt (Batch Generator).
     */
    public function batchGenerate(array $data): array
    {
        $orgId = $this->getActiveOrgId($data['organization_id'] ?? null);
        $block = $data['block'] ?? 'Block A';
        $floor = (int) ($data['floor'] ?? 1);
        $startNum = (int) $data['start_number'];
        $endNum = (int) $data['end_number'];
        $prefix = $data['prefix'] ?? 'Phòng ';
        $roomTypeId = $data['room_type_id'] ?? null;
        $price = $data['price'] ?? 0;
        $status = $data['status'] ?? RoomStatusEnum::Ready->value;
        $bedroomCount = isset($data['bedroom_count']) ? (int) $data['bedroom_count'] : 1;
        $livingRoomCount = isset($data['living_room_count']) ? (int) $data['living_room_count'] : 0;
        $wifiMode = $data['wifi_mode'] ?? 'dedicated';

        if ($endNum < $startNum) {
            throw new \InvalidArgumentException('Số phòng kết thúc phải lớn hơn hoặc bằng số bắt đầu.');
        }

        $created = [];
        $colIndex = 1;

        DB::transaction(function () use ($orgId, $block, $floor, $startNum, $endNum, $prefix, $roomTypeId, $price, $status, $bedroomCount, $livingRoomCount, $wifiMode, &$created, &$colIndex) {
            for ($num = $startNum; $num <= $endNum; $num++) {
                // Kiểm tra nếu số phòng đã tồn tại trong tổ chức thì bỏ qua
                $exists = RoomModel::where('organization_id', $orgId)
                    ->where('room_number', $num)
                    ->exists();

                if ($exists) {
                    continue;
                }

                $room = RoomModel::create([
                    'name' => $prefix . $num,
                    'room_number' => $num,
                    'block' => $block,
                    'floor' => $floor,
                    'grid_row' => $floor,
                    'grid_col' => $colIndex++,
                    'room_type_id' => $roomTypeId,
                    'price' => $price,
                    'status' => $status,
                    'bedroom_count' => $bedroomCount,
                    'living_room_count' => $livingRoomCount,
                    'wifi_mode' => $wifiMode,
                    'organization_id' => $orgId,
                ]);

                $created[] = $room;
            }
        });

        $count = count($created);
        $this->invalidateMapCache($orgId);
        event(new RoomChanged('batch_generated', ['count' => $count, 'block' => $block, 'floor' => $floor], "Đã tạo nhanh {$count} phòng mới cho {$block} - Tầng {$floor}.", $orgId));

        return [
            'created_count' => $count,
            'rooms' => $created,
        ];
    }

    /**
     * Đổi tên Block hàng loạt
     */
    public function renameBlock(string $oldBlock, string $newBlock, ?int $orgId = null): int
    {
        $activeOrg = $this->getActiveOrgId($orgId);
        $count = RoomModel::where('organization_id', $activeOrg)
            ->where('block', $oldBlock)
            ->update(['block' => $newBlock]);

        $this->invalidateMapCache($activeOrg);

        event(new RoomChanged('block_renamed', ['old_block' => $oldBlock, 'new_block' => $newBlock], "Đã đổi tên khu vực từ '{$oldBlock}' thành '{$newBlock}' ({$count} phòng).", $activeOrg));

        return $count;
    }

    /**
     * Xóa toàn bộ phòng của một Block
     */
    public function deleteBlock(string $block, ?int $orgId = null): int
    {
        $activeOrg = $this->getActiveOrgId($orgId);

        return DB::transaction(function () use ($block, $activeOrg) {
            $roomIds = RoomModel::where('organization_id', $activeOrg)
                ->where('block', $block)
                ->pluck('id')
                ->toArray();

            if (!empty($roomIds)) {
                // Thu hồi thiết bị về kho
                RoomEquipment::whereIn('room_id', $roomIds)->update([
                    'room_id' => null,
                    'status' => EquipmentStatusEnum::InStock->value,
                    'position_note' => null,
                ]);

                $count = RoomModel::whereIn('id', $roomIds)->delete();
            } else {
                $count = 0;
            }

            $this->invalidateMapCache($activeOrg);

            event(new RoomChanged('block_deleted', ['block' => $block], "Đã xóa toàn bộ các phòng thuộc khu '{$block}'.", $activeOrg));

            return $count;
        });
    }
}
