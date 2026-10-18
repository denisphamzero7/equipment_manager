<?php

namespace App\Modules\Equipment\Services;

use App\Modules\Motel\Models\Room;
use Illuminate\Support\Collection;

class EquipmentMapViewService
{
    /**
     * Lấy danh sách các Block / Khu vực hiện có của khách sạn.
     */
    public function getAvailableBlocks(?int $organizationId = null): array
    {
        $query = Room::query();
        if ($organizationId) {
            $query->where('organization_id', $organizationId);
        }

        return $query->whereNotNull('block')
            ->distinct()
            ->orderBy('block', 'asc')
            ->pluck('block')
            ->toArray();
    }

    /**
     * Lấy danh sách các Tầng hiện có (có thể lọc theo Block nếu có).
     */
    public function getAvailableFloors(?string $block = null, ?int $organizationId = null): array
    {
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
    }

    /**
     * Lấy dữ liệu các phòng cho sơ đồ Map View theo Block và Tầng.
     */
    public function getMapViewRooms(array $params = []): Collection
    {
        $query = Room::with([
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
