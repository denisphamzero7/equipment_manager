<?php 
namespace App\Modules\Motel\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Request\FilterRequest;
use App\Modules\Motel\Services\RoomService;


class RoomController extends Controller
{
    public function __construct(private RoomService $roomService)
    {
    }

    public function index(FilterRequest $request)
    {
        $filters = $request->validated();
        $limit = $request->input('limit', 10);
        $rooms = $this->roomService->index($filters, $limit);

        return response()->json($rooms);
    }
    // Chi tết phòng
    public function show($id)
    {
        $room = $this->roomService->getRoomById($id);
        return response()->json($room);
    }

    //Tạo phòng mới
    public function store(FilterRequest $request)
    {
        $data = $request->validated();
        $room = $this->roomService->createRoom($data);
        return response()->json($room, 201);
    }

    //Cập nhật thông tin phòng
    public function update(FilterRequest $request, $id)
    {
        $data = $request->validated();
        $room = $this->roomService->updateRoom($id, $data);
        return response()->json($room);
    }
    // Xóa nhiều phòng
    public function bulkDestroy(FilterRequest $request)
    {
        $ids = $request->input('ids', []);
        foreach ($ids as $id) {
            $this->roomService->deleteRoom($id);
        }
        return response()->json(['message' => 'Rooms deleted successfully']);
    }

    // Xóa một phòng
    public function destroy($id)
    {
        $this->roomService->deleteRoom($id);
        return response()->json(['message' => 'Room deleted successfully']);
    }

    // Đổi trạng thái phòng
    public function changeStatus(FilterRequest $request, $id)
    {
        $data = $request->validated();
        $room = $this->roomService->getRoomById($id);
        $room->status = $data['status'];
        $room->save();
        return response()->json($room);
    }


    // xuất danh sách phòng ra file excel
    public function export(FilterRequest $request)
    {
        $filters = $request->validated();
        return $this->roomService->export($filters);
    }
    // nhập danh sách phòng từ file excel
    public function import(FilterRequest $request)
    {
        $file = $request->file('file');
        $this->roomService->import($file);
        return response()->json(['message' => 'Rooms imported successfully']);
    }
}