<?php

namespace App\Modules\Equipment\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Equipment\Resources\RoomEquipmentDetailResource;
use App\Modules\Equipment\Services\EquipmentService;
use Illuminate\Http\JsonResponse;

/**
 * @group Chi tiết Thiết bị Phòng
 *
 * API hiển thị chi tiết thiết bị khi người dùng nhấp chọn 1 phòng trên sơ đồ khách sạn.
 */
class RoomEquipmentDetailController extends Controller
{
    public function __construct(
        protected EquipmentService $service
    ) {}

    /**
     * Chi tiết thiết bị của một phòng
     *
     * Hiển thị toàn bộ TV, Remote, Box, Wifi của phòng; phân loại theo quy cách (32", 40"), tình trạng tốt/hỏng và đối soát thiết bị thiếu.
     */
    public function show(int $roomId): JsonResponse
    {
        $room = $this->service->getRoomDetail($roomId);
        return $this->successResource(new RoomEquipmentDetailResource($room), 'Lấy chi tiết thiết bị phòng thành công');
    }
}
