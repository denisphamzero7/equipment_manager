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

        $blocks = $query->whereNotNull('block')
            ->distinct()
            ->pluck('block')
            ->toArray();

        if (empty($blocks)) {
            // Danh sách mặc định theo thực tế ảnh khách sạn
            $blocks = [
                'Olalani Hotel',
                'Block A',
                'Block B',
                'Block C',
                'Block D',
                'Condos 1',
                'Condos 2',
                'Penthouse-spa+Villa',
            ];
        }

        return $blocks;
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
