<?php

namespace App\Modules\Equipment\Services;

use App\Modules\Equipment\Enums\EquipmentStatusEnum;
use App\Modules\Equipment\Enums\MaintenanceStatusEnum;
use App\Modules\Equipment\Models\EquipmentCategory;
use App\Modules\Equipment\Models\EquipmentMaintenanceLog;
use App\Modules\Equipment\Models\RoomEquipment;
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
                    ->orWhere('note', 'like', "%{$s}%");
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
        return DB::transaction(function () use ($data) {
            $photo = $data['photo'] ?? null;
            unset($data['photo']);

            if (!empty($data['room_id'])) {
                $data['installed_at'] = $data['installed_at'] ?? now();
            } else {
                $data['status'] = $data['status'] ?? EquipmentStatusEnum::InStock->value;
            }

            $item = RoomEquipment::create($data);

            if ($photo) {
                $item->addMedia($photo)->toMediaCollection('equipment_photos');
            }

            return $item->load(['model.category', 'room']);
        });
    }

    public function update(int $id, array $data): RoomEquipment
    {
        return DB::transaction(function () use ($id, $data) {
            $item = RoomEquipment::findOrFail($id);
            $photo = $data['photo'] ?? null;
            unset($data['photo']);

            $item->update($data);

            if ($photo) {
                $item->addMedia($photo)->toMediaCollection('equipment_photos');
            }

            return $item->load(['model.category', 'room']);
        });
    }

    public function delete(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            $item = RoomEquipment::findOrFail($id);
            return (bool) $item->delete();
        });
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
        return DB::transaction(function () use ($id, $status, $note) {
            $item = RoomEquipment::findOrFail($id);
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
        return DB::transaction(function () use ($equipmentId, $roomId, $positionNote) {
            $item = RoomEquipment::findOrFail($equipmentId);

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
    }

    /**
     * Báo hỏng thiết bị kèm ghi chép bảo trì và ảnh chụp lỗi.
     */
    public function reportBroken(int $equipmentId, string $issueDescription, mixed $brokenPhoto = null): RoomEquipment
    {
        return DB::transaction(function () use ($equipmentId, $issueDescription, $brokenPhoto) {
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
     * Thống kê tổng thể toàn khách sạn: Tổng số TV, Remote, Box, Wifi...
     */
    public function getHotelStats(?int $organizationId = null): array
    {
        $cacheKey = 'hotel_stats_' . ($organizationId ?? 'all');

        return cache()->remember($cacheKey, 30, function () use ($organizationId) {
            $query = RoomEquipment::with(['model.category']);
            if ($organizationId) {
                $query->where('organization_id', $organizationId);
            }
            $allEquipments = $query->get();

            $totalCount = $allEquipments->count();
            $goodCount = $allEquipments->where('status', EquipmentStatusEnum::Good)->count();
            $brokenCount = $allEquipments->where('status', EquipmentStatusEnum::Broken)->count();
            $maintenanceCount = $allEquipments->where('status', EquipmentStatusEnum::Maintenance)->count();
            $inStockCount = $allEquipments->where('status', EquipmentStatusEnum::InStock)->count();
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
     * Chi tiết thiết bị trong 1 phòng cụ thể.
     */
    public function getRoomDetail(int $roomId): Room
    {
        return Room::with([
            'roomType',
            'equipments.model.category',
            'equipments.media',
            'standardEquipments.model.category',
        ])->findOrFail($roomId);
    }
}
