<?php

namespace App\Modules\Equipment\Resources;

use App\Modules\Equipment\Enums\EquipmentStatusEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EquipmentMapViewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $room = $this->resource;
        $equipments = $room->equipments ?? collect();
        $standardAllocations = $room->standardEquipments ?? collect();

        $totalEquipments = $equipments->count();
        $brokenCount = $equipments->where('status', EquipmentStatusEnum::Broken)->count();
        $maintenanceCount = $equipments->where('status', EquipmentStatusEnum::Maintenance)->count();

        // Kiểm tra thiếu so với định mức
        $modelCounts = $equipments->groupBy('equipment_model_id')->map->count();
        $missingCount = 0;
        foreach ($standardAllocations as $std) {
            $actual = $modelCounts[$std->equipment_model_id] ?? 0;
            if ($actual < $std->standard_quantity) {
                $missingCount += ($std->standard_quantity - $actual);
            }
        }

        // Tóm tắt nhanh số lượng theo nhóm (TV, Remote, Box, Wifi)
        $summaryParts = [];
        $catGroups = $equipments->groupBy(fn ($item) => $item->model?->category?->name ?? 'Khác');
        foreach ($catGroups as $catName => $items) {
            $summaryParts[] = $items->count().' '.$catName;
        }
        $quickSummary = implode(', ', $summaryParts);

        // Mức độ cảnh báo & Màu badge
        // 4 trạng thái:
        // 1. unconfigured / slate: chưa cấu hình thiết bị nào
        // 2. broken / red: có thiết bị hỏng hoặc thiếu định mức nghiêm trọng
        // 3. maintenance / missing / yellow: đang bảo trì hoặc thiếu 1 vài thiết bị
        // 4. good / green: đủ định mức và hoạt động tốt
        if ($totalEquipments === 0 && $standardAllocations->isEmpty()) {
            $badgeColor = 'slate';
            $equipmentStatus = 'unconfigured';
            $alertLevel = 'default';
        } elseif ($brokenCount > 0) {
            $badgeColor = 'red';
            $equipmentStatus = 'broken';
            $alertLevel = 'danger';
        } elseif ($missingCount > 0) {
            $badgeColor = 'red';
            $equipmentStatus = 'missing';
            $alertLevel = 'warning';
        } elseif ($maintenanceCount > 0) {
            $badgeColor = 'yellow';
            $equipmentStatus = 'maintenance';
            $alertLevel = 'warning';
        } else {
            $badgeColor = 'green';
            $equipmentStatus = 'good';
            $alertLevel = 'good';
        }

        return [
            'id' => $room->id,
            'name' => $room->name,
            'room_number' => $room->room_number,
            'block' => $room->block ?? 'Block A',
            'floor' => $room->floor ?? 1,
            'grid_row' => $room->grid_row,
            'grid_col' => $room->grid_col,
            'room_status' => $room->status?->value ?? $room->status,
            'room_type_id' => $room->room_type_id,
            'room_type' => $room->roomType?->name ?? 'Tiêu chuẩn',
            'badge_color' => $badgeColor,
            'equipment_status' => $equipmentStatus,
            'stats' => [
                'total_equipments' => $totalEquipments,
                'broken_count' => $brokenCount,
                'maintenance_count' => $maintenanceCount,
                'missing_count' => $missingCount,
            ],
            'equipment_stats' => [
                'total' => $totalEquipments,
                'broken' => $brokenCount,
                'maintenance' => $maintenanceCount,
                'missing' => $missingCount,
                'alert_level' => $alertLevel,
                'quick_summary' => $quickSummary,
            ],
        ];
    }
}
