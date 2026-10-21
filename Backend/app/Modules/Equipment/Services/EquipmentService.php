<?php

namespace App\Modules\Equipment\Services;

use App\Modules\Equipment\Enums\EquipmentStatusEnum;
use App\Modules\Equipment\Enums\MaintenanceStatusEnum;
use App\Modules\Equipment\Events\EquipmentAssigned;
use App\Modules\Equipment\Events\EquipmentBrokenReported;
use App\Modules\Equipment\Events\EquipmentCreated;
use App\Modules\Equipment\Events\EquipmentDeleted;
use App\Modules\Equipment\Events\EquipmentStatusChanged;
use App\Modules\Equipment\Events\EquipmentUpdated;
use App\Modules\Equipment\Models\EquipmentCategory;
use App\Modules\Equipment\Models\EquipmentMaintenanceLog;
use App\Modules\Equipment\Models\RoomEquipment;
use App\Modules\Equipment\Models\RoomStandardEquipment;
use App\Modules\Motel\Models\Room;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class EquipmentService
{
    /**
     * Lấy danh sách thiết bị có phân trang và bộ lọc.
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

        $perPage = (int) ($filters['per_page'] ?? 15);
        $sortBy = $filters['sort_by'] ?? 'id';
        $sortOrder = strtolower($filters['sort_order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($sortBy, $sortOrder)->paginate($perPage);
    }

    public function find(int $id): RoomEquipment
    {
        return RoomEquipment::with(['model.category', 'room', 'maintenanceLogs', 'media'])->findOrFail($id);
    }

    public function create(array $data): RoomEquipment
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
        event(new EquipmentCreated($item));

        return $item;
    }

    public function update(int $id, array $data): RoomEquipment
    {
        $item = DB::transaction(function () use ($id, $data) {
            $item = RoomEquipment::findOrFail($id);
            $photo = $data['photo'] ?? null;
            unset($data['photo']);

            $item->update($data);

            if ($photo) {
                $item->addMedia($photo)->toMediaCollection('equipment_photos');
            }

            return $item->load(['model.category', 'room']);
        });

        // Phát Broadcast Real-time
        event(new EquipmentUpdated($item));

        return $item;
    }

    public function delete(int $id): bool
    {
        $item = RoomEquipment::findOrFail($id);
        $roomId = $item->room_id;
        $orgId = $item->organization_id;

        $deleted = (bool) $item->delete();

        if ($deleted) {
            // Phát Broadcast Real-time
            event(new EquipmentDeleted($id, $roomId, $orgId));
        }

        return $deleted;
    }

    public function bulkDestroy(array $ids): int
    {
        return DB::transaction(function () use ($ids) {
            return RoomEquipment::whereIn('id', $ids)->delete();
        });
    }

    /**
     * Thay đổi trạng thái thiết bị (tốt, hỏng, bảo trì, mất, trong kho).
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

            return $item->load(['model.category', 'room']);
        });

        // Phát Broadcast Real-time
        event(new EquipmentStatusChanged($item, $oldStatus));

        return $item;
    }

    /**
     * Cập nhật trạng thái hàng loạt.
     */
    public function bulkUpdateStatus(array $ids, string $status, ?string $note = null): int
    {
        return DB::transaction(function () use ($ids, $status, $note) {
            $updateData = ['status' => $status];
            if ($note) {
                $updateData['note'] = $note;
            }
            if ($status === EquipmentStatusEnum::InStock->value) {
                $updateData['room_id'] = null;
            }

            return RoomEquipment::whereIn('id', $ids)->update($updateData);
        });
    }

    /**
     * Gán thiết bị vào phòng hoặc đưa về kho.
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
                $item->status = EquipmentStatusEnum::InStock;
            }

            $item->save();

            return $item->load(['model.category', 'room']);
        });

        // Phát Broadcast Real-time
        event(new EquipmentAssigned($item, $fromRoomId));

        return $item;
    }

    /**
     * Kiểm tra định mức chuẩn của phòng đích và cảnh báo trước khi điều chuyển thiết bị.
     */
    public function checkAssignTarget(int $equipmentId, ?int $targetRoomId): array
    {
        $equipment = RoomEquipment::with(['model', 'room'])->findOrFail($equipmentId);

        // Trường hợp 1: Thu hồi về kho
        if (!$targetRoomId) {
            $sourceImpact = null;
            if ($equipment->room_id) {
                $sourceRoom = Room::find($equipment->room_id);
                $catCode = $equipment->model?->category?->code ?? '';

                $sourceStd = $sourceRoom ? RoomStandardEquipment::where('room_type_id', $sourceRoom->room_type_id)
                    ->when(!in_array($catCode, ['tv', 'remote', 'wifi']), fn ($q) => $q->where('equipment_model_id', $equipment->equipment_model_id))
                    ->when(in_array($catCode, ['tv', 'remote', 'wifi']), fn ($q) => $q->whereHas('model.category', fn ($c) => $c->where('code', $catCode)))
                    ->first() : null;

                if (in_array($catCode, ['tv', 'remote', 'wifi'])) {
                    $sourceCurr = $sourceRoom ? RoomEquipment::where('room_id', $sourceRoom->id)
                        ->whereHas('model.category', fn ($q) => $q->where('code', $catCode))
                        ->count() : 0;
                } else {
                    $sourceCurr = $sourceRoom ? RoomEquipment::where('room_id', $sourceRoom->id)
                        ->where('equipment_model_id', $equipment->equipment_model_id)
                        ->count() : 0;
                }

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
        $catCode = $equipment->model?->category?->code ?? '';

        // Định mức chuẩn của model thiết bị trong loại phòng này
        $std = RoomStandardEquipment::where('room_type_id', $targetRoom->room_type_id)
            ->when(!in_array($catCode, ['tv', 'remote', 'wifi']), fn ($q) => $q->where('equipment_model_id', $equipment->equipment_model_id))
            ->when(in_array($catCode, ['tv', 'remote', 'wifi']), fn ($q) => $q->whereHas('model.category', fn ($c) => $c->where('code', $catCode)))
            ->first();

        $staticQuota = $std ? $std->standard_quantity : 0;
        $standardQuota = $this->getDynamicStandardQuota($targetRoom, $equipment->model, $staticQuota);

        // Số lượng hiện có của thiết bị/loại này trong phòng đích (không tính chính cá thể thiết bị này nếu đang ở đó)
        if (in_array($catCode, ['tv', 'remote', 'wifi'])) {
            $currentCount = RoomEquipment::where('room_id', $targetRoom->id)
                ->whereHas('model.category', fn ($q) => $q->where('code', $catCode))
                ->where('id', '!=', $equipment->id)
                ->count();
        } else {
            $currentCount = RoomEquipment::where('room_id', $targetRoom->id)
                ->where('equipment_model_id', $equipment->equipment_model_id)
                ->where('id', '!=', $equipment->id)
                ->count();
        }

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
                    ->when(!in_array($catCode, ['tv', 'remote', 'wifi']), fn ($q) => $q->where('equipment_model_id', $equipment->equipment_model_id))
                    ->when(in_array($catCode, ['tv', 'remote', 'wifi']), fn ($q) => $q->whereHas('model.category', fn ($c) => $c->where('code', $catCode)))
                    ->first();

                if (in_array($catCode, ['tv', 'remote', 'wifi'])) {
                    $sourceCurr = RoomEquipment::where('room_id', $sourceRoom->id)
                        ->whereHas('model.category', fn ($q) => $q->where('code', $catCode))
                        ->count();
                } else {
                    $sourceCurr = RoomEquipment::where('room_id', $sourceRoom->id)
                        ->where('equipment_model_id', $equipment->equipment_model_id)
                        ->count();
                }

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
     * Báo hỏng thiết bị kèm ghi chép bảo trì và ảnh chụp lỗi.
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
        event(new EquipmentBrokenReported($item, $issueDescription, auth()->user()?->name));

        return $item;
    }

    /**
     * Tải thêm ảnh cho thiết bị (ảnh chụp thiết bị hoặc ảnh lỗi).
     */
    public function uploadPhoto(int $equipmentId, mixed $file, string $collection = 'equipment_photos'): RoomEquipment
    {
        $item = RoomEquipment::findOrFail($equipmentId);
        $item->addMedia($file)->toMediaCollection($collection);

        return $item->load('media');
    }

    /**
     * Xóa ảnh của thiết bị theo media ID.
     */
    public function deletePhoto(int $equipmentId, int $mediaId): RoomEquipment
    {
        $item = RoomEquipment::findOrFail($equipmentId);
        $media = $item->media()->where('id', $mediaId)->firstOrFail();
        $media->delete();

        return $item->load('media');
    }

    /**
     * Thống kê tổng thể toàn khách sạn: Tổng số TV, Remote, Box, Wifi...
     */
    public function getHotelStats(?int $organizationId = null, ?string $block = null): array
    {
        $cacheKey = 'hotel_stats_' . ($organizationId ?? 'all') . '_' . ($block ?? 'all');

        return cache()->remember($cacheKey, 30, function () use ($organizationId, $block) {
            $query = RoomEquipment::with(['model.category']);
            if ($organizationId) {
                $query->where('organization_id', $organizationId);
            }
            if (!empty($block)) {
                $query->whereHas('room', fn ($q) => $q->where('block', $block));
            }
            $allEquipments = $query->get();

            $totalCount = $allEquipments->count();
            $goodCount = $allEquipments->where('status', EquipmentStatusEnum::Good)->count();
            $brokenCount = $allEquipments->where('status', EquipmentStatusEnum::Broken)->count();
            $maintenanceCount = $allEquipments->where('status', EquipmentStatusEnum::Maintenance)->count();
            $inStockCount = empty($block)
                ? $allEquipments->where('status', EquipmentStatusEnum::InStock)->count()
                : RoomEquipment::whereNull('room_id')->where('status', EquipmentStatusEnum::InStock)->count();
            $lostCount = $allEquipments->where('status', EquipmentStatusEnum::Lost)->count();

            // Thống kê chi tiết theo từng loại (TV, Remote, Box, Wifi...)
            $byCategory = [];
            $categories = EquipmentCategory::orderBy('sort_order')->get();

            foreach ($categories as $cat) {
                $items = $allEquipments->filter(fn ($e) => $e->model?->equipment_category_id === $cat->id);
                $byCategory[] = [
                    'category_id' => $cat->id,
                    'category_name' => $cat->name,
                    'category_code' => $cat->code,
                    'icon' => $cat->icon,
                    'total' => $items->count(),
                    'good' => $items->where('status', EquipmentStatusEnum::Good)->count(),
                    'broken' => $items->where('status', EquipmentStatusEnum::Broken)->count(),
                    'maintenance' => $items->where('status', EquipmentStatusEnum::Maintenance)->count(),
                    'in_stock' => $items->where('status', EquipmentStatusEnum::InStock)->count(),
                    'lost' => $items->where('status', EquipmentStatusEnum::Lost)->count(),
                ];
            }

            // Đếm số phòng có vấn đề (thiết bị hỏng)
            $brokenQuery = RoomEquipment::where('status', EquipmentStatusEnum::Broken)
                ->whereNotNull('room_id');
            if ($organizationId) {
                $brokenQuery->where('organization_id', $organizationId);
            }
            if (!empty($block)) {
                $brokenQuery->whereHas('room', fn ($q) => $q->where('block', $block));
            }
            $roomsWithBroken = $brokenQuery->distinct('room_id')->count('room_id');

            $statusData = [
                'good' => $goodCount,
                'broken' => $brokenCount,
                'maintenance' => $maintenanceCount,
                'in_stock' => $inStockCount,
                'lost' => $lostCount,
            ];

            return [
                'total_equipments' => $totalCount,
                'summary' => $statusData,
                'by_status' => $statusData,
                'rooms_with_issues_count' => $roomsWithBroken,
                'by_category' => $byCategory,
            ];
        });
    }

    /**
     * Tính toán định mức chuẩn động theo cơ cấu phòng:
     * - TV, Remote: max(1, bedroom_count + living_room_count)
     * - Wifi Router: 0 nếu dùng ké wifi (shared) hoặc không wifi (none); 1 nếu riêng (dedicated)
     * - Các danh mục khác: giữ nguyên định mức từ bảng room_standard_equipments
     */
    public function getDynamicStandardQuota(Room $room, ?EquipmentModel $model, int $staticQuota = 0): int
    {
        $catCode = $model?->category?->code ?? '';

        if (in_array($catCode, ['tv', 'remote'])) {
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

        return $staticQuota;
    }

    /**
     * Chi tiết thiết bị trong 1 phòng cụ thể.
     */
    public function getRoomDetail(int $roomId): Room
    {
        return Room::with([
            'roomType',
            'sharedWifiRoom',
            'equipments.model.category',
            'equipments.media',
            'standardEquipments.model.category',
        ])->findOrFail($roomId);
    }
}
