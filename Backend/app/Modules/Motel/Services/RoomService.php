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

/**
 * [1. MỤC ĐÍCH]
 * Tầng dịch vụ quản lý thực thể Phòng (Rooms) trong khách sạn:
 * Quản lý danh sách phòng, phân cấp Block - Tầng, tạo phòng nhanh hàng loạt, đổi tên/xóa khu vực và thu hồi thiết bị an toàn.
 */
class RoomService
{
    /**
     * [1. MỤC ĐÍCH]
     * Xác định Organization ID (ID cơ sở) hiện tại.
     * @param int|null $orgId
     * @return int
     */
    protected function getActiveOrgId(?int $orgId = null): int
    {
        return (int) ($orgId ?: (function_exists('getPermissionsTeamId') ? getPermissionsTeamId() : (\App\Modules\Core\Models\Organization::value('id') ?? 1)));
    }

    /**
     * [1. MỤC ĐÍCH]
     * Phát broadcast sự kiện Reverb an toàn cho các tác vụ thay đổi phòng, không làm gián đoạn request nếu websocket offline.
     *
     * [2. LUỒNG XỬ LÝ (FLOW)]
     * 1. Gọi `event($event)` để dispatch broadcast event.
     * 2. Bắt ngoại lệ Throwable và ghi cảnh báo vào Log nếu kết nối Reverb lỗi.
     *
     * [3. NGUỒN GỌI (CALLERS)]
     * - Các method trong RoomService: createRoom, updateRoom, deleteRoom, bulkDestroy, batchGenerate, renameBlock, deleteBlock
     *
     * [4. THAM SỐ (PARAMETERS)]
     * @param object $event Đối tượng Event (RoomChanged)
     * @return void
     */
    protected function safeBroadcast(object $event): void
    {
        try {
            event($event);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Room broadcast failed safely: ' . $e->getMessage());
        }
    }

    /**
     * [1. MỤC ĐÍCH]
     * Xóa sạch các tầng cache dữ liệu liên quan đến cấu trúc phòng, block, tầng và Map View khi có thay đổi.
     *
     * [2. LUỒNG XỬ LÝ (FLOW)]
     * 1. Xác định `$orgId` hoạt động.
     * 2. Xóa cache danh sách block (`hotel_blocks_{orgId}`), tầng (`hotel_floors_{orgId}_all`), options phòng (`equipment_room_options_{orgId}`) và hạng phòng (`motel_room_types_{orgId}`).
     * 3. Tăng phiên bản `equipment_map_view_version_{orgId}` lên 1 để Frontend tự động refresh sơ đồ Map.
     *
     * [3. NGUỒN GỌI (CALLERS)]
     * - Mọi tác vụ ghi dữ liệu trong RoomService
     *
     * [4. THAM SỐ (PARAMETERS)]
     * @param int|null $orgId
     * @return void
     */
    public function invalidateMapCache(?int $orgId = null): void
    {
        $orgId = $this->getActiveOrgId($orgId);
        cache()->forget('hotel_blocks_' . $orgId);
        cache()->forget('hotel_blocks_all');
        cache()->forget('hotel_floors_' . $orgId . '_all');
        cache()->forget('hotel_floors_all_all');
        cache()->forget('equipment_room_options_' . $orgId);
        cache()->forget('equipment_room_options_all');
        cache()->forget('motel_room_types_' . $orgId);
        cache()->forget('motel_room_types_all');

        $v = (int) cache()->get('equipment_map_view_version_' . $orgId, 1);
        cache()->put('equipment_map_view_version_' . $orgId, $v + 1, 86400 * 30);
        $vAll = (int) cache()->get('equipment_map_view_version_all', 1);
        cache()->put('equipment_map_view_version_all', $vAll + 1, 86400 * 30);
    }

    /**
     * [1. MỤC ĐÍCH]
     * Thống kê số lượng phòng theo trạng thái (sẵn sàng, có khách, chưa dọn, đang sửa chữa).
     *
     * [2. LUỒNG XỬ LÝ (FLOW)]
     * 1. Xác định `organization_id`.
     * 2. Đếm số lượng phòng gom nhóm theo `status`.
     * 3. Trả về mảng chứa: total, ready, occupied, not_ready, maintenance.
     *
     * [3. NGUỒN GỌI (CALLERS)]
     * - Route: GET /api/v1/motel/rooms/stats
     * - View: `Frontend/src/modules/room/views/room-list/index.vue`
     *
     * [4. THAM SỐ (PARAMETERS)]
     * @param array $filters Bộ lọc theo tổ chức
     * @return array Mảng thống kê trạng thái phòng
     */
    public function stats(array $filters = []): array
    {
        $orgId = $this->getActiveOrgId($filters['organization_id'] ?? null);
        $counts = RoomModel::where('organization_id', $orgId)
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->all();

        return [
            'total' => array_sum($counts),
            'ready' => (int) ($counts[RoomStatusEnum::Ready->value] ?? 0),
            'occupied' => (int) ($counts[RoomStatusEnum::Occupied->value] ?? 0),
            'not_ready' => (int) ($counts[RoomStatusEnum::NotReady->value] ?? 0),
            'maintenance' => (int) ($counts[RoomStatusEnum::Maintenance->value] ?? 0),
        ];
    }

    /**
     * [1. MỤC ĐÍCH]
     * Lấy danh sách phòng có phân trang, bộ lọc (block, floor, status, room_type_id) và tóm tắt thiết bị trong phòng.
     *
     * [2. LUỒNG XỬ LÝ (FLOW)]
     * 1. Áp dụng scope filter trên `RoomModel`, eager load `roomType`, `sharedWifiRoom` và đếm tổng thiết bị (`withCount('equipments')`).
     * 2. Phân trang theo `limit` (mặc định 15 phòng/trang).
     * 3. Tối ưu N+1: Truy vấn tóm tắt tên và số lượng thiết bị (`equipmentSummary`) cho toàn bộ các phòng trong trang chỉ bằng 1 câu SQL `whereIn('room_id', $roomIds)`.
     * 4. Gắn mảng `equipment_summary` vào từng thực thể phòng.
     * 5. Trả về LengthAwarePaginator.
     *
     * [3. NGUỒN GỌI (CALLERS)]
     * - Route: GET /api/v1/motel/rooms
     * - Controller: App\Modules\Motel\Controllers\RoomController::index()
     * - Frontend Service: `roomService.getRooms()`
     *
     * [4. THAM SỐ (PARAMETERS)]
     * @param array $filters Tham số lọc (block, floor, status, search, room_type_id...)
     * @param int $limit Số lượng bản ghi trên một trang (mặc định 15)
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */
    public function index(array $filters = [], int $limit = 15): LengthAwarePaginator
    {
        $orgId = $this->getActiveOrgId($filters['organization_id'] ?? null);
        $filters['organization_id'] = $orgId;

        $sortBy = $filters['sort_by'] ?? 'room_number';
        $sortOrder = strtolower($filters['sort_order'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        $rooms = RoomModel::with(['roomType', 'sharedWifiRoom'])
            ->withCount('equipments')
            ->filter($filters)
            ->orderBy($sortBy, $sortOrder)
            ->paginate($limit);

        $roomIds = $rooms->getCollection()->pluck('id');
        $equipmentSummary = $roomIds->isEmpty()
            ? collect()
            : DB::table('room_equipments')
                ->join('equipment_models', 'equipment_models.id', '=', 'room_equipments.equipment_model_id')
                ->whereIn('room_equipments.room_id', $roomIds)
                ->whereNull('room_equipments.deleted_at')
                ->select(
                    'room_equipments.room_id',
                    'equipment_models.name as model_name',
                    DB::raw('COUNT(*) as quantity')
                )
                ->groupBy('room_equipments.room_id', 'equipment_models.name')
                ->orderBy('equipment_models.name')
                ->get()
                ->groupBy('room_id');

        $rooms->getCollection()->each(function (RoomModel $room) use ($equipmentSummary) {
            $room->setAttribute(
                'equipment_summary',
                $equipmentSummary->get($room->id, collect())
                    ->map(fn ($item) => [
                        'name' => $item->model_name,
                        'quantity' => (int) $item->quantity,
                    ])
                    ->values()
                    ->all()
            );
        });

        return $rooms;
    }

    /**
     * [1. MỤC ĐÍCH]
     * Lấy danh sách các Hạng phòng (Room Types) của cơ sở hiện tại (Standard, VIP, Deluxe...).
     * @param int|null $orgId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getRoomTypes(?int $orgId = null)
    {
        $activeOrg = $this->getActiveOrgId($orgId);
        return cache()->remember("motel_room_types_{$activeOrg}", 600, function () use ($activeOrg) {
            return RoomTypeModel::where('organization_id', $activeOrg)
                ->orWhereNull('organization_id')
                ->orderBy('name', 'asc')
                ->get();
        });
    }

    /**
     * [1. MỤC ĐÍCH]
     * Lấy thông tin chi tiết một phòng theo ID kèm danh sách thiết bị.
     * @param int $id ID phòng
     * @return \App\Modules\Motel\Models\Room
     */
    public function getRoomById(int $id): RoomModel
    {
        return RoomModel::with(['roomType', 'sharedWifiRoom', 'equipments.model.category'])->findOrFail($id);
    }

    /**
     * [1. MỤC ĐÍCH]
     * Tạo mới một phòng đơn lẻ và phát broadcast đồng bộ.
     * @param array $data Dữ liệu phòng (room_number, block, floor, room_type_id, wifi_mode...)
     * @return \App\Modules\Motel\Models\Room
     */
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
        $this->safeBroadcast(new RoomChanged('created', $room, "Phòng {$room->room_number} ({$room->block} - Tầng {$room->floor}) vừa được tạo mới."));

        return $room;
    }

    /**
     * [1. MỤC ĐÍCH]
     * Cập nhật thông tin phòng (số phòng, tầng, block, cơ cấu phòng ngủ/khách, chế độ wifi).
     * @param int $id ID phòng
     * @param array $data
     * @return \App\Modules\Motel\Models\Room
     */
    public function updateRoom(int $id, array $data): RoomModel
    {
        $room = RoomModel::findOrFail($id);
        $room->update($data);
        $room->load(['roomType'])->loadCount('equipments');

        $this->invalidateMapCache($room->organization_id);

        // Phát Broadcast Real-time
        $this->safeBroadcast(new RoomChanged('updated', $room, "Thông tin Phòng {$room->room_number} vừa được cập nhật."));

        return $room;
    }

    /**
     * [1. MỤC ĐÍCH]
     * Xóa một phòng khỏi hệ thống, hỗ trợ tự động thu hồi toàn bộ thiết bị về kho dự phòng chung để bảo toàn tài sản.
     *
     * [2. LUỒNG XỬ LÝ (FLOW)]
     * 1. Bắt đầu Database Transaction.
     * 2. Nếu `$retractEquipments = true`: Thu hồi toàn bộ thiết bị đang gắn trong phòng (`room_id = null`, `status = in_stock`).
     * 3. Thực hiện xóa bản ghi phòng.
     * 4. Xóa cache và phát broadcast sự kiện `RoomChanged('deleted')`.
     *
     * [4. THAM SỐ (PARAMETERS)]
     * @param int $id ID phòng
     * @param bool $retractEquipments Có tự động thu hồi thiết bị về kho hay không (mặc định true)
     * @return bool
     */
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
            $this->safeBroadcast(new RoomChanged('deleted', ['id' => $id, 'room_number' => $roomNumber], "Phòng {$roomNumber} đã được xóa khỏi hệ thống.", $orgId));

            return $deleted;
        });
    }

    /**
     * [1. MỤC ĐÍCH]
     * Xóa đồng thời nhiều phòng và tự động thu hồi tài sản về kho.
     * @param array<int> $ids Mảng ID các phòng cần xóa
     * @param bool $retractEquipments
     * @return int Số phòng đã xóa
     */
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

            $this->safeBroadcast(new RoomChanged('bulk_deleted', ['ids' => $ids], "Đã xóa {$count} phòng khỏi hệ thống."));

            return $count;
        });
    }

    /**
     * [1. MỤC ĐÍCH]
     * Cập nhật trạng thái kinh doanh hàng loạt cho nhiều phòng.
     * @param array<int> $ids
     * @param string $status ('ready', 'occupied', 'not_ready', 'maintenance')
     * @return int
     */
    public function bulkUpdateStatus(array $ids, string $status): int
    {
        $count = RoomModel::whereIn('id', $ids)->update(['status' => $status]);
        $this->invalidateMapCache();
        $this->safeBroadcast(new RoomChanged('bulk_status_updated', ['ids' => $ids, 'status' => $status], "Đã cập nhật trạng thái {$count} phòng thành công."));
        return $count;
    }

    /**
     * [1. MỤC ĐÍCH]
     * Sinh phòng nhanh hàng loạt theo dải số phòng (Batch Generator) cho một tầng và block cụ thể.
     *
     * [2. LUỒNG XỬ LÝ (FLOW)]
     * 1. Validate số phòng kết thúc `$endNum >= $startNum`.
     * 2. Lặp từ `$startNum` đến `$endNum`:
     *    - Kiểm tra số phòng đã tồn tại trong tổ chức chưa, nếu có thì bỏ qua để tránh trùng.
     *    - Tạo bản ghi phòng với tọa độ lưới (`grid_row = floor`, `grid_col = colIndex++`).
     * 3. Xóa cache sơ đồ Map View và phát broadcast `batch_generated`.
     * 4. Trả về số lượng phòng đã tạo thành công và danh sách phòng.
     *
     * [4. THAM SỐ (PARAMETERS)]
     * @param array $data Chứa block, floor, start_number, end_number, room_type_id, wifi_mode...
     * @return array{created_count: int, rooms: array}
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
        $wifiRouterCount = isset($data['wifi_router_count']) ? (int) $data['wifi_router_count'] : 1;

        if ($endNum < $startNum) {
            throw new \InvalidArgumentException('Số phòng kết thúc phải lớn hơn hoặc bằng số bắt đầu.');
        }

        $created = [];
        $colIndex = 1;

        DB::transaction(function () use ($orgId, $block, $floor, $startNum, $endNum, $prefix, $roomTypeId, $price, $status, $bedroomCount, $livingRoomCount, $wifiMode, $wifiRouterCount, &$created, &$colIndex) {
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
                    'wifi_router_count' => $wifiRouterCount,
                    'organization_id' => $orgId,
                ]);

                $created[] = $room;
            }
        });

        $count = count($created);
        $this->invalidateMapCache($orgId);
        $this->safeBroadcast(new RoomChanged('batch_generated', ['count' => $count, 'block' => $block, 'floor' => $floor], "Đã tạo nhanh {$count} phòng mới cho {$block} - Tầng {$floor}.", $orgId));

        return [
            'created_count' => $count,
            'rooms' => $created,
        ];
    }

    /**
     * [1. MỤC ĐÍCH]
     * Đổi tên một Block / Khu vực và cập nhật đồng loạt toàn bộ các phòng thuộc Block đó.
     * @param string $oldBlock Tên cũ
     * @param string $newBlock Tên mới
     * @param int|null $orgId
     * @return int Số phòng bị tác động
     */
    public function renameBlock(string $oldBlock, string $newBlock, ?int $orgId = null): int
    {
        $activeOrg = $this->getActiveOrgId($orgId);
        $count = RoomModel::where('organization_id', $activeOrg)
            ->where('block', $oldBlock)
            ->update(['block' => $newBlock]);

        $this->invalidateMapCache($activeOrg);

        $this->safeBroadcast(new RoomChanged('block_renamed', ['old_block' => $oldBlock, 'new_block' => $newBlock], "Đã đổi tên khu vực từ '{$oldBlock}' thành '{$newBlock}' ({$count} phòng).", $activeOrg));

        return $count;
    }

    /**
     * [1. MỤC ĐÍCH]
     * Xóa toàn bộ phòng của một Block và tự động thu hồi toàn bộ thiết bị về kho.
     * @param string $block Tên block cần xóa
     * @param int|null $orgId
     * @return int Số phòng đã xóa
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

            $this->safeBroadcast(new RoomChanged('block_deleted', ['block' => $block], "Đã xóa toàn bộ các phòng thuộc khu '{$block}'.", $activeOrg));

            return $count;
        });
    }
}
