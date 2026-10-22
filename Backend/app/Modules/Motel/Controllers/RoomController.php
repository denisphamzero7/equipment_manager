<?php

namespace App\Modules\Motel\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Motel\Events\RoomChanged;
use App\Modules\Motel\Exports\RoomExport;
use App\Modules\Motel\Imports\RoomImport;
use App\Modules\Motel\Requests\BulkUpdateStatusRoomRequest;
use App\Modules\Motel\Requests\StoreRoomRequest;
use App\Modules\Motel\Requests\UpdateRoomRequest;
use App\Modules\Motel\Services\RoomService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class RoomController extends Controller
{
    public function __construct(private RoomService $roomService)
    {
    }

    public function stats(Request $request): JsonResponse
    {
        $filters = $request->all();
        $stats = $this->roomService->stats($filters);
        return response()->json($stats);
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $request->all();
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
            'bedroom_count' => 'nullable|integer|min:0|max:20',
            'living_room_count' => 'nullable|integer|min:0|max:20',
            'wifi_mode' => 'nullable|string|in:dedicated,shared,none',
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

    public function bulkUpdateStatus(BulkUpdateStatusRoomRequest $request): JsonResponse
    {
        $ids = $request->input('ids', []);
        $status = $request->input('status');
        $count = $this->roomService->bulkUpdateStatus($ids, $status);
        return response()->json([
            'message' => 'Cập nhật trạng thái thành công',
            'updated_count' => $count,
        ]);
    }

    public function export(Request $request)
    {
        $organizationId = (int) ($request->header('X-Organization-Id')
            ?: (function_exists('getPermissionsTeamId') ? getPermissionsTeamId() : 0));
        abort_if(!$organizationId, 422, 'Không xác định được tổ chức để xuất dữ liệu.');

        $fileName = 'danh-sach-phong-'.now()->format('Y-m-d-His').'.xlsx';
        return Excel::download(
            new RoomExport($request->all(), $organizationId, $request->boolean('template')),
            $fileName
        );
    }

    public function import(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
        ], [
            'file.required' => 'Vui lòng chọn tệp Excel để nhập danh sách phòng.',
            'file.mimes' => 'Hệ thống chỉ hỗ trợ định dạng Excel (.xlsx, .xls) hoặc .csv.',
            'file.max' => 'Dung lượng tệp không được vượt quá 10MB.',
        ]);

        $organizationId = (int) ($request->header('X-Organization-Id')
            ?: (function_exists('getPermissionsTeamId') ? getPermissionsTeamId() : 0));
        abort_if(!$organizationId, 422, 'Không xác định được tổ chức để nhập dữ liệu.');

        $import = new RoomImport($organizationId);
        Excel::import($import, $validated['file']);
        $result = $import->getResult();

        if ($result['success_count'] > 0) {
            $this->roomService->invalidateMapCache($organizationId);
            event(new RoomChanged(
                'room_imported',
                ['imported_count' => $result['success_count']],
                "Đã nhập {$result['success_count']} phòng từ Excel.",
                $organizationId
            ));
        }

        return response()->json([
            'success' => true,
            'message' => "Nhập dữ liệu hoàn tất: Thành công {$result['success_count']}, Bỏ qua / thất bại {$result['failed_count']}.",
            'data' => $result,
        ]);
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
