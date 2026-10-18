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

        // Mức độ cảnh báo
        $alertLevel = 'good';
        if ($brokenCount > 0) {
            $alertLevel = 'danger';   // Đỏ: có đồ hỏng
        } elseif ($missingCount > 0) {
            $alertLevel = 'warning';  // Vàng: thiếu đồ so với chuẩn
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
