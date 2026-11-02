<?php

namespace App\Modules\Equipment\Services;

use App\Modules\Motel\Models\Room;
use Illuminate\Support\Collection;

class EquipmentMapViewService
{
    /**
     * [1. MỤC ĐÍCH]
     * Xóa cache sơ đồ mặt bằng Map View (danh sách tòa nhà, danh sách tầng, phiên bản sơ đồ phòng) khi có thay đổi.
     *
     * [2. LUỒNG XỬ LÝ (FLOW)]
     * 1. Xác định `$organizationId` (nếu null, lấy từ quyền team hiện tại hoặc cơ sở đầu tiên).
     * 2. Xóa cache danh sách Block (`hotel_blocks_{orgId}` và `hotel_blocks_all`).
     * 3. Xóa cache danh sách Tầng (`hotel_floors_{orgId}_all` và `hotel_floors_all_all`).
     * 4. Tăng số version cache `equipment_map_view_version_{orgId}` và `equipment_map_view_version_all` lên 1.
     *    -> Trình duyệt/App phía Client khi kiểm tra version sẽ nhận biết dữ liệu cũ và tự động tải lại sơ đồ mới.
     *
     * [3. NGUỒN GỌI (CALLERS)]
     * - Khi tạo/sửa/xóa phòng: RoomController, MotelService
     * - Khi gán/thu hồi/đổi trạng thái thiết bị: EquipmentService::invalidateCache()
     *
     * [4. THAM SỐ (PARAMETERS)]
     * @param int|null $organizationId ID cơ sở / tổ chức
     *
     * [5. GIÁ TRỊ TRẢ VỀ]
     * @return void
     *
     * [6. VÍ DỤ SỬ DỤNG]
     * @example
     * ```php
     * $mapViewService->invalidateMapCache(1);
     * ```
     */
    public function invalidateMapCache(?int $organizationId = null): void
    {
        $orgId = $organizationId ?: (function_exists('getPermissionsTeamId') ? getPermissionsTeamId() : (\App\Modules\Core\Models\Organization::value('id') ?? 1));
        cache()->forget('hotel_blocks_' . $orgId);
        cache()->forget('hotel_blocks_all');
        cache()->forget('hotel_floors_' . $orgId . '_all');
        cache()->forget('hotel_floors_all_all');

        $v = (int) cache()->get('equipment_map_view_version_' . $orgId, 1);
        cache()->put('equipment_map_view_version_' . $orgId, $v + 1, 86400 * 30);
        $vAll = (int) cache()->get('equipment_map_view_version_all', 1);
        cache()->put('equipment_map_view_version_all', $vAll + 1, 86400 * 30);
    }

    /**
     * [1. MỤC ĐÍCH]
     * Lấy danh sách các Tòa nhà / Block hiện có trong cơ sở để hiển thị trên bộ lọc Map View.
     *
     * [2. LUỒNG XỬ LÝ (FLOW)]
     * 1. Kiểm tra cache với key `hotel_blocks_{orgId}` trong vòng 120 giây.
     * 2. Nếu chưa có trong cache:
     *    - Truy vấn bảng `rooms`, lọc theo `organization_id` (nếu có).
     *    - Lấy các giá trị `block` khác null, loại trừ trùng lặp (`distinct()`).
     *    - Sắp xếp tăng dần theo tên block ('Tòa A', 'Tòa B'...).
     * 3. Trả về mảng danh sách các tên Block.
     *
     * [3. NGUỒN GỌI (CALLERS)]
     * - API: GET /api/v1/equipments/map-view/blocks
     * - Controller: App\Modules\Equipment\Controllers\EquipmentMapViewController::getBlocks()
     * - Frontend Filter Toolbar: `MapFilterToolbar.vue`
     *
     * [4. THAM SỐ (PARAMETERS)]
     * @param int|null $organizationId ID cơ sở
     *
     * [5. GIÁ TRỊ TRẢ VỀ]
     * @return array<string> Mảng danh sách tên các block (ví dụ: ['Block A', 'Block B'])
     *
     * [6. VÍ DỤ SỬ DỤNG]
     * @example
     * ```php
     * $blocks = $mapViewService->getAvailableBlocks(1);
     * ```
     */
    public function getAvailableBlocks(?int $organizationId = null): array
    {
        $cacheKey = 'hotel_blocks_' . ($organizationId ?? 'all');

        return cache()->remember($cacheKey, 120, function () use ($organizationId) {
            $query = Room::query();
            if ($organizationId) {
                $query->where('organization_id', $organizationId);
            }

            return $query->whereNotNull('block')
                ->distinct()
                ->orderBy('block', 'asc')
                ->pluck('block')
                ->toArray();
        });
    }

    /**
     * [1. MỤC ĐÍCH]
     * Lấy danh sách các Tầng hiện có của khách sạn (có thể lọc theo tòa nhà cụ thể).
     *
     * [2. LUỒNG XỬ LÝ (FLOW)]
     * 1. Kiểm tra cache với key `hotel_floors_{orgId}_{block}` trong vòng 120 giây.
     * 2. Nếu cache miss:
     *    - Truy vấn bảng `rooms`, lọc theo `organization_id` và `block` (nếu được truyền).
     *    - Lọc các bản ghi có cột `floor` không null, loại bỏ trùng (`distinct()`).
     *    - Sắp xếp thứ tự tầng từ thấp đến cao (tầng 1 -> tầng n).
     * 3. Trả về mảng danh sách số tầng.
     *
     * [3. NGUỒN GỌI (CALLERS)]
     * - API: GET /api/v1/equipments/map-view/floors
     * - Controller: App\Modules\Equipment\Controllers\EquipmentMapViewController::getFloors()
     * - Frontend Filter: `MapFilterToolbar.vue` khi chọn Block
     *
     * [4. THAM SỐ (PARAMETERS)]
     * @param string|null $block Tên Block cần lọc tầng (nếu null sẽ lấy toàn bộ các tầng của khách sạn)
     * @param int|null $organizationId ID cơ sở
     *
     * [5. GIÁ TRỊ TRẢ VỀ]
     * @return array<int> Mảng danh sách các số tầng (ví dụ: [1, 2, 3, 4, 5])
     *
     * [6. VÍ DỤ SỬ DỤNG]
     * @example
     * ```php
     * $floors = $mapViewService->getAvailableFloors('Tòa A', 1);
     * ```
     */
    public function getAvailableFloors(?string $block = null, ?int $organizationId = null): array
    {
        $cacheKey = 'hotel_floors_' . ($organizationId ?? 'all') . '_' . ($block ?? 'all');

        return cache()->remember($cacheKey, 120, function () use ($block, $organizationId) {
            $query = Room::query();
            if ($organizationId) {
                $query->where('organization_id', $organizationId);
            }
            if (!empty($block)) {
                $query->where('block', $block);
            }

            return $query->whereNotNull('floor')
                ->distinct()
                ->orderBy('floor', 'asc')
                ->pluck('floor')
                ->toArray();
        });
    }

    /**
     * [1. MỤC ĐÍCH]
     * Lấy toàn bộ danh sách phòng và trạng thái trang thiết bị theo tọa độ ma trận lưới (Grid Row x Grid Col) để vẽ sơ đồ Map View.
     *
     * [2. LUỒNG XỬ LÝ (FLOW)]
     * 1. Khởi tạo query từ model Room kèm eager loading toàn diện:
     *    - `roomType`: Thông tin loại phòng (tiêu chuẩn, VIP, gia đình...).
     *    - `sharedWifiRoom.equipments.model.category`: Thông tin phòng gốc cấp wifi nếu đang dùng ké wifi.
     *    - `dependentWifiRooms`: Danh sách các phòng phụ thuộc wifi vào phòng này.
     *    - `equipments.model.category`: Danh sách toàn bộ thiết bị đang gắn trong phòng.
     *    - `standardEquipments.model.category`: Cấu hình định mức thiết bị chuẩn của phòng.
     * 2. Lọc theo `block`, `floor`, và `organization_id` nếu người dùng chọn bộ lọc.
     * 3. Sắp xếp vị trí phòng logic trên sơ đồ:
     *    - Tầng cao nhất ở trên (`floor desc`).
     *    - Hàng từ trên xuống dưới (`grid_row asc`).
     *    - Cột từ trái sang phải (`grid_col asc`).
     *    - Số phòng tăng dần (`room_number asc`).
     * 4. Trả về Collection các phòng.
     *
     * [3. NGUỒN GỌI (CALLERS)]
     * - API: GET /api/v1/equipments/map-view
     * - Controller: App\Modules\Equipment\Controllers\EquipmentMapViewController::index()
     * - Frontend View: `Frontend/src/modules/equipment/views/equipment-map/index.vue`
     *
     * [4. THAM SỐ (PARAMETERS)]
     * @param array $params Mảng tham số lọc (block, floor, organization_id)
     *
     * [5. GIÁ TRỊ TRẢ VỀ]
     * @return \Illuminate\Support\Collection<Room> Collection danh sách các phòng đã nạp đầy đủ thông tin thiết bị
     *
     * [6. VÍ DỤ SỬ DỤNG]
     * @example
     * ```php
     * $rooms = $mapViewService->getMapViewRooms([
     *     'block' => 'Tòa A',
     *     'floor' => 2,
     *     'organization_id' => 1
     * ]);
     * ```
     */
    public function getMapViewRooms(array $params = []): Collection
    {
        $query = Room::with([
            'roomType',
            'sharedWifiRoom.equipments.model.category',
            'dependentWifiRooms',
            'equipments.model.category',
            'standardEquipments.model.category',
        ]);

        if (!empty($params['block'])) {
            $query->where('block', $params['block']);
        }
        if (!empty($params['floor'])) {
            $query->where('floor', $params['floor']);
        }
        if (!empty($params['organization_id'])) {
            $query->where('organization_id', $params['organization_id']);
        }

        return $query->orderBy('floor', 'desc')
            ->orderBy('grid_row', 'asc')
            ->orderBy('grid_col', 'asc')
            ->orderBy('room_number', 'asc')
            ->get();
    }
}
