<?php

namespace App\Modules\Equipment\Resources;

use App\Modules\Equipment\Enums\EquipmentStatusEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoomEquipmentDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $room = $this->resource;
        $equipments = $room->equipments ?? collect();
        $standardAllocations = $room->standardEquipments ?? collect();

        // 1. Nhóm thiết bị theo Danh mục (TV, Remote, Box, Wifi,...)
        $grouped = [];
        $actualModelCounts = [];

        foreach ($equipments as $item) {
            $model = $item->model;
            $cat = $model?->category;
            $catCode = $cat?->code ?? 'other';
            $catName = $cat?->name ?? 'Khác';

            if (!isset($grouped[$catCode])) {
                $grouped[$catCode] = [
                    'category_id' => $cat?->id,
                    'category_code' => $catCode,
                    'category_name' => $catName,
                    'category_icon' => $cat?->icon,
                    'total_count' => 0,
                    'good_count' => 0,
                    'broken_count' => 0,
                    'maintenance_count' => 0,
                    'models_summary' => [],
                    'items' => [],
                ];
            }

            $grouped[$catCode]['total_count']++;
            if ($item->status === EquipmentStatusEnum::Good) {
                $grouped[$catCode]['good_count']++;
            } elseif ($item->status === EquipmentStatusEnum::Broken) {
                $grouped[$catCode]['broken_count']++;
            } elseif ($item->status === EquipmentStatusEnum::Maintenance) {
                $grouped[$catCode]['maintenance_count']++;
            }

            // Tóm tắt theo model
            $modelName = $model?->name ?? 'Không rõ';
            $modelId = $model?->id ?? 0;
            $actualModelCounts[$modelId] = ($actualModelCounts[$modelId] ?? 0) + 1;

            if (!isset($grouped[$catCode]['models_summary'][$modelId])) {
                $grouped[$catCode]['models_summary'][$modelId] = [
                    'model_id' => $modelId,
                    'model_name' => $modelName,
                    'specs' => $model?->specs,
                    'count' => 0,
                ];
            }
            $grouped[$catCode]['models_summary'][$modelId]['count']++;

            // Chi tiết từng cá thể
            $grouped[$catCode]['items'][] = [
                'id' => $item->id,
                'serial_number' => $item->serial_number,
                'model_name' => $modelName,
                'status' => $item->status?->value ?? $item->status,
                'status_label' => $item->status?->label() ?? $item->status,
                'status_color' => $item->status?->color() ?? 'secondary',
                'position_note' => $item->position_note,
                'note' => $item->note,
                'installed_at' => $item->installed_at?->format('Y-m-d H:i:s'),
                'photo_url' => $item->getFirstMediaUrl('equipment_photos') ? str_replace('/laravel_hq/quandh-core/public', '', $item->getFirstMediaUrl('equipment_photos')) : null,
                'photos' => $item->getMedia('equipment_photos')->map(fn ($m) => [
                    'id' => $m->id,
                    'url' => str_replace('/laravel_hq/quandh-core/public', '', $m->getUrl()),
                ]),
                'broken_photos' => $item->getMedia('broken_photos')->map(fn ($m) => [
                    'id' => $m->id,
                    'url' => str_replace('/laravel_hq/quandh-core/public', '', $m->getUrl()),
                ]),
            ];
        }

        // Chuyển models_summary thành mảng tuần tự
        foreach ($grouped as &$g) {
            $g['models_summary'] = array_values($g['models_summary']);
        }

        // 2. So sánh với định mức chuẩn để phát hiện thiết bị THIẾU
        $missingEquipments = [];
        $totalMissingCount = 0;

        foreach ($standardAllocations as $std) {
            $modelId = $std->equipment_model_id;
            $stdModel = $std->model;
            $requiredQty = $std->standard_quantity;
            $actualQty = $actualModelCounts[$modelId] ?? 0;

            if ($actualQty < $requiredQty) {
                $diff = $requiredQty - $actualQty;
                $totalMissingCount += $diff;
                $missingEquipments[] = [
                    'model_id' => $modelId,
                    'model_name' => $stdModel?->name ?? 'Thiết bị chuẩn',
                    'name' => $stdModel?->name ?? 'Thiết bị chuẩn',
                    'category_name' => $stdModel?->category?->name ?? 'Khác',
                    'standard_quantity' => $requiredQty,
                    'actual_quantity' => $actualQty,
                    'missing_quantity' => $diff,
                    'missing_count' => $diff,
                ];
            }
        }

        $totalBrokenCount = $equipments->where('status', EquipmentStatusEnum::Broken)->count();
        $totalGoodCount = $equipments->where('status', EquipmentStatusEnum::Good)->count();
        $isCompliant = ($totalMissingCount === 0 && $totalBrokenCount === 0 && $equipments->isNotEmpty());

        return [
            'id' => $room->id,
            'room_id' => $room->id,
            'room_number' => $room->room_number,
            'name' => $room->name,
            'room_type' => $room->roomType?->name ?? 'Phòng Tiêu chuẩn',
            'compliance_status' => $isCompliant ? 'compliant' : 'non_compliant',
            'room' => [
                'id' => $room->id,
                'name' => $room->name,
                'room_number' => $room->room_number,
                'block' => $room->block,
                'floor' => $room->floor,
                'grid_row' => $room->grid_row,
                'grid_col' => $room->grid_col,
                'status' => $room->status?->value ?? $room->status,
                'room_type_id' => $room->room_type_id,
                'room_type' => $room->roomType?->name ?? 'Tiêu chuẩn',
            ],
            'summary' => [
                'total_equipments' => $equipments->count(),
                'good_count' => $totalGoodCount,
                'broken_count' => $totalBrokenCount,
                'missing_count' => $totalMissingCount,
                'has_issues' => ($totalBrokenCount > 0 || $totalMissingCount > 0),
            ],
            'categories' => array_values($grouped),
            'missing_equipments' => $missingEquipments,
        ];
    }
}
