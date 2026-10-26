<?php

namespace App\Modules\Equipment\Services;

use App\Modules\Motel\Models\Room;
use Illuminate\Support\Collection;

class EquipmentMapViewService
{
    /**
     * Xóa cache sơ đồ Map View khi có phòng hoặc thiết bị thay đổi.
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
     * Lấy danh sách các Block / Khu vực hiện có của khách sạn.
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
     * Lấy danh sách các Tầng hiện có (có thể lọc theo Block nếu có).
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
     * Lấy dữ liệu các phòng cho sơ đồ Map View theo Block và Tầng.
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
