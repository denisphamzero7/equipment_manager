<?php

namespace App\Modules\Equipment\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Equipment\Events\EquipmentImported;
use App\Modules\Equipment\Exports\EquipmentExport;
use App\Modules\Equipment\Imports\EquipmentImport;
use App\Modules\Equipment\Models\EquipmentModel;
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
use App\Modules\Motel\Models\Room;
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
     * [1. MỤC ĐÍCH]
     * Lấy danh sách thiết bị có phân trang và bộ lọc linh hoạt (trạng thái, phòng, block, model, từ khóa).
     *
     * [2. LUỒNG XỬ LÝ (FLOW)]
     * 1. Nhận toàn bộ query parameters từ HTTP Request ($request->all()).
     * 2. Ủy quyền truy vấn cho tầng nghiệp vụ: `$this->service->paginate($filters)`.
     * 3. Đóng gói danh sách kết quả vào `EquipmentCollection` (chuẩn hóa dữ liệu API trả về).
     * 4. Trả về JSON Response thành công kèm thông tin phân trang (total, per_page, current_page).
     *
     * [3. NGUỒN GỌI (CALLERS)]
     * - Route: GET /api/v1/equipments (Tên route: api.v1.equipments.index)
     * - Frontend: `equipmentService.getEquipments()` tại trang Quản lý thiết bị (`equipment-list/index.vue`)
     *
     * [4. THAM SỐ (PARAMETERS)]
     * @param \Illuminate\Http\Request $request Chứa: status, block, floor, room_id, search, per_page, sort_by...
     *
     * [5. GIÁ TRỊ TRẢ VỀ]
     * @return \Illuminate\Http\JsonResponse JSON danh sách thiết bị và metadata phân trang
     *
     * [6. VÍ DỤ SỬ DỤNG]
     * @example
     * GET /api/v1/equipments?status=broken&block=Tòa%20A&per_page=15
     */
    public function index(Request $request): JsonResponse
    {
        $items = $this->service->paginate($request->all());
        return $this->successCollection(new EquipmentCollection($items), 'Lấy danh sách thiết bị thành công');
    }

    /**
     * [1. MỤC ĐÍCH]
     * Lấy thông tin chi tiết một cá thể thiết bị theo ID.
     *
     * [2. LUỒNG XỬ LÝ (FLOW)]
     * 1. Gọi `$this->service->find($id)` để truy vấn thiết bị và eager load model, room, media, lịch sử sửa chữa.
     * 2. Định dạng đầu ra thông qua `EquipmentResource`.
     * 3. Trả về JSON Response 200 kèm dữ liệu thiết bị.
     *
     * [3. NGUỒN GỌI (CALLERS)]
     * - Route: GET /api/v1/equipments/{id}
     * - Frontend: `equipmentService.getEquipment(id)` tại trang Chi tiết thiết bị (`equipment-detail/index.vue`)
     *
     * [4. THAM SỐ (PARAMETERS)]
     * @param int $id ID thiết bị (`room_equipments.id`)
     *
     * [5. GIÁ TRỊ TRẢ VỀ & NGOẠI LỆ]
     * @return \Illuminate\Http\JsonResponse Chi tiết thiết bị
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException Khi không tìm thấy ID (trả về 404)
     *
     * [6. VÍ DỤ SỬ DỤNG]
     * @example
     * GET /api/v1/equipments/25
     */
    public function show(int $id): JsonResponse
    {
        $item = $this->service->find($id);
        return $this->successResource(new EquipmentResource($item), 'Lấy thông tin thiết bị thành công');
    }

    /**
     * [1. MỤC ĐÍCH]
     * Thêm mới một thiết bị vào hệ thống (gắn vào phòng hoặc lưu kho dự phòng).
     *
     * [2. LUỒNG XỬ LÝ (FLOW)]
     * 1. Validate dữ liệu qua FormRequest `StoreEquipmentRequest` (model_id, serial_number, room_id...).
     * 2. Lấy `$clientRequestId` (UUID) để xử lý chống lặp sự kiện real-time phía client.
     * 3. Trích xuất file ảnh đính kèm nếu có (`$request->file('photo')`).
     * 4. Gọi `$this->service->create($data, $clientRequestId)` để tạo bản ghi và lưu ảnh.
     * 5. Trả về JSON Resource `EquipmentResource` với HTTP Status Code 201 (Created).
     *
     * [3. NGUỒN GỌI (CALLERS)]
     * - Route: POST /api/v1/equipments
     * - Frontend: Form modal thêm thiết bị `EquipmentFormModal.vue`
     *
     * [4. THAM SỐ (PARAMETERS)]
     * @param \App\Modules\Equipment\Requests\StoreEquipmentRequest $request Form Request đã validate
     *
     * [5. GIÁ TRỊ TRẢ VỀ]
     * @return \Illuminate\Http\JsonResponse Dữ liệu thiết bị vừa tạo kèm mã 201
     *
     * [6. VÍ DỤ SỬ DỤNG]
     * @example
     * POST /api/v1/equipments Payload: { "equipment_model_id": 3, "serial_number": "SN123", "room_id": 10 }
     */
    public function store(StoreEquipmentRequest $request): JsonResponse
    {
        $data = $request->validated();
        $clientRequestId = $data['client_request_id'] ?? null;
        unset($data['client_request_id']);
        if ($request->hasFile('photo')) {
            $data['photo'] = $request->file('photo');
        }

        $item = $this->service->create($data, $clientRequestId);
        return $this->successResource(new EquipmentResource($item), 'Thêm thiết bị mới thành công', 201);
    }

    /**
     * [1. MỤC ĐÍCH]
     * Cập nhật thông tin chi tiết của một thiết bị.
     *
     * [2. LUỒNG XỬ LÝ (FLOW)]
     * 1. Validate payload đầu vào bằng `UpdateEquipmentRequest`.
     * 2. Xử lý ảnh đính kèm nếu client tải lên file mới.
     * 3. Chuyển tiếp cho `$this->service->update($id, $data)` để cập nhật DB và phát broadcast.
     * 4. Trả về JSON Resource thành công.
     *
     * [3. NGUỒN GỌI (CALLERS)]
     * - Route: PUT/POST /api/v1/equipments/{id}
     * - Frontend: `EquipmentFormModal.vue` khi bấm "Lưu thay đổi"
     *
     * [4. THAM SỐ (PARAMETERS)]
     * @param \App\Modules\Equipment\Requests\UpdateEquipmentRequest $request Dữ liệu cập nhật
     * @param int $id ID thiết bị cần sửa
     *
     * [5. GIÁ TRỊ TRẢ VỀ]
     * @return \Illuminate\Http\JsonResponse Model thiết bị đã cập nhật
     *
     * [6. VÍ DỤ SỬ DỤNG]
     * @example
     * PUT /api/v1/equipments/12 Payload: { "position_note": "Bàn làm việc", "note": "Mới lau chùi" }
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
     * [1. MỤC ĐÍCH]
     * Xóa một thiết bị khỏi hệ thống.
     *
     * [2. LUỒNG XỬ LÝ (FLOW)]
     * 1. Tiếp nhận ID thiết bị từ route parameter.
     * 2. Gọi `$this->service->delete($id)` để xóa bản ghi và phát broadcast xóa.
     * 3. Trả về JSON Response 200 thông báo đã xóa thành công.
     *
     * [3. NGUỒN GỌI (CALLERS)]
     * - Route: DELETE /api/v1/equipments/{id}
     * - Frontend: Nút "Xóa" trên danh sách thiết bị
     *
     * [4. THAM SỐ (PARAMETERS)]
     * @param int $id ID thiết bị cần xóa
     *
     * [5. GIÁ TRỊ TRẢ VỀ]
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        $this->service->delete($id);
        return $this->success(null, 'Đã xóa thiết bị thành công');
    }

    /**
     * [1. MỤC ĐÍCH]
     * Thay đổi nhanh trạng thái thiết bị (tốt, hỏng, bảo trì, thu hồi về kho...).
     *
     * [2. LUỒNG XỬ LÝ (FLOW)]
     * 1. Validate trạng thái (`status`) và lý do (`note`) qua `ChangeEquipmentStatusRequest`.
     * 2. Gọi `$this->service->changeStatus()` để đổi trạng thái, đồng bộ phiếu bảo trì và giải phóng phòng nếu về kho.
     * 3. Trả về dữ liệu thiết bị mới thông qua `EquipmentResource`.
     *
     * [3. NGUỒN GỌI (CALLERS)]
     * - Route: PATCH /api/v1/equipments/{id}/status
     * - Frontend: Menu đổi trạng thái nhanh tại danh sách thiết bị
     *
     * [4. THAM SỐ (PARAMETERS)]
     * @param \App\Modules\Equipment\Requests\ChangeEquipmentStatusRequest $request Trạng thái mới & ghi chú
     * @param int $id ID thiết bị
     *
     * [5. GIÁ TRỊ TRẢ VỀ]
     * @return \Illuminate\Http\JsonResponse
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
     * [1. MỤC ĐÍCH]
     * Cập nhật trạng thái cho đồng thời nhiều thiết bị được chọn.
     *
     * [2. LUỒNG XỬ LÝ (FLOW)]
     * 1. Validate mảng `ids`, giá trị `status` và `note` qua `BulkUpdateStatusEquipmentRequest`.
     * 2. Chuyển tiếp cho `$this->service->bulkUpdateStatus()` thực hiện trong 1 Transaction.
     * 3. Trả về số lượng thiết bị đã được cập nhật thành công.
     *
     * [3. NGUỒN GỌI (CALLERS)]
     * - Route: PATCH /api/v1/equipments/bulk-status
     * - Frontend: Thanh tác vụ hàng loạt khi tích chọn nhiều checkbox ở bảng thiết bị
     *
     * [4. THAM SỐ (PARAMETERS)]
     * @param \App\Modules\Equipment\Requests\BulkUpdateStatusEquipmentRequest $request Chứa ids, status, note
     *
     * [5. GIÁ TRỊ TRẢ VỀ]
     * @return \Illuminate\Http\JsonResponse Số lượng bản ghi cập nhật
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
     * [1. MỤC ĐÍCH]
     * Xóa hàng loạt các thiết bị được chọn.
     *
     * [2. LUỒNG XỬ LÝ (FLOW)]
     * 1. Validate mảng danh sách `ids` phải tồn tại trong bảng `room_equipments`.
     * 2. Gọi `$this->service->bulkDestroy($ids)` để xóa trong DB và phát broadcast đồng bộ.
     * 3. Trả về thông báo thành công kèm số lượng đã xóa.
     *
     * [3. NGUỒN GỌI (CALLERS)]
     * - Route: POST /api/v1/equipments/bulk-destroy
     * - Frontend: Nút "Xóa đã chọn" trên thanh công cụ bảng thiết bị
     *
     * [4. THAM SỐ (PARAMETERS)]
     * @param \Illuminate\Http\Request $request Chứa mảng `ids`
     *
     * [5. GIÁ TRỊ TRẢ VỀ]
     * @return \Illuminate\Http\JsonResponse
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
     * [1. MỤC ĐÍCH]
     * Điều chuyển thiết bị: Gán 1 hoặc nhiều thiết bị vào phòng cụ thể, hoặc thu hồi thiết bị về kho.
     *
     * [2. LUỒNG XỬ LÝ (FLOW)]
     * 1. Kiểm tra request:
     *    - Nếu có mảng `equipment_ids`: Xử lý gán hàng loạt (hỗ trợ tính năng "Bù thiết bị từ kho").
     *      Lặp qua từng ID và gọi `$this->service->assignToRoom()`.
     *    - Nếu chỉ có `equipment_id` đơn lẻ: Gọi trực tiếp `$this->service->assignToRoom()`.
     * 2. Tự động cập nhật `status = in_stock` (nếu về kho) hoặc `status = good` (nếu gán vào phòng).
     * 3. Trả về Resource thiết bị hoặc danh sách thiết bị kèm thông báo kết quả.
     *
     * [3. NGUỒN GỌI (CALLERS)]
     * - Route: POST /api/v1/equipments/assign-room
     * - Frontend: `AssignRoomModal.vue`, `RoomAssignWarehouseModal.vue`
     *
     * [4. THAM SỐ (PARAMETERS)]
     * @param \App\Modules\Equipment\Requests\AssignRoomEquipmentRequest $request Chứa equipment_id hoặc equipment_ids, room_id, position_note
     *
     * [5. GIÁ TRỊ TRẢ VỀ]
     * @return \Illuminate\Http\JsonResponse
     */
    public function assignToRoom(AssignRoomEquipmentRequest $request): JsonResponse
    {
        $equipmentIds = $request->input('equipment_ids');
        if (!empty($equipmentIds) && is_array($equipmentIds)) {
            $assignedItems = [];
            foreach ($equipmentIds as $eqId) {
                $assignedItems[] = $this->service->assignToRoom(
                    (int) $eqId,
                    $request->validated('room_id'),
                    $request->validated('position_note')
                );
            }
            $count = count($assignedItems);
            $targetText = $request->validated('room_id') ? "vào phòng" : "về kho";
            return $this->success([
                'assigned_count' => $count,
                'items' => EquipmentResource::collection($assignedItems),
            ], "Đã gán thành công {$count} thiết bị {$targetText}.");
        }

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
     * [1. MỤC ĐÍCH]
     * Kiểm tra đối soát định mức phòng đích trước khi chuyển thiết bị tới (báo trước thừa/thiếu).
     *
     * [2. LUỒNG XỬ LÝ (FLOW)]
     * 1. Lấy `target_room_id` từ query parameter.
     * 2. Gọi `$this->service->checkAssignTarget($id, $targetRoomId)`.
     * 3. Trả về phân tích: định mức hiện tại, số lượng sau khi chuyển, cảnh báo thừa/thiếu, tác động tới phòng cũ.
     *
     * [3. NGUỒN GỌI (CALLERS)]
     * - Route: GET /api/v1/equipments/{id}/check-assign-target
     * - Frontend: Modal điều chuyển thiết bị `AssignRoomModal.vue`
     *
     * [4. THAM SỐ (PARAMETERS)]
     * @param \Illuminate\Http\Request $request Chứa `target_room_id`
     * @param int $id ID thiết bị đang xét
     *
     * [5. GIÁ TRỊ TRẢ VỀ]
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkAssignTarget(Request $request, int $id): JsonResponse
    {
        $targetRoomId = $request->query('target_room_id') ? (int) $request->query('target_room_id') : null;
        $result = $this->service->checkAssignTarget($id, $targetRoomId);

        return $this->success($result, 'Kiểm tra định mức phòng thành công');
    }

    /**
     * [1. MỤC ĐÍCH]
     * Báo hỏng thiết bị, tự động tạo phiếu sửa chữa bảo trì và lưu ảnh chụp hiện trường.
     *
     * [2. LUỒNG XỬ LÝ (FLOW)]
     * 1. Lấy file ảnh `broken_photo` và mô tả sự cố `issue_description`.
     * 2. Gọi `$this->service->reportBroken()` tạo log bảo trì, đổi status sang 'broken' và broadcast sự kiện.
     * 3. Trả về thông tin thiết bị sau khi tiếp nhận.
     *
     * [3. NGUỒN GỌI (CALLERS)]
     * - Route: POST /api/v1/equipments/{id}/report-broken
     * - Frontend: `ReportBrokenModal.vue`
     *
     * [4. THAM SỐ (PARAMETERS)]
     * @param \App\Modules\Equipment\Requests\ReportBrokenRequest $request
     * @param int $id ID thiết bị báo hỏng
     *
     * [5. GIÁ TRỊ TRẢ VỀ]
     * @return \Illuminate\Http\JsonResponse
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
     * [1. MỤC ĐÍCH]
     * Cập nhật tiến độ xử lý phiếu bảo trì (tiếp nhận, hoàn thành, không sửa được) và ghi nhận chi phí.
     *
     * [2. LUỒNG XỬ LÝ (FLOW)]
     * 1. Validate trạng thái phiếu ('pending', 'in_progress', 'completed', 'unfixable') và chi phí (`cost`).
     * 2. Chuyển cho `$this->service->updateMaintenanceLog($logId, $validated)`.
     * 3. Trả về phiếu bảo trì sau khi cập nhật.
     *
     * [3. NGUỒN GỌI (CALLERS)]
     * - Route: PATCH /api/v1/equipments/maintenance-logs/{logId}
     * - Frontend: Form cập nhật phiếu sửa chữa thiết bị
     *
     * [4. THAM SỐ (PARAMETERS)]
     * @param \Illuminate\Http\Request $request
     * @param int $logId ID phiếu bảo trì
     *
     * [5. GIÁ TRỊ TRẢ VỀ]
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateMaintenanceLog(Request $request, int $logId): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'nullable|string|in:pending,in_progress,completed,unfixable',
            'cost' => 'nullable|numeric|min:0',
            'issue_description' => 'nullable|string|max:1000',
        ]);

        $log = $this->service->updateMaintenanceLog($logId, $validated);

        return $this->success($log, 'Cập nhật tiến độ bảo trì thành công');
    }

    /**
     * [1. MỤC ĐÍCH]
     * Tải thêm hình ảnh bổ sung cho thiết bị.
     *
     * [2. LUỒNG XỬ LÝ (FLOW)]
     * 1. Kiểm tra định dạng file (ảnh dung lượng <= 10MB).
     * 2. Xác định collection lưu trữ ('equipment_photos' hoặc 'broken_photos').
     * 3. Gọi `$this->service->uploadPhoto()` để gắn ảnh vào thiết bị.
     * 4. Trả về thông tin thiết bị kèm danh sách media mới.
     *
     * [3. NGUỒN GỌI (CALLERS)]
     * - Route: POST /api/v1/equipments/{id}/photos
     * - Frontend: Component Upload ảnh tại chi tiết thiết bị
     *
     * [4. THAM SỐ (PARAMETERS)]
     * @param \Illuminate\Http\Request $request
     * @param int $id ID thiết bị
     *
     * [5. GIÁ TRỊ TRẢ VỀ]
     * @return \Illuminate\Http\JsonResponse
     */
    public function uploadPhoto(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'file' => 'nullable|image|max:10240',
            'photo' => 'nullable|image|max:10240',
            'collection' => 'nullable|string|in:equipment_photos,broken_photos',
        ]);

        $file = $request->file('file') ?? $request->file('photo');
        if (! $file) {
            return $this->error('Vui lòng chọn tệp hình ảnh để tải lên.', 422);
        }

        $item = $this->service->uploadPhoto(
            $id,
            $file,
            $request->input('collection', 'equipment_photos')
        );

        return $this->successResource(new EquipmentResource($item), 'Tải ảnh thiết bị thành công');
    }

    /**
     * [1. MỤC ĐÍCH]
     * Xóa 1 ảnh của thiết bị theo ID ảnh.
     *
     * [2. LUỒNG XỬ LÝ (FLOW)]
     * 1. Tiếp nhận ID thiết bị và media ID từ route parameter.
     * 2. Gọi `$this->service->deletePhoto($id, $mediaId)` để xóa file và bản ghi media.
     * 3. Trả về thông tin thiết bị đã cập nhật.
     *
     * [3. NGUỒN GỌI (CALLERS)]
     * - Route: DELETE /api/v1/equipments/{id}/photos/{mediaId}
     * - Frontend: Nút xóa ảnh trong gallery thiết bị
     *
     * [4. THAM SỐ (PARAMETERS)]
     * @param int $id ID thiết bị
     * @param int $mediaId ID ảnh cần xóa
     *
     * [5. GIÁ TRỊ TRẢ VỀ]
     * @return \Illuminate\Http\JsonResponse
     */
    public function deletePhoto(int $id, int $mediaId): JsonResponse
    {
        $item = $this->service->deletePhoto($id, $mediaId);
        return $this->successResource(new EquipmentResource($item), 'Xóa ảnh thiết bị thành công');
    }

    /**
     * [1. MỤC ĐÍCH]
     * Báo cáo thống kê toàn bộ thiết bị khách sạn (tổng số, tình trạng hoạt động, phân theo block, phân theo loại).
     *
     * [2. LUỒNG XỬ LÝ (FLOW)]
     * 1. Xác định tổ chức/cơ sở (`organization_id`) và bộ lọc Block (nếu có).
     * 2. Gọi `$this->service->getHotelStats($orgId, $block)`.
     * 3. Trả về dữ liệu thống kê tổng hợp (tự động cache 60s).
     *
     * [3. NGUỒN GỌI (CALLERS)]
     * - Route: GET /api/v1/equipments/stats
     * - Frontend: Header thống kê `EquipmentStatHeader.vue`, `MapStatHeader.vue`
     *
     * [4. THAM SỐ (PARAMETERS)]
     * @param \Illuminate\Http\Request $request Chứa `block`
     *
     * [5. GIÁ TRỊ TRẢ VỀ]
     * @return \Illuminate\Http\JsonResponse
     */
    public function stats(Request $request): JsonResponse
    {
        $block = $request->query('block');
        $orgId = (int) ($request->header('X-Organization-Id') ?: ($request->user()?->organization_id ?: (function_exists('getPermissionsTeamId') ? getPermissionsTeamId() : null)));
        $stats = $this->service->getHotelStats($orgId, $block);
        return $this->success($stats, 'Lấy báo cáo thống kê thiết bị thành công');
    }

    /**
     * [1. MỤC ĐÍCH]
     * Xuất danh sách thiết bị ra file Excel (hỗ trợ cả xuất dữ liệu hiện có hoặc xuất file mẫu import).
     *
     * [2. LUỒNG XỬ LÝ (FLOW)]
     * 1. Sinh tên file đính kèm thời gian: `danh-sach-thiet-bi-{Y-m-d-His}.xlsx`.
     * 2. Xác định `organization_id` và tham số `template`.
     * 3. Sử dụng Maatwebsite Excel download `EquipmentExport`.
     *
     * [3. NGUỒN GỌI (CALLERS)]
     * - Route: GET /api/v1/equipments/export
     * - Frontend: Nút "Xuất Excel" trên thanh công cụ
     *
     * [4. THAM SỐ (PARAMETERS)]
     * @param \Illuminate\Http\Request $request
     *
     * [5. GIÁ TRỊ TRẢ VỀ]
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse File Excel nhị phân
     */
    public function export(Request $request)
    {
        $fileName = 'danh-sach-thiet-bi-'.now()->format('Y-m-d-His').'.xlsx';
        $organizationId = (int) ($request->header('X-Organization-Id')
            ?: (function_exists('getPermissionsTeamId') ? getPermissionsTeamId() : 0));
        return Excel::download(
            new EquipmentExport($request->all(), $organizationId ?: null, $request->boolean('template')),
            $fileName
        );
    }

    /**
     * [1. MỤC ĐÍCH]
     * Nhập hàng loạt thiết bị từ file bảng tính Excel vào hệ thống.
     *
     * [2. LUỒNG XỬ LÝ (FLOW)]
     * 1. Validate file Excel thông qua `ImportEquipmentRequest`.
     * 2. Khởi tạo đối tượng `EquipmentImport` với `organization_id`.
     * 3. Thực hiện import qua thư viện Excel, đọc từng dòng và tạo thiết bị.
     * 4. Xóa cache thống kê và phát sự kiện `EquipmentImported`.
     * 5. Trả về kết quả: số dòng thành công, số dòng lỗi và chi tiết lỗi nếu có.
     *
     * [3. NGUỒN GỌI (CALLERS)]
     * - Route: POST /api/v1/equipments/import
     * - Frontend: Modal Import Excel tại trang danh sách thiết bị
     *
     * [4. THAM SỐ (PARAMETERS)]
     * @param \App\Modules\Equipment\Requests\ImportEquipmentRequest $request File upload
     *
     * [5. GIÁ TRỊ TRẢ VỀ]
     * @return \Illuminate\Http\JsonResponse Kết quả import
     */
    public function import(ImportEquipmentRequest $request): JsonResponse
    {
        $organizationId = (int) ($request->header('X-Organization-Id')
            ?: (function_exists('getPermissionsTeamId') ? getPermissionsTeamId() : 0));
        $import = new EquipmentImport($organizationId ?: null);
        Excel::import($import, $request->file('file'));

        $result = $import->getResult();
        $this->service->invalidateCache($organizationId ?: null);
        if ($result['success_count'] > 0 && $organizationId) {
            event(new EquipmentImported($organizationId, $result['success_count']));
        }
        return $this->success($result, "Nhập dữ liệu hoàn tất: Thành công {$result['success_count']}, Thất bại {$result['failed_count']}");
    }

    /**
     * [1. MỤC ĐÍCH]
     * Lấy danh sách toàn bộ các mẫu thiết bị (Equipment Models) cho dropdown chọn mẫu.
     *
     * [2. LUỒNG XỬ LÝ (FLOW)]
     * 1. Kiểm tra cache với key `equipment_models_dropdown_{orgId}` trong vòng 300 giây.
     * 2. Nếu cache miss: truy vấn `EquipmentModel::with('category')`, sắp xếp tên tăng dần.
     * 3. Trả về JSON danh sách mẫu thiết bị.
     *
     * [3. NGUỒN GỌI (CALLERS)]
     * - Route: GET /api/v1/equipments/models
     * - Frontend: Dropdown chọn Model trong form thêm/sửa thiết bị
     *
     * [4. THAM SỐ (PARAMETERS)]
     * @param \Illuminate\Http\Request $request
     *
     * [5. GIÁ TRỊ TRẢ VỀ]
     * @return \Illuminate\Http\JsonResponse
     */
    public function getModels(Request $request): JsonResponse
    {
        $orgId = $request->user()?->organization_id;
        $cacheKey = 'equipment_models_dropdown_' . ($orgId ?: 'all');

        $models = cache()->remember($cacheKey, 300, function () use ($orgId) {
            $query = EquipmentModel::with('category');
            if ($orgId) {
                $query->where(function ($q) use ($orgId) {
                    $q->where('organization_id', $orgId)->orWhereNull('organization_id');
                });
            }
            return $query->orderBy('name', 'asc')->get();
        });

        return $this->success($models, 'Lấy danh sách mẫu thiết bị thành công');
    }

    /**
     * [1. MỤC ĐÍCH]
     * Lấy danh sách phòng theo cấu trúc phân cấp Block - Tầng - Phòng cho bộ chọn Cascader bậc thang.
     *
     * [2. LUỒNG XỬ LÝ (FLOW)]
     * 1. Kiểm tra cache `equipment_room_options_{orgId}` (TTL 300 giây).
     * 2. Nếu cache miss: Truy vấn bảng `rooms`, eager load `roomType:id,name`.
     * 3. Map các trường dữ liệu cần thiết (id, room_number, block, floor, bedroom_count, wifi_mode...).
     * 4. Lưu cache và trả về danh sách tùy chọn phòng.
     *
     * [3. NGUỒN GỌI (CALLERS)]
     * - Route: GET /api/v1/equipments/room-options
     * - Frontend: `equipmentService.getRoomOptions()` phục vụ bộ chọn phòng bậc thang
     *
     * [4. THAM SỐ (PARAMETERS)]
     * @param \Illuminate\Http\Request $request
     *
     * [5. GIÁ TRỊ TRẢ VỀ]
     * @return \Illuminate\Http\JsonResponse
     */
    public function getRoomOptions(Request $request): JsonResponse
    {
        $orgId = $request->header('X-Organization-Id') 
            ?? (function_exists('getPermissionsTeamId') ? getPermissionsTeamId() : null)
            ?? $request->user()?->organization_id;
        $orgId = $orgId ? (int) $orgId : null;
        $cacheKey = 'equipment_room_options_' . ($orgId ?: 'all');

        $rooms = cache()->remember($cacheKey, 300, function () use ($orgId) {
            $query = Room::with('roomType:id,name');
            if ($orgId) {
                $query->where('organization_id', $orgId);
            }
            return $query->select(['id', 'room_number', 'block', 'floor', 'room_type_id', 'bedroom_count', 'living_room_count', 'wifi_mode', 'wifi_router_count', 'shared_wifi_note'])
                ->orderBy('block', 'asc')
                ->orderBy('floor', 'asc')
                ->orderBy('room_number', 'asc')
                ->get()
                ->map(fn ($r) => [
                    'id' => $r->id,
                    'room_number' => (string) $r->room_number,
                    'block' => $r->block ?: 'Chưa phân khu',
                    'floor' => $r->floor ?? 1,
                    'room_type' => $r->roomType?->name ?: 'Tiêu chuẩn',
                    'bedroom_count' => $r->bedroom_count ?? 1,
                    'living_room_count' => $r->living_room_count ?? 0,
                    'wifi_mode' => $r->wifi_mode ?? 'dedicated',
                    'wifi_router_count' => (int) ($r->wifi_router_count ?? 1),
                    'shared_wifi_note' => $r->shared_wifi_note,
                ])
                ->values()
                ->all();
        });

        return $this->success($rooms, 'Lấy danh sách phòng thành công');
    }
}
