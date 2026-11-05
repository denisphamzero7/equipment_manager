<?php

namespace App\Modules\Equipment\Services;

use App\Modules\Equipment\Enums\EquipmentStatusEnum;
use App\Modules\Equipment\Enums\MaintenanceStatusEnum;
use App\Modules\Equipment\Events\EquipmentAssigned;
use App\Modules\Equipment\Events\EquipmentBrokenReported;
use App\Modules\Equipment\Events\EquipmentBulkDeleted;
use App\Modules\Equipment\Events\EquipmentBulkStatusChanged;
use App\Modules\Equipment\Events\EquipmentCreated;
use App\Modules\Equipment\Events\EquipmentDeleted;
use App\Modules\Equipment\Events\EquipmentStatusChanged;
use App\Modules\Equipment\Events\EquipmentUpdated;
use App\Modules\Equipment\Models\EquipmentCategory;
use App\Modules\Equipment\Models\EquipmentMaintenanceLog;
use App\Modules\Equipment\Models\EquipmentModel;
use App\Modules\Equipment\Models\RoomEquipment;
use App\Modules\Equipment\Models\RoomStandardEquipment;
use App\Modules\Motel\Models\Room;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class EquipmentService
{
    /**
     * [1. MỤC ĐÍCH]
     * Phát broadcast sự kiện Reverb/Websocket an toàn mà không làm gián đoạn request.
     *
     * [2. LUỒNG XỬ LÝ (FLOW)]
     * 1. Gọi hàm event($event) của Laravel để dispatch sự kiện broadcast ra websocket channel.
     * 2. Bắt ngoại lệ Throwable (nếu Reverb server tắt, timeout hoặc lỗi kết nối).
     * 3. Ghi log Warning thay vì throw exception để luồng chính vẫn hoàn tất bình thường.
     *
     * [3. NGUỒN GỌI (CALLERS)]
     * - Các phương thức nội bộ trong EquipmentService: create, update, delete, changeStatus, assignToRoom, reportBroken...
     *
     * [4. THAM SỐ (PARAMETERS)]
     * @param object $event Đối tượng Event broadcast của Laravel (ví dụ: EquipmentCreated, EquipmentUpdated)
     *
     * [5. GIÁ TRỊ TRẢ VỀ]
     * @return void
     *
     * [6. VÍ DỤ SỬ DỤNG]
     * @example
     * ```php
     * $this->safeBroadcast(new EquipmentUpdated($item));
     * ```
     */
    protected function safeBroadcast(object $event): void
    {
        try {
            event($event);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Broadcast failed safely: ' . $e->getMessage());
        }
    }

    /**
     * [1. MỤC ĐÍCH]
     * Xóa sạch các tầng cache dữ liệu (thống kê phòng, map view) khi có bất kỳ thay đổi nào về thiết bị.
     *
     * [2. LUỒNG XỬ LÝ (FLOW)]
     * 1. Xác định organization_id của người dùng hiện tại (hoặc lấy mặc định từ cơ sở dữ liệu).
     * 2. Xóa cache thống kê khách sạn theo tổ chức (`hotel_stats_{orgId}_all`) và toàn cục (`hotel_stats_all_all`).
     * 3. Tăng phiên bản version cache sơ đồ Map View (`equipment_map_view_version_{orgId}` và `equipment_map_view_version_all`).
     *    -> Frontend khi thấy version thay đổi sẽ tự động tải lại dữ liệu mới nhất.
     *
     * [3. NGUỒN GỌI (CALLERS)]
     * - Các hàm ghi dữ liệu trong EquipmentService: create, update, delete, bulkDestroy, changeStatus, assignToRoom, reportBroken...
     * - Import Excel thiết bị: EquipmentController::import()
     *
     * [4. THAM SỐ (PARAMETERS)]
     * @param int|null $orgId ID của tổ chức / cơ sở (nếu null sẽ tự động nhận diện từ session/token)
     *
     * [5. GIÁ TRỊ TRẢ VỀ]
     * @return void
     *
     * [6. VÍ DỤ SỬ DỤNG]
     * @example
     * ```php
     * $this->invalidateCache($equipment->organization_id);
     * ```
     */
    public function invalidateCache(?int $orgId = null): void
    {
        $orgId = $orgId ?: (function_exists('getPermissionsTeamId') ? getPermissionsTeamId() : (\App\Modules\Core\Models\Organization::value('id') ?? 1));

        $vStats = (int) cache()->get('hotel_stats_version_' . $orgId, 1);
        cache()->put('hotel_stats_version_' . $orgId, $vStats + 1, 86400 * 30);
        $vStatsAll = (int) cache()->get('hotel_stats_version_all', 1);
        cache()->put('hotel_stats_version_all', $vStatsAll + 1, 86400 * 30);

        cache()->forget('hotel_stats_' . $orgId . '_all');
        cache()->forget('hotel_stats_all_all');

        $v = (int) cache()->get('equipment_map_view_version_' . $orgId, 1);
        cache()->put('equipment_map_view_version_' . $orgId, $v + 1, 86400 * 30);
        $vAll = (int) cache()->get('equipment_map_view_version_all', 1);
        cache()->put('equipment_map_view_version_all', $vAll + 1, 86400 * 30);
    }

    /**
     * [1. MỤC ĐÍCH]
     * Truy vấn danh sách thiết bị có phân trang, hỗ trợ đa dạng bộ lọc và tìm kiếm toàn văn.
     *
     * [2. LUỒNG XỬ LÝ (FLOW)]
     * 1. Khởi tạo query Eloquent từ RoomEquipment, eager load quan hệ: `model.category`, `room`, `media`.
     * 2. Lọc theo trạng thái (`status`: good, broken, maintenance, in_stock, lost...).
     * 3. Lọc theo Block / Tòa nhà:
     *    - Nếu `block === '__stock__'`, lọc thiết bị trong kho (`whereNull('room_id')`).
     *    - Nếu `block` khác, join lọc theo bảng `rooms.block`.
     * 4. Lọc theo tầng (`floor`), số phòng (`room_number`), hoặc phòng cụ thể (`room_id`).
     * 5. Lọc theo danh mục thiết bị (`equipment_category_id`) hoặc model cụ thể (`equipment_model_id`).
     * 6. Tìm kiếm toàn văn (`search`) trên các trường: serial_number, position_note, note, tên model, thương hiệu, số phòng, tên phòng.
     * 7. Áp dụng sắp xếp (`sort_by`, `sort_order`) và phân trang (`per_page` mặc định 15).
     *
     * [3. NGUỒN GỌI (CALLERS)]
     * - API: GET /api/v1/equipments
     * - Controller: App\Modules\Equipment\Controllers\EquipmentController::index()
     * - Frontend Store: `useEquipmentStore::fetchEquipments()`
     *
     * [4. THAM SỐ (PARAMETERS)]
     * @param array $filters Mảng tham số lọc (status, block, floor, room_id, search, per_page, sort_by...)
     *
     * [5. GIÁ TRỊ TRẢ VỀ]
     * @return \Illuminate\Pagination\LengthAwarePaginator Danh sách thiết bị được phân trang
     *
     * [6. VÍ DỤ SỬ DỤNG]
     * @example
     * ```php
     * $paginator = $equipmentService->paginate([
     *     'status' => 'broken',
     *     'block' => 'Tòa A',
     *     'search' => 'Sony 55 inch',
     *     'per_page' => 20
     * ]);
     * ```
     */
    public function paginate(array $filters = []): LengthAwarePaginator
    {
        $query = RoomEquipment::with(['model.category', 'room', 'media']);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['block'])) {
            if ($filters['block'] === '__stock__') {
                $query->whereNull('room_id');
            } else {
                $query->whereHas('room', fn ($q) => $q->where('block', $filters['block']));
            }
        }
        if (isset($filters['floor']) && $filters['floor'] !== '' && $filters['floor'] !== null) {
            $query->whereHas('room', fn ($q) => $q->where('floor', (int) $filters['floor']));
        }
        if (!empty($filters['room_number'])) {
            $query->whereHas('room', fn ($q) => $q->where('room_number', 'like', "%{$filters['room_number']}%"));
        }
        if (!empty($filters['room_id'])) {
            $query->where('room_id', $filters['room_id']);
        }
        if (isset($filters['in_stock']) && $filters['in_stock']) {
            $query->whereNull('room_id');
        }
        if (!empty($filters['equipment_category_id'])) {
            $query->whereHas('model', fn ($q) => $q->where('equipment_category_id', $filters['equipment_category_id']));
        }
        if (!empty($filters['equipment_model_id'])) {
            $query->where('equipment_model_id', $filters['equipment_model_id']);
        }
        if (!empty($filters['search'])) {
            $s = $filters['search'];
            $query->where(function ($q) use ($s) {
                $q->where('serial_number', 'like', "%{$s}%")
                    ->orWhere('position_note', 'like', "%{$s}%")
                    ->orWhere('note', 'like', "%{$s}%")
                    ->orWhereHas('model', fn ($mq) => $mq->where('name', 'like', "%{$s}%")->orWhere('brand', 'like', "%{$s}%"))
                    ->orWhereHas('room', fn ($rq) => $rq->where('room_number', 'like', "%{$s}%")->orWhere('name', 'like', "%{$s}%"));
            });
        }

        $perPage = (int) ($filters['per_page'] ?? $filters['limit'] ?? 15);
        $sortBy = $filters['sort_by'] ?? 'id';
        $sortOrder = strtolower($filters['sort_order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($sortBy, $sortOrder)->paginate($perPage);
    }

    /**
     * [1. MỤC ĐÍCH]
     * Lấy thông tin chi tiết một cá thể thiết bị kèm toàn bộ quan hệ liên quan.
     *
     * [2. LUỒNG XỬ LÝ (FLOW)]
     * 1. Tìm bản ghi RoomEquipment theo ID (sử dụng findOrFail).
     * 2. Eager load các mối quan hệ:
     *    - `model.category`: Thông tin model, thương hiệu, nhóm danh mục thiết bị.
     *    - `room`: Thông tin phòng đang đặt thiết bị (số phòng, tầng, block).
     *    - `maintenanceLogs.reportedByUser`: Lịch sử các phiếu bảo trì kèm thông tin người báo.
     *    - `maintenanceLogs.resolvedByUser`: Thông tin kỹ thuật viên/người xử lý phiếu bảo trì.
     *    - `media`: Danh sách ảnh chụp thực tế (ảnh thiết bị, ảnh hư hỏng).
     * 3. Trả về thực thể RoomEquipment hoặc ném ModelNotFoundException (404) nếu không tìm thấy.
     *
     * [3. NGUỒN GỌI (CALLERS)]
     * - API: GET /api/v1/equipments/{id}
     * - Controller: App\Modules\Equipment\Controllers\EquipmentController::show()
     * - Frontend Store: `useEquipmentStore::fetchEquipment(id)`
     *
     * [4. THAM SỐ (PARAMETERS)]
     * @param int $id ID của thiết bị (cột `room_equipments.id`)
     *
     * [5. GIÁ TRỊ TRẢ VỀ & NGOẠI LỆ]
     * @return \App\Modules\Equipment\Models\RoomEquipment Model thiết bị kèm quan hệ
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException Khi ID không tồn tại trong hệ thống
     *
     * [6. VÍ DỤ SỬ DỤNG]
     * @example
     * ```php
     * $equipment = $equipmentService->find(42);
     * echo $equipment->model->name . ' - Phòng ' . $equipment->room?->room_number;
     * ```
     */
    public function find(int $id): RoomEquipment
    {
        return RoomEquipment::with([
            'model.category',
            'room',
            'maintenanceLogs.reportedByUser',
            'maintenanceLogs.resolvedByUser',
            'media'
        ])->findOrFail($id);
    }

    /**
     * [1. MỤC ĐÍCH]
     * Tạo mới một cá thể thiết bị vào hệ thống (gắn vào phòng hoặc đưa vào kho dự phòng).
     *
     * [2. LUỒNG XỬ LÝ (FLOW)]
     * 1. Bắt đầu Database Transaction.
     * 2. Tách file ảnh (`photo`) ra khỏi mảng dữ liệu.
     * 3. Xử lý trạng thái & thời gian lắp đặt:
     *    - Nếu có `room_id`: thiết lập `installed_at = now()` (nếu chưa có).
     *    - Nếu không có `room_id`: mặc định thiết bị ở kho (`status = in_stock`).
     * 4. Gán `organization_id` theo tổ chức hiện tại nếu chưa được cung cấp.
     * 5. Tạo mới bản ghi RoomEquipment.
     * 6. Nếu có file ảnh, lưu vào Spatie Media Library collection `equipment_photos`.
     * 7. Eager load thông tin `model.category` và `room`.
     * 8. Phát sự kiện broadcast `EquipmentCreated` kèm `$clientRequestId` (để tránh xử lý trùng ở client gọi).
     * 9. Xóa cache dữ liệu (`invalidateCache`) và trả về thiết bị vừa tạo.
     *
     * [3. NGUỒN GỌI (CALLERS)]
     * - API: POST /api/v1/equipments
     * - Controller: App\Modules\Equipment\Controllers\EquipmentController::store()
     * - Frontend Store: `useEquipmentStore::createEquipment()`
     *
     * [4. THAM SỐ (PARAMETERS)]
     * @param array $data Dữ liệu thiết bị (equipment_model_id, serial_number, room_id, status, photo...)
     * @param string|null $clientRequestId UUID định danh request từ client để chống trùng lặp real-time
     *
     * [5. GIÁ TRỊ TRẢ VỀ & NGOẠI LỆ]
     * @return \App\Modules\Equipment\Models\RoomEquipment Model thiết bị vừa được tạo
     * @throws \Throwable Nếu lỗi kết nối DB hoặc lỗi ghi file ảnh
     *
     * [6. VÍ DỤ SỬ DỤNG]
     * @example
     * ```php
     * $item = $equipmentService->create([
     *     'equipment_model_id' => 5,
     *     'serial_number' => 'SN-TV-2026-001',
     *     'room_id' => 12,
     *     'status' => 'good'
     * ]);
     * ```
     */
    public function create(array $data, ?string $clientRequestId = null): RoomEquipment
    {
        $item = DB::transaction(function () use ($data) {
            $photo = $data['photo'] ?? null;
            unset($data['photo']);

            if (!empty($data['room_id'])) {
                $data['installed_at'] = $data['installed_at'] ?? now();
            } else {
                $data['status'] = $data['status'] ?? EquipmentStatusEnum::InStock->value;
            }

            if (empty($data['organization_id']) && function_exists('getPermissionsTeamId')) {
                $data['organization_id'] = getPermissionsTeamId();
            }

            $item = RoomEquipment::create($data);

            if ($photo) {
                $item->addMedia($photo)->toMediaCollection('equipment_photos');
            }

            return $item->load(['model.category', 'room']);
        });

        // Phát Broadcast Real-time
        $this->safeBroadcast(new EquipmentCreated($item, $clientRequestId));
        $this->invalidateCache($item->organization_id);

        return $item;
    }

    /**
     * [1. MỤC ĐÍCH]
     * Cập nhật thông tin chi tiết, trạng thái, phòng hoặc ảnh của thiết bị.
     *
     * [2. LUỒNG XỬ LÝ (FLOW)]
     * 1. Bắt đầu Database Transaction và tìm RoomEquipment theo ID.
     * 2. Lưu lại trạng thái cũ (`$oldStatus`) để phát hiện việc thay đổi trạng thái.
     * 3. Tách file ảnh (`photo`) ra khỏi payload và gọi `$item->update($data)`.
     * 4. Nếu có cập nhật trạng thái mới (`status`), gọi `syncOpenMaintenanceLogs()` để tự động cập nhật hoặc mở phiếu sự cố.
     * 5. Nếu có tải kèm ảnh mới, lưu vào media collection `equipment_photos`.
     * 6. Phát sự kiện Broadcast:
     *    - Nếu trạng thái đổi: phát `EquipmentStatusChanged` kèm `$oldStatus`.
     *    - Nếu chỉ sửa thông tin: phát `EquipmentUpdated`.
     * 7. Xóa cache thống kê và sơ đồ phòng (`invalidateCache`).
     *
     * [3. NGUỒN GỌI (CALLERS)]
     * - API: PUT /api/v1/equipments/{id}
     * - Controller: App\Modules\Equipment\Controllers\EquipmentController::update()
     * - Frontend Store: `useEquipmentStore::updateEquipment()`
     *
     * [4. THAM SỐ (PARAMETERS)]
     * @param int $id ID thiết bị cần sửa
     * @param array $data Dữ liệu cần cập nhật (serial_number, status, room_id, note, photo...)
     *
     * [5. GIÁ TRỊ TRẢ VỀ & NGOẠI LỆ]
     * @return \App\Modules\Equipment\Models\RoomEquipment Model thiết bị sau khi cập nhật
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException Khi không tìm thấy ID
     *
     * [6. VÍ DỤ SỬ DỤNG]
     * @example
     * ```php
     * $updated = $equipmentService->update(15, [
     *     'position_note' => 'Gắn góc trái phòng ngủ 1',
     *     'status' => 'good'
     * ]);
     * ```
     */
    public function update(int $id, array $data): RoomEquipment
    {
        $oldStatus = null;
        $statusChanged = false;

        $item = DB::transaction(function () use ($id, $data, &$oldStatus, &$statusChanged) {
            $item = RoomEquipment::findOrFail($id);
            $photo = $data['photo'] ?? null;
            unset($data['photo']);

            $oldStatus = $item->status instanceof \BackedEnum ? $item->status->value : (string) $item->status;
            $newStatus = isset($data['status']) ? ($data['status'] instanceof \BackedEnum ? $data['status']->value : (string) $data['status']) : null;
            if ($newStatus && $newStatus !== $oldStatus) {
                $statusChanged = true;
            }

            $item->update($data);

            if (!empty($data['status'])) {
                $statusVal = $data['status'] instanceof \BackedEnum ? $data['status']->value : (string) $data['status'];
                $this->syncOpenMaintenanceLogs($item, $statusVal);
            }

            if ($photo) {
                $item->addMedia($photo)->toMediaCollection('equipment_photos');
            }

            return $item->load(['model.category', 'room']);
        });

        // Phát Broadcast Real-time
        if ($statusChanged) {
            $this->safeBroadcast(new EquipmentStatusChanged($item, $oldStatus));
        } else {
            $this->safeBroadcast(new EquipmentUpdated($item));
        }
        $this->invalidateCache($item->organization_id);

        return $item;
    }

    /**
     * [1. MỤC ĐÍCH]
     * Xóa 1 thiết bị khỏi hệ thống (soft delete hoặc hard delete theo cấu hình Model).
     *
     * [2. LUỒNG XỬ LÝ (FLOW)]
     * 1. Tìm RoomEquipment theo ID.
     * 2. Lưu lại `room_id` và `organization_id` trước khi xóa.
     * 3. Thực hiện xóa bản ghi ($item->delete()).
     * 4. Nếu xóa thành công:
     *    - Phát sự kiện broadcast `EquipmentDeleted` để client xóa khỏi danh sách ngay lập tức.
     *    - Xóa cache thống kê khách sạn (`invalidateCache`).
     * 5. Trả về true nếu xóa thành công.
     *
     * [3. NGUỒN GỌI (CALLERS)]
     * - API: DELETE /api/v1/equipments/{id}
     * - Controller: App\Modules\Equipment\Controllers\EquipmentController::destroy()
     * - Frontend Store: `useEquipmentStore::deleteEquipment()`
     *
     * [4. THAM SỐ (PARAMETERS)]
     * @param int $id ID thiết bị cần xóa
     *
     * [5. GIÁ TRỊ TRẢ VỀ & NGOẠI LỆ]
     * @return bool True nếu xóa thành công
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException Nếu không tìm thấy thiết bị
     *
     * [6. VÍ DỤ SỬ DỤNG]
     * @example
     * ```php
     * $equipmentService->delete(25);
     * ```
     */
    public function delete(int $id): bool
    {
        $item = RoomEquipment::findOrFail($id);
        $roomId = $item->room_id;
        $orgId = $item->organization_id;

        $deleted = (bool) $item->delete();

        if ($deleted) {
            // Phát Broadcast Real-time
            $this->safeBroadcast(new EquipmentDeleted($id, $roomId, $orgId));
            $this->invalidateCache($orgId);
        }

        return $deleted;
    }

    /**
     * [1. MỤC ĐÍCH]
     * Xóa đồng thời nhiều thiết bị theo danh sách ID.
     *
     * [2. LUỒNG XỬ LÝ (FLOW)]
     * 1. Lấy tổ chức hiện tại (`organization_id`).
     * 2. Bắt đầu Database Transaction và xóa hàng loạt bằng `whereIn('id', $ids)->delete()`.
     * 3. Phát broadcast `EquipmentBulkDeleted` kèm danh sách ID để các client đồng bộ UI.
     * 4. Xóa cache thống kê (`invalidateCache`).
     * 5. Trả về số lượng bản ghi đã xóa.
     *
     * [3. NGUỒN GỌI (CALLERS)]
     * - API: POST /api/v1/equipments/bulk-destroy
     * - Controller: App\Modules\Equipment\Controllers\EquipmentController::bulkDestroy()
     * - Frontend Store: `useEquipmentStore::bulkDestroy()`
     *
     * [4. THAM SỐ (PARAMETERS)]
     * @param array<int> $ids Mảng danh sách ID thiết bị cần xóa
     *
     * [5. GIÁ TRỊ TRẢ VỀ]
     * @return int Số lượng thiết bị đã xóa
     *
     * [6. VÍ DỤ SỬ DỤNG]
     * @example
     * ```php
     * $deletedCount = $equipmentService->bulkDestroy([10, 11, 12]);
     * ```
     */
    public function bulkDestroy(array $ids): int
    {
        $orgId = (int) (function_exists('getPermissionsTeamId') ? getPermissionsTeamId() : 1);
        $count = DB::transaction(function () use ($ids) {
            return RoomEquipment::whereIn('id', $ids)->delete();
        });

        $this->safeBroadcast(new EquipmentBulkDeleted($ids, $orgId));
        $this->invalidateCache($orgId);

        return $count;
    }

    /**
     * [1. MỤC ĐÍCH]
     * Thay đổi nhanh trạng thái của 1 thiết bị (tốt, hư hỏng, đang bảo trì, trong kho, thanh lý...).
     *
     * [2. LUỒNG XỬ LÝ (FLOW)]
     * 1. Tìm RoomEquipment theo ID và lưu lại `$oldStatus`.
     * 2. Chuyển đổi trạng thái sang enum `EquipmentStatusEnum`.
     * 3. Nếu chuyển về trạng thái 'in_stock' (kho): tự động giải phóng khỏi phòng (`room_id = null`).
     * 4. Cập nhật ghi chú nếu có.
     * 5. Gọi `syncOpenMaintenanceLogs()` để tự động cập nhật hoặc đóng/mở phiếu bảo trì tương ứng.
     * 6. Phát broadcast `EquipmentStatusChanged` và xóa cache.
     * 7. Trả về RoomEquipment với thông tin danh mục và phòng mới nhất.
     *
     * [3. NGUỒN GỌI (CALLERS)]
     * - API: PATCH /api/v1/equipments/{id}/status
     * - Controller: App\Modules\Equipment\Controllers\EquipmentController::changeStatus()
     * - Frontend Store: `useEquipmentStore::changeStatus()`
     *
     * [4. THAM SỐ (PARAMETERS)]
     * @param int $id ID thiết bị
     * @param string $status Mã trạng thái mới ('good', 'broken', 'maintenance', 'in_stock', 'lost', 'disposed')
     * @param string|null $note Ghi chú lý do chuyển trạng thái
     *
     * [5. GIÁ TRỊ TRẢ VỀ & NGOẠI LỆ]
     * @return \App\Modules\Equipment\Models\RoomEquipment Model sau khi đổi trạng thái
     * @throws \ValueError Nếu mã trạng thái không hợp lệ theo enum
     *
     * [6. VÍ DỤ SỬ DỤNG]
     * @example
     * ```php
     * $item = $equipmentService->changeStatus(12, 'maintenance', 'Kỹ thuật viên đang kiểm tra màn hình');
     * ```
     */
    public function changeStatus(int $id, string $status, ?string $note = null): RoomEquipment
    {
        $oldStatus = null;
        $item = DB::transaction(function () use ($id, $status, $note, &$oldStatus) {
            $item = RoomEquipment::findOrFail($id);
            $oldStatus = $item->status instanceof \BackedEnum ? $item->status->value : (string) $item->status;
            $item->status = EquipmentStatusEnum::from($status);
            if ($note) {
                $item->note = $note;
            }
            if ($status === EquipmentStatusEnum::InStock->value) {
                $item->room_id = null;
            }
            $item->save();

            $this->syncOpenMaintenanceLogs($item, $status);

            return $item->load(['model.category', 'room']);
        });

        // Phát Broadcast Real-time
        $this->safeBroadcast(new EquipmentStatusChanged($item, $oldStatus));
        $this->invalidateCache($item->organization_id);

        return $item;
    }

    /**
     * [1. MỤC ĐÍCH]
     * Cập nhật trạng thái hàng loạt cho nhiều thiết bị cùng lúc.
     *
     * [2. LUỒNG XỬ LÝ (FLOW)]
     * 1. Bắt đầu Database Transaction.
     * 2. Nếu chuyển về trạng thái 'in_stock', tự động set `room_id = null` cho toàn bộ danh sách.
     * 3. Thực hiện câu lệnh bulk update `RoomEquipment::whereIn('id', $ids)->update(...)`.
     * 4. Đồng bộ phiếu bảo trì:
     *    - Nếu trạng thái là 'good': tự động hoàn tất các phiếu đang chờ/đang sửa (`status = completed`, `resolved_at = now()`).
     *    - Nếu là 'broken' hoặc 'maintenance': duyệt qua từng thiết bị để gọi `syncOpenMaintenanceLogs()`.
     * 5. Phát broadcast `EquipmentBulkStatusChanged` và xóa cache.
     * 6. Trả về số lượng bản ghi bị tác động.
     *
     * [3. NGUỒN GỌI (CALLERS)]
     * - API: PATCH /api/v1/equipments/bulk-status
     * - Controller: App\Modules\Equipment\Controllers\EquipmentController::bulkUpdateStatus()
     * - Frontend Store: `useEquipmentStore::bulkUpdateStatus()`
     *
     * [4. THAM SỐ (PARAMETERS)]
     * @param array<int> $ids Mảng ID thiết bị cần cập nhật
     * @param string $status Trạng thái mới
     * @param string|null $note Ghi chú chung
     *
     * [5. GIÁ TRỊ TRẢ VỀ]
     * @return int Số lượng bản ghi đã cập nhật
     *
     * [6. VÍ DỤ SỬ DỤNG]
     * @example
     * ```php
     * $count = $equipmentService->bulkUpdateStatus([1, 2, 3], 'good', 'Đã bảo dưỡng định kỳ');
     * ```
     */
    public function bulkUpdateStatus(array $ids, string $status, ?string $note = null): int
    {
        $count = DB::transaction(function () use ($ids, $status, $note) {
            $updateData = ['status' => $status];
            if ($note) {
                $updateData['note'] = $note;
            }
            if ($status === EquipmentStatusEnum::InStock->value) {
                $updateData['room_id'] = null;
            }

            $affected = RoomEquipment::whereIn('id', $ids)->update($updateData);

            if ($status === EquipmentStatusEnum::Good->value) {
                EquipmentMaintenanceLog::whereIn('room_equipment_id', $ids)
                    ->whereIn('status', [MaintenanceStatusEnum::Pending->value, MaintenanceStatusEnum::InProgress->value])
                    ->update([
                        'status' => MaintenanceStatusEnum::Completed->value,
                        'resolved_at' => now(),
                        'resolved_by' => auth()->id(),
                    ]);
            } elseif (in_array($status, [EquipmentStatusEnum::Broken->value, EquipmentStatusEnum::Maintenance->value, EquipmentStatusEnum::Disposed->value], true)) {
                $equipments = RoomEquipment::whereIn('id', $ids)->get();
                foreach ($equipments as $eq) {
                    $this->syncOpenMaintenanceLogs($eq, $status);
                }
            }

            return $affected;
        });

        $orgId = (int) (function_exists('getPermissionsTeamId') ? getPermissionsTeamId() : 1);
        $this->safeBroadcast(new EquipmentBulkStatusChanged($ids, $status, $orgId));
        $this->invalidateCache($orgId);

        return $count;
    }

    /**
     * [1. MỤC ĐÍCH]
     * Điều chuyển thiết bị vào một phòng cụ thể hoặc thu hồi thiết bị về kho dự phòng chung.
     *
     * [2. LUỒNG XỬ LÝ (FLOW)]
     * 1. Bắt đầu Database Transaction.
     * 2. Tìm RoomEquipment theo `$equipmentId` và lưu lại phòng cũ (`$fromRoomId`).
     * 3. Xử lý logic gán:
     *    - Nếu có `$roomId`: Cập nhật `room_id = $roomId`, `installed_at = now()`.
     *      Nếu thiết bị đang ở trạng thái 'in_stock', tự động đổi thành 'good'.
     *    - Nếu `$roomId == null`: Thu hồi về kho: set `room_id = null`, `position_note = null`,
     *      cập nhật ghi chú và đổi trạng thái sang 'in_stock'.
     * 4. Lưu bản ghi và gọi `syncOpenMaintenanceLogs()` để đảm bảo trạng thái phiếu bảo trì đồng bộ.
     * 5. Eager load quan hệ `model.category` và `room`.
     * 6. Phát sự kiện broadcast `EquipmentAssigned` kèm `$fromRoomId` (để FE cập nhật cả phòng cũ lẫn phòng mới).
     * 7. Xóa cache thống kê & map view (`invalidateCache`).
     *
     * [3. NGUỒN GỌI (CALLERS)]
     * - API: POST /api/v1/equipments/assign-room
     * - Controller: App\Modules\Equipment\Controllers\EquipmentController::assignToRoom()
     * - UI Trigger: Modal `AssignRoomModal.vue` hoặc `RoomAssignWarehouseModal.vue`
     *
     * [4. THAM SỐ (PARAMETERS)]
     * @param int $equipmentId ID của thiết bị cần điều chuyển
     * @param int|null $roomId ID phòng đích (nếu null là thu hồi về kho)
     * @param string|null $positionNote Ghi chú vị trí lắp đặt trong phòng (ví dụ: "Treo tường đối diện giường")
     *
     * [5. GIÁ TRỊ TRẢ VỀ & NGOẠI LỆ]
     * @return \App\Modules\Equipment\Models\RoomEquipment Model thiết bị sau khi điều chuyển
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException Khi không tìm thấy thiết bị
     *
     * [6. VÍ DỤ SỬ DỤNG]
     * @example
     * ```php
     * // Gán vào phòng 101
     * $item = $equipmentService->assignToRoom(42, 101, 'Kệ tủ phòng khách');
     *
     * // Thu hồi về kho
     * $item = $equipmentService->assignToRoom(42, null, 'Thu hồi bảo dưỡng');
     * ```
     */
    public function assignToRoom(int $equipmentId, ?int $roomId, ?string $positionNote = null): RoomEquipment
    {
        $fromRoomId = null;
        $item = DB::transaction(function () use ($equipmentId, $roomId, $positionNote, &$fromRoomId) {
            $item = RoomEquipment::findOrFail($equipmentId);
            $fromRoomId = $item->room_id;

            if ($roomId) {
                $item->room_id = $roomId;
                $item->position_note = $positionNote ?? $item->position_note;
                $item->installed_at = now();
                if ($item->status === EquipmentStatusEnum::InStock) {
                    $item->status = EquipmentStatusEnum::Good;
                }
            } else {
                // Thu hồi về kho
                $item->room_id = null;
                $item->position_note = null;
                if ($positionNote) {
                    $item->note = $positionNote;
                }
                $item->status = EquipmentStatusEnum::InStock;
            }

            $item->save();

            $statusVal = $item->status instanceof \BackedEnum ? $item->status->value : (string) $item->status;
            $this->syncOpenMaintenanceLogs($item, $statusVal);

            return $item->load(['model.category', 'room']);
        });

        // Phát Broadcast Real-time
        $this->safeBroadcast(new EquipmentAssigned($item, $fromRoomId));
        $this->invalidateCache($item->organization_id);

        return $item;
    }

    /**
     * [1. MỤC ĐÍCH]
     * Kiểm tra đối soát định mức chuẩn của phòng đích và cảnh báo trước khi thực hiện điều chuyển thiết bị.
     *
     * [2. LUỒNG XỬ LÝ (FLOW)]
     * 1. Tìm RoomEquipment theo ID kèm model và category.
     * 2. Phân loại danh mục: Nhóm thiết bị động (tv, box, remote, wifi, phone) hay nhóm định mức tĩnh.
     * 3. Nhánh 1: Nếu đích đến là KHO (`$targetRoomId == null`):
     *    - Đánh giá tác động đến phòng hiện tại (phòng nguồn): Nếu thu hồi thiết bị, phòng có bị thiếu định mức không.
     * 4. Nhánh 2: Nếu đích đến là một PHÒNG cụ thể:
     *    - Lấy thông tin phòng đích (`Room::with('roomType')`).
     *    - Tính toán định mức chuẩn bắt buộc (`getDynamicStandardQuota`).
     *    - Đếm số lượng hiện có của thiết bị cùng nhóm trong phòng đích.
     *    - So sánh số lượng sau khi thêm (`afterCount = currentCount + 1`):
     *      * Trạng thái 'exact_quota': Chuyển vào sẽ giúp phòng đạt chuẩn định mức.
     *      * Trạng thái 'over_quota': Chuyển vào sẽ khiến phòng bị thừa thiết bị.
     *      * Trạng thái 'under_quota': Vẫn còn thiếu so với định mức.
     *      * Trạng thái 'no_standard': Hạng phòng không quy định định mức cho thiết bị này.
     *    - Đánh giá tác động ngược lại đối với phòng nguồn (nếu chuyển từ phòng khác sang).
     * 5. Trả về mảng thông tin chi tiết để giao diện hiển thị hộp thoại cảnh báo người dùng.
     *
     * [3. NGUỒN GỌI (CALLERS)]
     * - API: GET /api/v1/equipments/{id}/check-assign-target
     * - Controller: App\Modules\Equipment\Controllers\EquipmentController::checkAssignTarget()
     * - UI Component: Modal `AssignRoomModal.vue` khi chọn phòng đích từ danh sách
     *
     * [4. THAM SỐ (PARAMETERS)]
     * @param int $equipmentId ID thiết bị chuẩn bị điều chuyển
     * @param int|null $targetRoomId ID phòng dự kiến chuyển tới (null nếu chuyển về kho)
     *
     * [5. GIÁ TRỊ TRẢ VỀ]
     * @return array Cấu trúc gồm: target_type, target_room, model, standard_quota, current_count, after_count, status, message, source_room_impact
     *
     * [6. VÍ DỤ SỬ DỤNG]
     * @example
     * ```php
     * $checkResult = $equipmentService->checkAssignTarget(10, 205);
     * if ($checkResult['status'] === 'over_quota') {
     *     // Cảnh báo người dùng phòng đã đủ số lượng TV
     * }
     * ```
     */
    public function checkAssignTarget(int $equipmentId, ?int $targetRoomId): array
    {
        $equipment = RoomEquipment::with(['model.category', 'room'])->findOrFail($equipmentId);

        $category = $equipment->model?->category;
        $catCode = $category?->code ?? '';
        $catId = $category?->id;
        $isDynamicCategory = in_array($catCode, ['tv', 'box', 'remote', 'wifi', 'phone']);
        $sameCatModelIds = ($isDynamicCategory && $catId)
            ? EquipmentModel::where('equipment_category_id', $catId)->pluck('id')
            : collect([$equipment->equipment_model_id]);

        // Trường hợp 1: Thu hồi về kho
        if (!$targetRoomId) {
            $sourceImpact = null;
            if ($equipment->room_id) {
                $sourceRoom = Room::find($equipment->room_id);

                $sourceStd = $sourceRoom ? RoomStandardEquipment::where('room_type_id', $sourceRoom->room_type_id)
                    ->whereIn('equipment_model_id', $sameCatModelIds)
                    ->first() : null;

                $sourceCurr = $sourceRoom ? RoomEquipment::where('room_id', $sourceRoom->id)
                    ->whereIn('equipment_model_id', $sameCatModelIds)
                    ->count() : 0;

                $sourceAfter = max(0, $sourceCurr - 1);
                $sourceStaticQty = $sourceStd ? $sourceStd->standard_quantity : 0;
                $sourceStandardQty = $sourceRoom ? $this->getDynamicStandardQuota($sourceRoom, $equipment->model, $sourceStaticQty) : $sourceStaticQty;
                $willBeMissing = $sourceStd && ($sourceAfter < $sourceStandardQty);

                if ($sourceRoom) {
                    $sourceImpact = [
                        'source_room_id' => $sourceRoom->id,
                        'room_number' => (string) $sourceRoom->room_number,
                        'block' => $sourceRoom->block,
                        'floor' => $sourceRoom->floor,
                        'standard_quota' => $sourceStandardQty,
                        'current_count' => $sourceCurr,
                        'after_count' => $sourceAfter,
                        'will_be_missing' => $willBeMissing,
                        'message' => $willBeMissing
                            ? "Thu hồi thiết bị sẽ khiến Phòng {$sourceRoom->room_number} ({$sourceRoom->block}) bị THIẾU {$equipment->model?->name} so với định mức ({$sourceAfter}/{$sourceStandardQty})."
                            : "Phòng {$sourceRoom->room_number} vẫn đáp ứng định mức sau khi thu hồi thiết bị ({$sourceAfter}/{$sourceStandardQty}).",
                    ];
                }
            }

            return [
                'target_type' => 'warehouse',
                'status' => 'warehouse',
                'message' => 'Thiết bị sẽ được thu hồi về kho dự phòng chung (trạng thái in_stock).',
                'source_room_impact' => $sourceImpact,
            ];
        }

        // Trường hợp 2: Chuyển vào phòng đích
        $targetRoom = Room::with('roomType')->findOrFail($targetRoomId);

        // Định mức chuẩn của model thiết bị trong loại phòng này
        $std = RoomStandardEquipment::where('room_type_id', $targetRoom->room_type_id)
            ->whereIn('equipment_model_id', $sameCatModelIds)
            ->first();

        $staticQuota = $std ? $std->standard_quantity : 0;
        $standardQuota = $this->getDynamicStandardQuota($targetRoom, $equipment->model, $staticQuota);

        // Số lượng hiện có của thiết bị/loại này trong phòng đích (không tính chính cá thể thiết bị này nếu đang ở đó)
        $currentCount = RoomEquipment::where('room_id', $targetRoom->id)
            ->whereIn('equipment_model_id', $sameCatModelIds)
            ->where('id', '!=', $equipment->id)
            ->count();

        $afterCount = $currentCount + 1;
        $modelName = $equipment->model?->name ?? 'Thiết bị';

        if ($catCode === 'wifi' && in_array($targetRoom->wifi_mode, ['shared', 'none'])) {
            $status = 'over_quota';
            $message = "Phòng {$targetRoom->room_number} đang ở chế độ 'Dùng ké Wifi'. Việc chuyển thêm Wifi Router vào phòng sẽ vượt quá nhu cầu trang bị.";
        } elseif ($standardQuota === 0) {
            $status = 'no_standard';
            $message = "Hạng phòng '{$targetRoom->roomType?->name}' không có định mức chuẩn bắt buộc cho mẫu '{$modelName}' (thiết bị trang bị thêm ngoài định mức).";
        } elseif ($afterCount > $standardQuota) {
            $status = 'over_quota';
            $message = "Phòng {$targetRoom->room_number} ({$targetRoom->block}) đã có đủ {$currentCount}/{$standardQuota} {$modelName}. Nếu chuyển vào sẽ bị THỪA ({$afterCount}/{$standardQuota}) thiết bị.";
        } elseif ($afterCount === $standardQuota) {
            $status = 'exact_quota';
            $message = "Phòng {$targetRoom->room_number} ({$targetRoom->block}) hiện có {$currentCount}/{$standardQuota}. Chuyển vào sẽ giúp phòng ĐẠT CHUẨN ĐỊNH MỨC ({$afterCount}/{$standardQuota})!";
        } else {
            $status = 'under_quota';
            $message = "Phòng {$targetRoom->room_number} sau khi chuyển sẽ có {$afterCount}/{$standardQuota} thiết bị (vẫn cần thêm " . ($standardQuota - $afterCount) . " máy để đủ chuẩn).";
        }

        // Tác động tới phòng nguồn (nếu chuyển từ phòng khác qua)
        $sourceImpact = null;
        if ($equipment->room_id && $equipment->room_id !== $targetRoom->id) {
            $sourceRoom = Room::find($equipment->room_id);
            if ($sourceRoom) {
                $sourceStd = RoomStandardEquipment::where('room_type_id', $sourceRoom->room_type_id)
                    ->whereIn('equipment_model_id', $sameCatModelIds)
                    ->first();

                $sourceCurr = RoomEquipment::where('room_id', $sourceRoom->id)
                    ->whereIn('equipment_model_id', $sameCatModelIds)
                    ->count();

                $sourceAfter = max(0, $sourceCurr - 1);
                $sourceStaticQty = $sourceStd ? $sourceStd->standard_quantity : 0;
                $sourceStandardQty = $this->getDynamicStandardQuota($sourceRoom, $equipment->model, $sourceStaticQty);
                $willBeMissing = $sourceStd && ($sourceAfter < $sourceStandardQty);

                $sourceImpact = [
                    'source_room_id' => $sourceRoom->id,
                    'room_number' => (string) $sourceRoom->room_number,
                    'block' => $sourceRoom->block,
                    'floor' => $sourceRoom->floor,
                    'standard_quota' => $sourceStandardQty,
                    'current_count' => $sourceCurr,
                    'after_count' => $sourceAfter,
                    'will_be_missing' => $willBeMissing,
                    'message' => $willBeMissing
                        ? "Lưu ý: Sau khi chuyển đi, phòng cũ (Phòng {$sourceRoom->room_number} - {$sourceRoom->block}) sẽ bị THIẾU {$modelName} so với định mức ({$sourceAfter}/{$sourceStandardQty})."
                        : "Phòng cũ (Phòng {$sourceRoom->room_number}) vẫn đủ định mức ({$sourceAfter}/{$sourceStandardQty}).",
                ];
            }
        }

        return [
            'target_type' => 'room',
            'target_room' => [
                'id' => $targetRoom->id,
                'room_number' => (string) $targetRoom->room_number,
                'block' => $targetRoom->block,
                'floor' => $targetRoom->floor,
                'room_type' => $targetRoom->roomType?->name ?? 'Tiêu chuẩn',
                'bedroom_count' => (int) ($targetRoom->bedroom_count ?? 1),
                'living_room_count' => (int) ($targetRoom->living_room_count ?? 0),
                'wifi_mode' => $targetRoom->wifi_mode ?? 'dedicated',
            ],
            'model' => [
                'id' => $equipment->model?->id,
                'name' => $modelName,
            ],
            'standard_quota' => $standardQuota,
            'current_count' => $currentCount,
            'after_count' => $afterCount,
            'status' => $status,
            'message' => $message,
            'source_room_impact' => $sourceImpact,
        ];
    }

    /**
     * [1. MỤC ĐÍCH]
     * Tiếp nhận báo hỏng thiết bị từ nhân viên/khách hàng, tự động tạo phiếu sửa chữa và lưu ảnh hiện trường.
     *
     * [2. LUỒNG XỬ LÝ (FLOW)]
     * 1. Bắt đầu Database Transaction.
     * 2. Tìm RoomEquipment theo ID và cập nhật `status = broken`, lưu mô tả sự cố vào `note`.
     * 3. Tạo mới 1 bản ghi `EquipmentMaintenanceLog` với:
     *    - `room_equipment_id` và `room_id` hiện tại của thiết bị.
     *    - `issue_description` = mô tả lỗi do người báo cung cấp.
     *    - `status` = 'pending' (Đang chờ kỹ thuật tiếp nhận).
     *    - `reported_by` = ID người dùng đang đăng nhập (`auth()->id()`).
     * 4. Nếu có đính kèm file ảnh chụp hiện trường (`$brokenPhoto`), lưu vào collection `broken_photos`.
     * 5. Eager load thông tin `model.category`, `room`, và `maintenanceLogs`.
     * 6. Phát sự kiện broadcast `EquipmentBrokenReported` để thông báo cho đội kỹ thuật.
     * 7. Xóa cache thống kê khách sạn (`invalidateCache`).
     *
     * [3. NGUỒN GỌI (CALLERS)]
     * - API: POST /api/v1/equipments/{id}/report-broken
     * - Controller: App\Modules\Equipment\Controllers\EquipmentController::reportBroken()
     * - UI Component: Modal `ReportBrokenModal.vue` khi bấm nút "Báo hỏng" trên thẻ phòng hoặc danh sách
     *
     * [4. THAM SỐ (PARAMETERS)]
     * @param int $equipmentId ID thiết bị gặp sự cố
     * @param string $issueDescription Mô tả chi tiết triệu chứng lỗi (ví dụ: "Màn hình sọc ngang, không lên nguồn")
     * @param mixed $brokenPhoto File ảnh hoặc mảng file ảnh chụp hiện trường (UploadedFile)
     *
     * [5. GIÁ TRỊ TRẢ VỀ & NGOẠI LỆ]
     * @return \App\Modules\Equipment\Models\RoomEquipment Model thiết bị sau khi cập nhật trạng thái hỏng
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException Khi ID thiết bị không tồn tại
     *
     * [6. VÍ DỤ SỬ DỤNG]
     * @example
     * ```php
     * $item = $equipmentService->reportBroken(15, 'Điều khiển TV bị gãy nắp pin', $request->file('photo'));
     * ```
     */
    public function reportBroken(int $equipmentId, string $issueDescription, mixed $brokenPhoto = null): RoomEquipment
    {
        $item = DB::transaction(function () use ($equipmentId, $issueDescription, $brokenPhoto) {
            $item = RoomEquipment::findOrFail($equipmentId);
            $item->status = EquipmentStatusEnum::Broken;
            $item->note = $issueDescription;
            $item->save();

            // Ghi nhật ký bảo trì
            $log = EquipmentMaintenanceLog::create([
                'room_equipment_id' => $item->id,
                'room_id' => $item->room_id,
                'issue_description' => $issueDescription,
                'status' => MaintenanceStatusEnum::Pending,
                'reported_by' => auth()->id(),
            ]);

            // Lưu ảnh chụp hiện trường hỏng
            if ($brokenPhoto) {
                $item->addMedia($brokenPhoto)->toMediaCollection('broken_photos');
            }

            return $item->load(['model.category', 'room', 'maintenanceLogs']);
        });

        // Phát Broadcast Real-time
        $this->safeBroadcast(new EquipmentBrokenReported($item, $issueDescription, auth()->user()?->name));
        $this->invalidateCache($item->organization_id);

        return $item;
    }

    /**
     * [1. MỤC ĐÍCH]
     * Tự động đồng bộ trạng thái phiếu bảo trì (EquipmentMaintenanceLog) khi trạng thái cá thể thiết bị thay đổi.
     *
     * [2. LUỒNG XỬ LÝ (FLOW)]
     * 1. Nếu trạng thái mới là 'broken':
     *    - Kiểm tra xem đã có phiếu bảo trì nào đang mở ('pending' hoặc 'in_progress') hay chưa.
     *    - Nếu chưa có, tự động tạo mới 1 phiếu `status = pending`.
     * 2. Nếu trạng thái mới là 'maintenance':
     *    - Chuyển phiếu bảo trì đang mở sang 'in_progress' (hoặc tạo phiếu mới nếu chưa có).
     * 3. Nếu trạng thái mới là 'good':
     *    - Đóng toàn bộ phiếu bảo trì đang mở: `status = completed`, `resolved_at = now()`, `resolved_by = auth()->id()`.
     * 4. Nếu trạng thái mới là 'disposed' (thanh lý do không sửa được):
     *    - Đóng phiếu bảo trì đang mở: `status = unfixable`, `resolved_at = now()`.
     *
     * [3. NGUỒN GỌI (CALLERS)]
     * - Nội bộ trong EquipmentService: update(), changeStatus(), bulkUpdateStatus(), assignToRoom()
     *
     * [4. THAM SỐ (PARAMETERS)]
     * @param \App\Modules\Equipment\Models\RoomEquipment $equipment Đối tượng thiết bị đang xử lý
     * @param string $newStatus Giá trị trạng thái mới ('good', 'broken', 'maintenance', 'disposed'...)
     *
     * [5. GIÁ TRỊ TRẢ VỀ]
     * @return void
     *
     * [6. VÍ DỤ SỬ DỤNG]
     * @example
     * ```php
     * $this->syncOpenMaintenanceLogs($equipment, 'good');
     * ```
     */
    protected function syncOpenMaintenanceLogs(RoomEquipment $equipment, string $newStatus): void
    {
        if ($newStatus === EquipmentStatusEnum::Broken->value) {
            // Khi thiết bị chuyển sang Hư hỏng, đảm bảo có một phiếu sự cố đang mở
            $hasOpenLog = EquipmentMaintenanceLog::where('room_equipment_id', $equipment->id)
                ->whereIn('status', [MaintenanceStatusEnum::Pending->value, MaintenanceStatusEnum::InProgress->value])
                ->exists();

            if (! $hasOpenLog) {
                EquipmentMaintenanceLog::create([
                    'room_equipment_id' => $equipment->id,
                    'room_id' => $equipment->room_id,
                    'issue_description' => $equipment->note ?: 'Báo hỏng thiết bị (Chuyển trạng thái sang Hư hỏng)',
                    'status' => MaintenanceStatusEnum::Pending,
                    'reported_by' => auth()->id(),
                ]);
            }
        } elseif ($newStatus === EquipmentStatusEnum::Maintenance->value) {
            $openLog = EquipmentMaintenanceLog::where('room_equipment_id', $equipment->id)
                ->whereIn('status', [MaintenanceStatusEnum::Pending->value, MaintenanceStatusEnum::InProgress->value])
                ->first();

            if ($openLog) {
                $openLog->update(['status' => MaintenanceStatusEnum::InProgress]);
            } else {
                EquipmentMaintenanceLog::create([
                    'room_equipment_id' => $equipment->id,
                    'room_id' => $equipment->room_id,
                    'issue_description' => $equipment->note ?: 'Bảo trì thiết bị (Chuyển trạng thái sang Đang bảo trì)',
                    'status' => MaintenanceStatusEnum::InProgress,
                    'reported_by' => auth()->id(),
                ]);
            }
        } elseif ($newStatus === EquipmentStatusEnum::Good->value) {
            EquipmentMaintenanceLog::where('room_equipment_id', $equipment->id)
                ->whereIn('status', [MaintenanceStatusEnum::Pending->value, MaintenanceStatusEnum::InProgress->value])
                ->update([
                    'status' => MaintenanceStatusEnum::Completed->value,
                    'resolved_at' => now(),
                    'resolved_by' => auth()->id(),
                ]);
        } elseif ($newStatus === EquipmentStatusEnum::Disposed->value) {
            EquipmentMaintenanceLog::where('room_equipment_id', $equipment->id)
                ->whereIn('status', [MaintenanceStatusEnum::Pending->value, MaintenanceStatusEnum::InProgress->value])
                ->update([
                    'status' => MaintenanceStatusEnum::Unfixable->value,
                    'resolved_at' => now(),
                    'resolved_by' => auth()->id(),
                ]);
        }
    }

    /**
     * [1. MỤC ĐÍCH]
     * Cập nhật tiến độ xử lý, chi phí sửa chữa và tình trạng hoàn tất của phiếu bảo trì.
     *
     * [2. LUỒNG XỬ LÝ (FLOW)]
     * 1. Bắt đầu Database Transaction và tìm EquipmentMaintenanceLog theo `$logId`.
     * 2. Cập nhật chi phí (`cost`), mô tả bổ sung (`issue_description`) nếu có.
     * 3. Nếu có cập nhật trạng thái phiếu (`status`):
     *    - Nếu trạng thái là 'completed' hoặc 'unfixable': Ghi nhận `resolved_at = now()` và `resolved_by = auth()->id()`.
     * 4. Tự động đồng bộ ngược lại trạng thái của thiết bị (`RoomEquipment`):
     *    - Nếu phiếu 'completed' -> Đổi thiết bị thành 'good' (Hoạt động tốt).
     *    - Nếu phiếu 'in_progress' và thiết bị đang 'broken' -> Đổi thành 'maintenance' (Đang sửa chữa).
     *    - Nếu phiếu 'unfixable' -> Đổi thiết bị thành 'disposed' (Thanh lý).
     * 5. Eager load thông tin người báo (`reportedByUser`) và người giải quyết (`resolvedByUser`).
     * 6. Phát sự kiện Broadcast để giao diện cập nhật thời gian thực.
     *
     * [3. NGUỒN GỌI (CALLERS)]
     * - API: PATCH /api/v1/equipments/maintenance-logs/{logId}
     * - Controller: App\Modules\Equipment\Controllers\EquipmentController::updateMaintenanceLog()
     * - Frontend Service: `equipmentService.updateMaintenanceLog()`
     *
     * [4. THAM SỐ (PARAMETERS)]
     * @param int $logId ID phiếu bảo trì
     * @param array $data Dữ liệu cập nhật (status, cost, issue_description)
     *
     * [5. GIÁ TRỊ TRẢ VỀ & NGOẠI LỆ]
     * @return \App\Modules\Equipment\Models\EquipmentMaintenanceLog Phiếu bảo trì sau khi cập nhật
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException Khi không tìm thấy phiếu
     *
     * [6. VÍ DỤ SỬ DỤNG]
     * @example
     * ```php
     * $log = $equipmentService->updateMaintenanceLog(5, [
     *     'status' => 'completed',
     *     'cost' => 150000,
     *     'issue_description' => 'Đã thay thế tụ nguồn bo mạch'
     * ]);
     * ```
     */
    public function updateMaintenanceLog(int $logId, array $data): EquipmentMaintenanceLog
    {
        $equipmentToBroadcast = null;
        $oldStatus = null;
        $statusChanged = false;

        $log = DB::transaction(function () use ($logId, $data, &$equipmentToBroadcast, &$oldStatus, &$statusChanged) {
            $log = EquipmentMaintenanceLog::findOrFail($logId);
            $updateFields = [];

            if (isset($data['status'])) {
                $statusEnum = $data['status'] instanceof MaintenanceStatusEnum ? $data['status'] : MaintenanceStatusEnum::from($data['status']);
                $updateFields['status'] = $statusEnum;
                if (in_array($statusEnum->value, [MaintenanceStatusEnum::Completed->value, MaintenanceStatusEnum::Unfixable->value], true)) {
                    $updateFields['resolved_at'] = now();
                    $updateFields['resolved_by'] = auth()->id();
                }
            }

            if (isset($data['cost'])) {
                $updateFields['cost'] = (float) $data['cost'];
            }

            if (isset($data['issue_description'])) {
                $updateFields['issue_description'] = $data['issue_description'];
            }

            $log->update($updateFields);

            // Tự động đồng bộ trạng thái thiết bị tương ứng
            if (!empty($data['status'])) {
                $equipment = $log->equipment;
                if ($equipment) {
                    $oldStatus = $equipment->status instanceof \BackedEnum ? $equipment->status->value : (string) $equipment->status;
                    $statusVal = $data['status'] instanceof MaintenanceStatusEnum ? $data['status']->value : (string) $data['status'];
                    if ($statusVal === MaintenanceStatusEnum::Completed->value) {
                        $equipment->status = EquipmentStatusEnum::Good;
                        $equipment->save();
                        $statusChanged = ($oldStatus !== EquipmentStatusEnum::Good->value);
                    } elseif ($statusVal === MaintenanceStatusEnum::InProgress->value && $equipment->status === EquipmentStatusEnum::Broken) {
                        $equipment->status = EquipmentStatusEnum::Maintenance;
                        $equipment->save();
                        $statusChanged = ($oldStatus !== EquipmentStatusEnum::Maintenance->value);
                    } elseif ($statusVal === MaintenanceStatusEnum::Unfixable->value) {
                        $equipment->status = EquipmentStatusEnum::Disposed;
                        $equipment->save();
                        $statusChanged = ($oldStatus !== EquipmentStatusEnum::Disposed->value);
                    }
                    $this->invalidateCache($equipment->organization_id);
                    $equipmentToBroadcast = $equipment->load(['model.category', 'room']);
                }
            }

            return $log->load(['equipment', 'reportedByUser', 'resolvedByUser']);
        });

        // Phát Broadcast Real-time thông báo cập nhật tiến độ / trạng thái
        if ($equipmentToBroadcast) {
            if ($statusChanged) {
                $this->safeBroadcast(new EquipmentStatusChanged($equipmentToBroadcast, $oldStatus));
            } else {
                $this->safeBroadcast(new EquipmentUpdated($equipmentToBroadcast));
            }
        }

        return $log;
    }

    /**
     * [1. MỤC ĐÍCH]
     * Tải thêm hình ảnh đính kèm cho thiết bị (ảnh chụp thực tế hoặc ảnh hiện trường hư hỏng).
     *
     * [2. LUỒNG XỬ LÝ (FLOW)]
     * 1. Tìm RoomEquipment theo ID.
     * 2. Sử dụng Spatie Media Library để lưu file vào collection tương ứng ('equipment_photos' hoặc 'broken_photos').
     * 3. Eager load lại quan hệ `media` để cập nhật danh sách ảnh.
     * 4. Trả về thực thể RoomEquipment có danh sách media mới nhất.
     *
     * [3. NGUỒN GỌI (CALLERS)]
     * - API: POST /api/v1/equipments/{id}/photos
     * - Controller: App\Modules\Equipment\Controllers\EquipmentController::uploadPhoto()
     * - Frontend Store: `useEquipmentStore` hoặc form chi tiết thiết bị
     *
     * [4. THAM SỐ (PARAMETERS)]
     * @param int $equipmentId ID thiết bị
     * @param mixed $file Tệp hình ảnh tải lên (UploadedFile)
     * @param string $collection Tên bộ sưu tập ảnh (mặc định 'equipment_photos')
     *
     * [5. GIÁ TRỊ TRẢ VỀ & NGOẠI LỆ]
     * @return \App\Modules\Equipment\Models\RoomEquipment Model thiết bị kèm danh sách media
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException Khi không tìm thấy thiết bị
     *
     * [6. VÍ DỤ SỬ DỤNG]
     * @example
     * ```php
     * $item = $equipmentService->uploadPhoto(12, $request->file('file'), 'equipment_photos');
     * ```
     */
    public function uploadPhoto(int $equipmentId, mixed $file, string $collection = 'equipment_photos'): RoomEquipment
    {
        $item = RoomEquipment::findOrFail($equipmentId);
        $item->addMedia($file)->toMediaCollection($collection);

        return $item->load('media');
    }

    /**
     * [1. MỤC ĐÍCH]
     * Xóa 1 ảnh đính kèm của thiết bị theo media ID.
     *
     * [2. LUỒNG XỬ LÝ (FLOW)]
     * 1. Tìm RoomEquipment theo ID.
     * 2. Tìm bản ghi media liên kết với thiết bị qua quan hệ `item->media()->where('id', $mediaId)->firstOrFail()`.
     * 3. Xóa file vật lý trên đĩa và xóa bản ghi trong bảng `media`.
     * 4. Load lại quan hệ media và trả về đối tượng RoomEquipment.
     *
     * [3. NGUỒN GỌI (CALLERS)]
     * - API: DELETE /api/v1/equipments/{id}/photos/{mediaId}
     * - Controller: App\Modules\Equipment\Controllers\EquipmentController::deletePhoto()
     * - Frontend Service: `equipmentService.deletePhoto()`
     *
     * [4. THAM SỐ (PARAMETERS)]
     * @param int $equipmentId ID thiết bị sở hữu ảnh
     * @param int $mediaId ID của ảnh trong bảng `media`
     *
     * [5. GIÁ TRỊ TRẢ VỀ & NGOẠI LỆ]
     * @return \App\Modules\Equipment\Models\RoomEquipment Model sau khi xóa ảnh
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException Khi không tìm thấy thiết bị hoặc ảnh
     *
     * [6. VÍ DỤ SỬ DỤNG]
     * @example
     * ```php
     * $item = $equipmentService->deletePhoto(12, 105);
     * ```
     */
    public function deletePhoto(int $equipmentId, int $mediaId): RoomEquipment
    {
        $item = RoomEquipment::findOrFail($equipmentId);
        $media = $item->media()->where('id', $mediaId)->firstOrFail();
        $media->delete();

        return $item->load('media');
    }

    /**
     * [1. MỤC ĐÍCH]
     * Thống kê tổng thể trang thiết bị toàn khách sạn (tổng số lượng, số tốt, hỏng, bảo trì, trong kho, phân rã theo nhóm danh mục).
     *
     * [2. LUỒNG XỬ LÝ (FLOW)]
     * 1. Kiểm tra cache Redis/File với key `hotel_stats_{orgId}_{block}` (TTL 60 giây).
     * 2. Nếu cache miss:
     *    - Tạo query tổng hợp trạng thái (good, broken, maintenance, in_stock, lost) gom nhóm theo SQL `groupBy('status')`.
     *    - Tính toán số lượng thiết bị theo từng danh mục (TV, Box, Wifi, Remote...) và từng trạng thái trong 1 câu query duy nhất.
     *    - Đếm số lượng phòng đang có sự cố (`brokenRoomsCount`).
     *    - Thống kê phân bổ thiết bị theo từng Tòa nhà / Block (`by_block`).
     *    - Lưu kết quả vào Cache trong 60 giây.
     * 3. Trả về mảng dữ liệu thống kê đồng bộ cho Dashboard, Header và Map View.
     *
     * [3. NGUỒN GỌI (CALLERS)]
     * - API: GET /api/v1/equipments/stats
     * - Controller: App\Modules\Equipment\Controllers\EquipmentController::stats()
     * - Frontend Header: `EquipmentStatHeader.vue`, `MapStatHeader.vue`, Dashboard view
     *
     * [4. THAM SỐ (PARAMETERS)]
     * @param int|null $organizationId ID tổ chức / cơ sở (mặc định lấy theo phiên làm việc)
     * @param string|null $block Tên Block lọc thống kê (ví dụ: 'Tòa A', 'Tòa B', '__stock__' cho kho)
     *
     * [5. GIÁ TRỊ TRẢ VỀ]
     * @return array Mảng chứa: total_equipments, summary, by_status, rooms_with_issues_count, by_category, by_block, in_stock_total, all_total
     *
     * [6. VÍ DỤ SỬ DỤNG]
     * @example
     * ```php
     * $stats = $equipmentService->getHotelStats(organizationId: 1, block: 'Tòa A');
     * echo "Tổng thiết bị: " . $stats['total_equipments'];
     * ```
     */
    public function getHotelStats(?int $organizationId = null, ?string $block = null): array
    {
        $version = (int) cache()->get('hotel_stats_version_' . ($organizationId ?? 'all'), 1);
        $cacheKey = 'hotel_stats_v' . $version . '_' . ($organizationId ?? 'all') . '_' . ($block ?? 'all');

        return cache()->remember($cacheKey, 60, function () use ($organizationId, $block) {
            $baseQuery = DB::table('room_equipments')
                ->whereNull('room_equipments.deleted_at');

            if ($organizationId) {
                $baseQuery->where('room_equipments.organization_id', $organizationId);
            }

            if (!empty($block)) {
                if ($block === '__stock__') {
                    $baseQuery->whereNull('room_equipments.room_id');
                } else {
                    $baseQuery->join('rooms', 'rooms.id', '=', 'room_equipments.room_id')
                        ->where('rooms.block', $block);
                }
            }

            // 1. Status aggregation in 1 fast query
            $statusCounts = (clone $baseQuery)
                ->select('room_equipments.status', DB::raw('count(*) as count'))
                ->groupBy('room_equipments.status')
                ->pluck('count', 'status')
                ->all();

            $totalCount = array_sum($statusCounts);
            $goodCount = (int) ($statusCounts[EquipmentStatusEnum::Good->value] ?? 0);
            $brokenCount = (int) ($statusCounts[EquipmentStatusEnum::Broken->value] ?? 0);
            $maintenanceCount = (int) ($statusCounts[EquipmentStatusEnum::Maintenance->value] ?? 0);
            $lostCount = (int) ($statusCounts[EquipmentStatusEnum::Lost->value] ?? 0);

            $inStockCount = empty($block)
                ? (int) ($statusCounts[EquipmentStatusEnum::InStock->value] ?? 0)
                : (int) DB::table('room_equipments')
                    ->whereNull('deleted_at')
                    ->whereNull('room_id')
                    ->where('status', EquipmentStatusEnum::InStock->value)
                    ->when($organizationId, fn($q) => $q->where('organization_id', $organizationId))
                    ->count();

            // 2. Category + Status breakdown in 1 query
            $catStatusCounts = (clone $baseQuery)
                ->join('equipment_models', 'equipment_models.id', '=', 'room_equipments.equipment_model_id')
                ->select(
                    'equipment_models.equipment_category_id',
                    'room_equipments.status',
                    DB::raw('count(*) as count')
                )
                ->groupBy('equipment_models.equipment_category_id', 'room_equipments.status')
                ->get();

            $catGrouped = [];
            foreach ($catStatusCounts as $row) {
                $catId = $row->equipment_category_id;
                $status = $row->status;
                $cnt = (int) $row->count;

                if (!isset($catGrouped[$catId])) {
                    $catGrouped[$catId] = ['total' => 0, 'good' => 0, 'broken' => 0, 'maintenance' => 0, 'in_stock' => 0, 'lost' => 0];
                }
                $catGrouped[$catId]['total'] += $cnt;
                if (isset($catGrouped[$catId][$status])) {
                    $catGrouped[$catId][$status] += $cnt;
                }
            }

            $categories = EquipmentCategory::orderBy('sort_order')->get();
            $byCategory = [];
            foreach ($categories as $cat) {
                $stat = $catGrouped[$cat->id] ?? ['total' => 0, 'good' => 0, 'broken' => 0, 'maintenance' => 0, 'in_stock' => 0, 'lost' => 0];
                $byCategory[] = [
                    'category_id' => $cat->id,
                    'category_name' => $cat->name,
                    'category_code' => $cat->code,
                    'icon' => $cat->icon,
                    'total' => $stat['total'],
                    'good' => $stat['good'],
                    'broken' => $stat['broken'],
                    'maintenance' => $stat['maintenance'],
                    'in_stock' => $stat['in_stock'],
                    'lost' => $stat['lost'],
                ];
            }

            // 3. Đếm số phòng có vấn đề (thiết bị hỏng)
            $brokenRoomsCount = (clone $baseQuery)
                ->where('room_equipments.status', EquipmentStatusEnum::Broken->value)
                ->whereNotNull('room_equipments.room_id')
                ->distinct()
                ->count('room_equipments.room_id');

            $statusData = [
                'good' => $goodCount,
                'broken' => $brokenCount,
                'maintenance' => $maintenanceCount,
                'in_stock' => $inStockCount,
                'lost' => $lostCount,
            ];

            $blockCountsQuery = DB::table('room_equipments')
                ->join('rooms', 'rooms.id', '=', 'room_equipments.room_id')
                ->whereNull('room_equipments.deleted_at');
            if ($organizationId) {
                $blockCountsQuery->where('room_equipments.organization_id', $organizationId);
            }
            $blockCounts = $blockCountsQuery
                ->select('rooms.block', DB::raw('count(*) as count'))
                ->groupBy('rooms.block')
                ->pluck('count', 'block')
                ->all();

            // Đảm bảo tất cả các Block thực tế đang có trong bảng rooms (kể cả chưa có thiết bị = 0)
            // đều được liệt kê đầy đủ để Frontend hiển thị đồng bộ các nút filter block.
            $allRoomBlocks = DB::table('rooms')
                ->whereNull('deleted_at')
                ->whereNotNull('block')
                ->where('block', '!=', '')
                ->when($organizationId, fn($q) => $q->where('organization_id', $organizationId))
                ->distinct()
                ->pluck('block')
                ->all();

            foreach ($allRoomBlocks as $rb) {
                if (!isset($blockCounts[$rb])) {
                    $blockCounts[$rb] = 0;
                }
            }

            $inStockTotal = (int) DB::table('room_equipments')
                ->whereNull('deleted_at')
                ->whereNull('room_id')
                ->when($organizationId, fn($q) => $q->where('organization_id', $organizationId))
                ->count();

            $allTotal = (int) DB::table('room_equipments')
                ->whereNull('deleted_at')
                ->when($organizationId, fn($q) => $q->where('organization_id', $organizationId))
                ->count();

            return [
                'total_equipments' => $totalCount,
                'summary' => $statusData,
                'by_status' => $statusData,
                'rooms_with_issues_count' => $brokenRoomsCount,
                'by_category' => $byCategory,
                'by_block' => $blockCounts,
                'in_stock_total' => $inStockTotal,
                'all_total' => $allTotal,
            ];
        });
    }

    /**
     * [1. MỤC ĐÍCH]
     * Tính toán định mức chuẩn động (Dynamic Standard Quota) dựa theo cơ cấu thực tế của từng phòng:
     * - TV, TV Box, Remote: max(1, số phòng ngủ + số phòng khách).
     * - Wifi Router: 0 nếu dùng chung (shared) hoặc không dùng (none); 1 nếu dùng riêng (dedicated).
     * - Điện thoại bàn: 1 chiếc cho mỗi phòng.
     * - Bỏ định mức: Điều hòa (ac), Tủ lạnh (fridge), Thiết bị gia dụng (appliance) -> trả về 0.
     * - Các danh mục khác: giữ nguyên định mức tĩnh trong cấu hình phòng.
     *
     * [2. LUỒNG XỬ LÝ (FLOW)]
     * 1. Xác định mã code danh mục của thiết bị (`$catCode = $model->category->code`).
     * 2. Nếu thuộc nhóm `['tv', 'box', 'remote']`:
     *    - Lấy `bedroom_count` (mặc định 1) + `living_room_count` (mặc định 0).
     *    - Định mức = max(1, tổng số phòng ngủ và phòng khách).
     * 3. Nếu là 'wifi':
     *    - Nếu `room->wifi_mode` là 'shared' hoặc 'none' -> Định mức = 0.
     *    - Nếu riêng (dedicated) -> Định mức = 1.
     * 4. Nếu là 'phone': Định mức = 1.
     * 5. Nếu thuộc nhóm `['ac', 'fridge', 'appliance']`: Định mức = 0 (bỏ kiểm kê bắt buộc).
     * 6. Các loại khác: trả về định mức tĩnh `$staticQuota` từ cấu hình `RoomStandardEquipment`.
     *
     * [3. NGUỒN GỌI (CALLERS)]
     * - Nội bộ trong EquipmentService: checkAssignTarget()
     * - Resource: RoomEquipmentDetailResource
     * - Service: RoomAuditService, RoomEquipmentService
     *
     * [4. THAM SỐ (PARAMETERS)]
     * @param \App\Modules\Motel\Models\Room $room Đối tượng phòng cần tính định mức
     * @param \App\Modules\Equipment\Models\EquipmentModel|null $model Mẫu thiết bị đang xét
     * @param int $staticQuota Định mức chuẩn tĩnh được cài đặt sẵn cho loại phòng (mặc định 0)
     *
     * [5. GIÁ TRỊ TRẢ VỀ]
     * @return int Số lượng định mức chuẩn yêu cầu cho thiết bị này trong phòng
     *
     * [6. VÍ DỤ SỬ DỤNG]
     * @example
     * ```php
     * // Phòng có 2 phòng ngủ + 1 phòng khách:
     * $tvQuota = $equipmentService->getDynamicStandardQuota($room, $tvModel, 1);
     * // Trả về: 3 (mỗi phòng ngủ 1 TV + phòng khách 1 TV)
     * ```
     */
    public function getDynamicStandardQuota(Room $room, ?EquipmentModel $model, int $staticQuota = 0): int
    {
        $catCode = $model?->category?->code ?? '';

        if (in_array($catCode, ['tv', 'box', 'remote'])) {
            $bedroomCount = (int) ($room->bedroom_count ?? 1);
            $livingRoomCount = (int) ($room->living_room_count ?? 0);
            return max(1, $bedroomCount + $livingRoomCount);
        }

        if ($catCode === 'wifi') {
            if ($room->wifi_mode === 'shared' || $room->wifi_mode === 'none') {
                return 0;
            }
            return 1;
        }

        if ($catCode === 'phone') {
            return 1;
        }

        if (in_array($catCode, ['ac', 'fridge', 'appliance'])) {
            return 0;
        }

        return $staticQuota;
    }

    /**
     * [1. MỤC ĐÍCH]
     * Lấy toàn bộ thông tin chi tiết về thiết bị, định mức và sơ đồ mạng wifi của một phòng cụ thể.
     *
     * [2. LUỒNG XỬ LÝ (FLOW)]
     * 1. Tìm bản ghi Room theo `$roomId` (sử dụng findOrFail).
     * 2. Eager load sâu các mối quan hệ:
     *    - `roomType`: Thông tin hạng phòng tiêu chuẩn.
     *    - `sharedWifiRoom.equipments.model.category`: Thông tin phòng chia sẻ wifi (nếu đang dùng ké wifi).
     *    - `dependentWifiRooms`: Danh sách các phòng khác đang dùng ké wifi từ phòng này.
     *    - `equipments.model.category` & `equipments.media`: Danh sách thiết bị hiện có trong phòng kèm ảnh.
     *    - `standardEquipments.model.category`: Danh sách định mức tiêu chuẩn được cấu hình.
     * 3. Trả về thực thể Room đã nạp đủ dữ liệu.
     *
     * [3. NGUỒN GỌI (CALLERS)]
     * - API: GET /api/v1/equipments/room-detail/{roomId}
     * - Controller: App\Modules\Equipment\Controllers\RoomEquipmentDetailController::show()
     * - Frontend Store & View: `equipmentService.getRoomDetail(roomId)` tại `MapRoomDetailModal.vue`
     *
     * [4. THAM SỐ (PARAMETERS)]
     * @param int $roomId ID của phòng cần tra cứu
     *
     * [5. GIÁ TRỊ TRẢ VỀ & NGOẠI LỆ]
     * @return \App\Modules\Motel\Models\Room Model phòng kèm đầy đủ quan hệ
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException Khi ID phòng không tồn tại
     *
     * [6. VÍ DỤ SỬ DỤNG]
     * @example
     * ```php
     * $room = $equipmentService->getRoomDetail(105);
     * foreach ($room->equipments as $equipment) {
     *     echo $equipment->model->name . ' - ' . $equipment->status;
     * }
     * ```
     */
    public function getRoomDetail(int $roomId): Room
    {
        return Room::with([
            'roomType',
            'sharedWifiRoom.equipments.model.category',
            'dependentWifiRooms',
            'equipments.model.category',
            'equipments.media',
            'standardEquipments.model.category',
        ])->findOrFail($roomId);
    }
}
