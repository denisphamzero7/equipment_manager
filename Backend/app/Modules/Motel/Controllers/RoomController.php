<?php

namespace App\Modules\Motel\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Request\FilterRequest;
use App\Modules\Motel\Requests\StoreRoomRequest;
use App\Modules\Motel\Requests\UpdateRoomRequest;
use App\Modules\Motel\Services\RoomService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoomController extends Controller
{
    public function __construct(private RoomService $roomService)
    {
    }

    public function stats(FilterRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $stats = $this->roomService->stats($filters);
        return response()->json($stats);
    }

    public function index(FilterRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $limit = (int) $request->input('limit', 15);
        $rooms = $this->roomService->index($filters, $limit);

        return response()->json($rooms);
    }

    public function getRoomTypes(Request $request): JsonResponse
    {
        $types = $this->roomService->getRoomTypes();
        return response()->json($types);
    }

    public function show($id): JsonResponse
    {
        $room = $this->roomService->getRoomById((int) $id);
        return response()->json($room);
    }

    public function store(StoreRoomRequest $request): JsonResponse
    {
        $data = $request->validated();
        $room = $this->roomService->createRoom($data);
        return response()->json($room, 201);
    }

    public function batchGenerate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'block' => 'required|string|max:50',
            'floor' => 'required|integer|min:1',
            'start_number' => 'required|integer|min:1',
            'end_number' => 'required|integer|min:1',
            'prefix' => 'nullable|string|max:50',
            'room_type_id' => 'nullable|integer',
            'price' => 'nullable|numeric|min:0',
            'status' => 'nullable|string',
        ]);

        $result = $this->roomService->batchGenerate($validated);
        return response()->json($result, 201);
    }

    public function update(UpdateRoomRequest $request, $id): JsonResponse
    {
        $data = $request->validated();
        $room = $this->roomService->updateRoom((int) $id, $data);
        return response()->json($room);
    }

    public function renameBlock(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'old_block' => 'required|string',
            'new_block' => 'required|string|max:50',
        ]);

        $count = $this->roomService->renameBlock($validated['old_block'], $validated['new_block']);
        return response()->json([
            'message' => "Đã đổi tên khu vực từ '{$validated['old_block']}' sang '{$validated['new_block']}'.",
            'updated_count' => $count,
        ]);
    }

    public function deleteBlock(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'block' => 'required|string',
        ]);

        $count = $this->roomService->deleteBlock($validated['block']);
        return response()->json([
            'message' => "Đã xóa toàn bộ các phòng thuộc khu '{$validated['block']}'.",
            'deleted_count' => $count,
        ]);
    }

    public function bulkDestroy(Request $request): JsonResponse
    {
        $ids = $request->input('ids', []);
        $retract = $request->boolean('retract_equipments', true);
        $count = $this->roomService->bulkDestroy($ids, $retract);
        return response()->json([
            'message' => 'Rooms deleted successfully',
            'deleted_count' => $count,
        ]);
    }

    public function destroy($id, Request $request): JsonResponse
    {
        $retract = $request->boolean('retract_equipments', true);
        $this->roomService->deleteRoom((int) $id, $retract);
        return response()->json(['message' => 'Room deleted successfully']);
    }

    public function changeStatus(Request $request, $id): JsonResponse
    {
        $room = $this->roomService->getRoomById((int) $id);
        if ($request->filled('status')) {
            $room->status = $request->input('status');
            $room->save();
        }
        return response()->json($room);
    }
}
