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

        // Định mức động theo cơ cấu phòng:
        // - TV, Remote: max(1, bedroom_count + living_room_count)
        // - Wifi Router: 0 nếu dùng ké wifi (shared) hoặc không dùng (none); wifi_router_count (mặc định 1) nếu dedicated
        $bedroomCount = (int) ($room->bedroom_count ?? 1);
        $livingRoomCount = (int) ($room->living_room_count ?? 0);
        $tvSpaces = max(1, $bedroomCount + $livingRoomCount);
        $wifiMode = $room->wifi_mode ?? 'dedicated';
        $requiredWifi = ($wifiMode === 'shared' || $wifiMode === 'none') ? 0 : max(1, (int) ($room->wifi_router_count ?? 1));

        $equipmentsByCat = $equipments->groupBy(fn ($item) => $item->model?->category?->code ?? 'other')->map->count();
        $modelCounts = $equipments->groupBy('equipment_model_id')->map->count();

        $missingCount = 0;

        // 1. Kiểm tra Tivi
        $hasTvStandard = $standardAllocations->contains(fn ($std) => ($std->model?->category?->code ?? '') === 'tv');
        if ($hasTvStandard || $standardAllocations->isNotEmpty()) {
            $actualTv = $equipmentsByCat['tv'] ?? 0;
            if ($actualTv < $tvSpaces) {
                $missingCount += ($tvSpaces - $actualTv);
            }
        }

        // 2. Kiểm tra Remote
        $hasRemoteStandard = $standardAllocations->contains(fn ($std) => ($std->model?->category?->code ?? '') === 'remote');
        if ($hasRemoteStandard || $standardAllocations->isNotEmpty()) {
            $actualRemote = $equipmentsByCat['remote'] ?? 0;
            if ($actualRemote < $tvSpaces) {
                $missingCount += ($tvSpaces - $actualRemote);
            }
        }

        // 3. Kiểm tra Wifi Router
        if ($requiredWifi > 0) {
            $hasWifiStandard = $standardAllocations->contains(fn ($std) => ($std->model?->category?->code ?? '') === 'wifi');
            if ($hasWifiStandard || $standardAllocations->isNotEmpty()) {
                $actualWifi = $equipmentsByCat['wifi'] ?? 0;
                if ($actualWifi < $requiredWifi) {
                    $missingCount += ($requiredWifi - $actualWifi);
                }
            }
        }

        // 4. Kiểm tra Điện thoại bàn (Mỗi 1 phòng cần 1 máy điện thoại)
        $hasPhoneStandard = $standardAllocations->contains(fn ($std) => ($std->model?->category?->code ?? '') === 'phone');
        if ($hasPhoneStandard || $standardAllocations->isNotEmpty()) {
            $actualPhone = $equipmentsByCat['phone'] ?? 0;
            if ($actualPhone < 1) {
                $missingCount += (1 - $actualPhone);
            }
        }

        // 5. Kiểm tra các thiết bị khác ngoài TV, Remote, Wifi, Phone (bỏ qua ac, fridge, appliance theo nghiệp vụ khách sạn)
        foreach ($standardAllocations as $std) {
            $catCode = $std->model?->category?->code ?? '';
            if (in_array($catCode, ['tv', 'remote', 'wifi', 'phone', 'ac', 'fridge', 'appliance'])) {
                continue; // Đã kiểm tra theo định mức động ở trên hoặc đã bỏ định mức
            }
            $actual = $modelCounts[$std->equipment_model_id] ?? 0;
            if ($actual === 0 && !empty($catCode) && isset($equipmentsByCat[$catCode])) {
                $actual = $equipmentsByCat[$catCode];
            }
            $required = (int) $std->standard_quantity;
            if ($actual < $required) {
                $missingCount += ($required - $actual);
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
            'bedroom_count' => $bedroomCount,
            'living_room_count' => $livingRoomCount,
            'wifi_mode' => $wifiMode,
            'wifi_router_count' => (int) ($room->wifi_router_count ?? 1),
            'shared_wifi_room_id' => $room->shared_wifi_room_id,
            'shared_wifi_room' => $room->sharedWifiRoom ? [
                'id' => $room->sharedWifiRoom->id,
                'room_number' => (string) $room->sharedWifiRoom->room_number,
                'name' => $room->sharedWifiRoom->name,
            ] : null,
            'shared_wifi_note' => $room->shared_wifi_note,
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
