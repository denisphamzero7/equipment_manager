<?php

namespace App\Modules\Equipment\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Equipment\Exports\EquipmentExport;
use App\Modules\Equipment\Imports\EquipmentImport;
use App\Modules\Equipment\Requests\AssignRoomEquipmentRequest;
use App\Modules\Equipment\Requests\BulkUpdateStatusEquipmentRequest;
use App\Modules\Equipment\Requests\ChangeEquipmentStatusRequest;
use App\Modules\Equipment\Requests\ImportEquipmentRequest;
use App\Modules\Equipment\Requests\ReportBrokenRequest;
use App\Modules\Equipment\Requests\StoreEquipmentRequest;
use App\Modules\Equipment\Requests\UpdateEquipmentRequest;
use App\Modules\Equipment\Resources\EquipmentCollection;
use App\Modules\Equipment\Resources\EquipmentResource;
use App\Modules\Equipment\Services\EquipmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

/**
 * @group Quản lý Trang thiết bị Khách sạn
 *
 * API quản lý cá thể thiết bị, điều chuyển kho - phòng, báo hỏng, kiểm kê và xuất nhập Excel.
 */
class EquipmentController extends Controller
{
    public function __construct(
        protected EquipmentService $service
    ) {}

    /**
     * Danh sách thiết bị
     *
     * Lấy danh sách thiết bị có phân trang, lọc theo trạng thái, phòng, danh mục.
     */
    public function index(Request $request): JsonResponse
    {
        $items = $this->service->paginate($request->all());
        return $this->successCollection(new EquipmentCollection($items), 'Lấy danh sách thiết bị thành công');
    }

    /**
     * Chi tiết thiết bị
     */
    public function show(int $id): JsonResponse
    {
        $item = $this->service->find($id);
        return $this->successResource(new EquipmentResource($item), 'Lấy thông tin thiết bị thành công');
    }

    /**
     * Thêm mới thiết bị
     *
     * Tạo mới thiết bị kèm số serial, vị trí và ảnh chụp nếu có.
     */
    public function store(StoreEquipmentRequest $request): JsonResponse
    {
        $data = $request->validated();
        if ($request->hasFile('photo')) {
            $data['photo'] = $request->file('photo');
        }

        $item = $this->service->create($data);
        return $this->successResource(new EquipmentResource($item), 'Thêm thiết bị mới thành công', 201);
    }

    /**
     * Cập nhật thông tin thiết bị
     */
    public function update(UpdateEquipmentRequest $request, int $id): JsonResponse
    {
        $data = $request->validated();
        if ($request->hasFile('photo')) {
            $data['photo'] = $request->file('photo');
        }

        $item = $this->service->update($id, $data);
        return $this->successResource(new EquipmentResource($item), 'Cập nhật thiết bị thành công');
    }

    /**
     * Xóa thiết bị
     */
    public function destroy(int $id): JsonResponse
    {
        $this->service->delete($id);
        return $this->success(null, 'Đã xóa thiết bị thành công');
    }

    /**
     * Đổi trạng thái thiết bị nhanh
     */
    public function changeStatus(ChangeEquipmentStatusRequest $request, int $id): JsonResponse
    {
        $item = $this->service->changeStatus(
            $id,
            $request->validated('status'),
            $request->validated('note')
        );

        return $this->successResource(new EquipmentResource($item), 'Cập nhật trạng thái thiết bị thành công');
    }

    /**
     * Cập nhật trạng thái hàng loạt
     */
    public function bulkUpdateStatus(BulkUpdateStatusEquipmentRequest $request): JsonResponse
    {
        $count = $this->service->bulkUpdateStatus(
            $request->validated('ids'),
            $request->validated('status'),
            $request->validated('note')
        );

        return $this->success(['updated_count' => $count], "Đã cập nhật trạng thái cho {$count} thiết bị.");
    }

    /**
     * Xóa hàng loạt
     */
    public function bulkDestroy(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|integer|exists:room_equipments,id',
        ]);

        $count = $this->service->bulkDestroy($request->input('ids'));
        return $this->success(['deleted_count' => $count], "Đã xóa {$count} thiết bị thành công.");
    }

    /**
     * Điều chuyển thiết bị (Gán phòng hoặc thu hồi về kho)
     */
    public function assignToRoom(AssignRoomEquipmentRequest $request): JsonResponse
    {
        $item = $this->service->assignToRoom(
            $request->validated('equipment_id'),
            $request->validated('room_id'),
            $request->validated('position_note')
        );

        $msg = $item->room_id
            ? "Đã gán thiết bị vào phòng {$item->room->room_number}"
            : 'Đã thu hồi thiết bị về kho';

        return $this->successResource(new EquipmentResource($item), $msg);
    }

    /**
     * Báo hỏng thiết bị (Kèm ảnh hiện trường)
     */
    public function reportBroken(ReportBrokenRequest $request, int $id): JsonResponse
    {
        $photo = $request->file('broken_photo');
        $item = $this->service->reportBroken(
            $id,
            $request->validated('issue_description'),
            $photo
        );

        return $this->successResource(new EquipmentResource($item), 'Đã tiếp nhận báo hỏng thiết bị và tạo phiếu bảo trì');
    }

    /**
     * Tải ảnh cho thiết bị
     */
    public function uploadPhoto(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'file' => 'required|image|max:10240',
            'collection' => 'nullable|string|in:equipment_photos,broken_photos',
        ]);

        $item = $this->service->uploadPhoto(
            $id,
            $request->file('file'),
            $request->input('collection', 'equipment_photos')
        );

        return $this->successResource(new EquipmentResource($item), 'Tải ảnh thiết bị thành công');
    }

    /**
     * Báo cáo thống kê toàn khách sạn
     *
     * Thống kê tổng số lượng TV, Remote, Box, Wifi...; phân loại theo tốt, hỏng, trong kho, bảo trì.
     */
    public function stats(Request $request): JsonResponse
    {
        $stats = $this->service->getHotelStats($request->user()?->organization_id);
        return $this->success($stats, 'Lấy báo cáo thống kê thiết bị thành công');
    }

    /**
     * Xuất danh sách thiết bị ra Excel
     *
     * Xuất ra các trường: ID, Mã Serial, Danh mục, Mẫu thiết bị, Thương hiệu, Phòng đặt, Vị trí trong phòng, Trạng thái, Ghi chú tình trạng, Ngày lắp đặt, Ngày tạo.
     */
    public function export(Request $request)
    {
        $fileName = 'danh-sach-thiet-bi-'.now()->format('Y-m-d-His').'.xlsx';
        return Excel::download(new EquipmentExport($request->all()), $fileName);
    }

    /**
     * Nhập danh sách thiết bị từ Excel
     *
     * Cột bắt buộc: mau_thiet_bi (hoặc model_name). Cột không bắt buộc: ma_serial, so_phong, trang_thai (mặc định good), vi_tri_trong_phong, ghi_chu.
     */
    public function import(ImportEquipmentRequest $request): JsonResponse
    {
        $import = new EquipmentImport;
        Excel::import($import, $request->file('file'));

        $result = $import->getResult();
        return $this->success($result, "Nhập dữ liệu hoàn tất: Thành công {$result['success_count']}, Thất bại {$result['failed_count']}");
    }
}
