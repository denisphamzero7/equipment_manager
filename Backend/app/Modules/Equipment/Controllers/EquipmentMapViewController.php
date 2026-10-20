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

    protected function resolveOrganizationId(Request $request): ?int
    {
        $orgId = $request->header('X-Organization-Id') ?? $request->header('x-organization-id');
        if ($orgId && is_numeric($orgId)) {
            return (int) $orgId;
        }
        if (function_exists('getPermissionsTeamId') && getPermissionsTeamId()) {
            return (int) getPermissionsTeamId();
        }
        return $request->user()?->organization_id;
    }

    /**
     * Danh sách Block / Khu vực của khách sạn
     */
    public function getBlocks(Request $request): JsonResponse
    {
        $orgId = $this->resolveOrganizationId($request);
        $blocks = $this->mapViewService->getAvailableBlocks($orgId);
        return $this->success($blocks, 'Lấy danh sách Block thành công');
    }

    /**
     * Danh sách Tầng (Floors) của khách sạn
     */
    public function getFloors(Request $request): JsonResponse
    {
        $orgId = $this->resolveOrganizationId($request);
        $floors = $this->mapViewService->getAvailableFloors(
            $request->query('block'),
            $orgId
        );
        return $this->success($floors, 'Lấy danh sách Tầng thành công');
    }

    /**
     * Dữ liệu sơ đồ mặt bằng phòng (Map View)
     *
     * Lấy danh sách phòng theo Block và Tầng kèm trạng thái cảnh báo thiết bị (xanh, đỏ, vàng).
     */
    public function index(Request $request): JsonResponse
    {
        $params = $request->all();
        $orgId = $this->resolveOrganizationId($request);
        if ($orgId && empty($params['organization_id'])) {
            $params['organization_id'] = $orgId;
        }

        $rooms = $this->mapViewService->getMapViewRooms($params);
        $data = EquipmentMapViewResource::collection($rooms);

        return $this->success($data, 'Lấy dữ liệu Map View thành công');
    }
}
