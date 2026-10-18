<?php

namespace App\Modules\Equipment\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Equipment\Resources\EquipmentMapViewResource;
use App\Modules\Equipment\Services\EquipmentMapViewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Sơ đồ Khách sạn & Thiết bị (Hotel Map View)
 *
 * API cung cấp dữ liệu trực quan cho sơ đồ mặt bằng các Block, Tầng và trạng thái thiết bị của từng phòng.
 */
class EquipmentMapViewController extends Controller
{
    public function __construct(
        protected EquipmentMapViewService $mapViewService
    ) {}

    /**
     * Danh sách Block / Khu vực của khách sạn
     */
    public function getBlocks(Request $request): JsonResponse
    {
        $blocks = $this->mapViewService->getAvailableBlocks($request->user()?->organization_id);
        return $this->success($blocks, 'Lấy danh sách Block thành công');
    }

    /**
     * Dữ liệu sơ đồ mặt bằng phòng (Map View)
     *
     * Lấy danh sách phòng theo Block và Tầng kèm trạng thái cảnh báo thiết bị (xanh, đỏ, vàng).
     */
    public function index(Request $request): JsonResponse
    {
        $rooms = $this->mapViewService->getMapViewRooms($request->all());
        $data = EquipmentMapViewResource::collection($rooms);

        return $this->success($data, 'Lấy dữ liệu Map View thành công');
    }
}
