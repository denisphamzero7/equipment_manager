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
                'photo_url' => $this->normalizeMediaUrl($item->getFirstMediaUrl('equipment_photos')),
                'photos' => $item->getMedia('equipment_photos')->map(fn ($m) => [
                    'id' => $m->id,
                    'url' => $this->normalizeMediaUrl($m->getUrl()),
                ]),
                'broken_photos' => $item->getMedia('broken_photos')->map(fn ($m) => [
                    'id' => $m->id,
                    'url' => $this->normalizeMediaUrl($m->getUrl()),
                ]),
            ];
        }

        // Chuyển models_summary thành mảng tuần tự
        foreach ($grouped as &$g) {
            $g['models_summary'] = array_values($g['models_summary']);
        }

        // 2. So sánh với định mức chuẩn để phát hiện thiết bị THIẾU
        // Định mức động theo cơ cấu phòng:
        // - TV, Remote: max(1, bedroom_count + living_room_count)
        // - Wifi Router: 0 nếu dùng ké wifi (shared) hoặc không wifi (none); wifi_router_count (mặc định 1) nếu dedicated
        $bedroomCount = (int) ($room->bedroom_count ?? 1);
        $livingRoomCount = (int) ($room->living_room_count ?? 0);
        $tvSpaces = max(1, $bedroomCount + $livingRoomCount);
        $wifiMode = $room->wifi_mode ?? 'dedicated';
        $requiredWifi = ($wifiMode === 'shared' || $wifiMode === 'none') ? 0 : max(1, (int) ($room->wifi_router_count ?? 1));

        $equipmentsByCat = $equipments->groupBy(fn ($item) => $item->model?->category?->code ?? 'other')->map->count();

        $missingEquipments = [];
        $totalMissingCount = 0;

        // 1. Kiểm tra Tivi
        $tvStd = $standardAllocations->first(fn ($s) => ($s->model?->category?->code ?? '') === 'tv');
        if ($tvStd || $standardAllocations->isNotEmpty()) {
            $actualTv = $equipmentsByCat['tv'] ?? 0;
            if ($actualTv < $tvSpaces) {
                $diff = $tvSpaces - $actualTv;
                $totalMissingCount += $diff;
                $missingEquipments[] = [
                    'model_id' => $tvStd?->equipment_model_id ?? 0,
                    'model_name' => $tvStd?->model?->name ?? 'Tivi & Màn hình',
                    'name' => 'Tivi & Màn hình',
                    'category_code' => 'tv',
                    'category_name' => 'Tivi & Màn hình',
                    'standard_quantity' => $tvSpaces,
                    'actual_quantity' => $actualTv,
                    'missing_quantity' => $diff,
                    'missing_count' => $diff,
                ];
            }
        }

        // 2. Kiểm tra TV Box & Đầu thu (Box TV)
        $boxStd = $standardAllocations->first(fn ($s) => ($s->model?->category?->code ?? '') === 'box');
        if ($boxStd || $standardAllocations->isNotEmpty()) {
            $actualBox = $equipmentsByCat['box'] ?? 0;
            if ($actualBox < $tvSpaces) {
                $diff = $tvSpaces - $actualBox;
                $totalMissingCount += $diff;
                $missingEquipments[] = [
                    'model_id' => $boxStd?->equipment_model_id ?? 0,
                    'model_name' => $boxStd?->model?->name ?? 'TV Box & Đầu thu',
                    'name' => 'TV Box & Đầu thu',
                    'category_code' => 'box',
                    'category_name' => 'TV Box & Đầu thu',
                    'standard_quantity' => $tvSpaces,
                    'actual_quantity' => $actualBox,
                    'missing_quantity' => $diff,
                    'missing_count' => $diff,
                ];
            }
        }

        // 3. Kiểm tra Remote
        $remoteStd = $standardAllocations->first(fn ($s) => ($s->model?->category?->code ?? '') === 'remote');
        if ($remoteStd || $standardAllocations->isNotEmpty()) {
            $actualRemote = $equipmentsByCat['remote'] ?? 0;
            if ($actualRemote < $tvSpaces) {
                $diff = $tvSpaces - $actualRemote;
                $totalMissingCount += $diff;
                $missingEquipments[] = [
                    'model_id' => $remoteStd?->equipment_model_id ?? 0,
                    'model_name' => $remoteStd?->model?->name ?? 'Điều khiển cầm tay',
                    'name' => 'Điều khiển cầm tay (Remote)',
                    'category_code' => 'remote',
                    'category_name' => 'Điều khiển cầm tay',
                    'standard_quantity' => $tvSpaces,
                    'actual_quantity' => $actualRemote,
                    'missing_quantity' => $diff,
                    'missing_count' => $diff,
                ];
            }
        }

        // 4. Kiểm tra Wifi Router
        if ($requiredWifi > 0) {
            $wifiStd = $standardAllocations->first(fn ($s) => ($s->model?->category?->code ?? '') === 'wifi');
            if ($wifiStd || $standardAllocations->isNotEmpty()) {
                $actualWifi = $equipmentsByCat['wifi'] ?? 0;
                if ($actualWifi < $requiredWifi) {
                    $diff = $requiredWifi - $actualWifi;
                    $totalMissingCount += $diff;
                    $missingEquipments[] = [
                        'model_id' => $wifiStd?->equipment_model_id ?? 0,
                        'model_name' => $wifiStd?->model?->name ?? 'Thiết bị mạng (Wifi Router)',
                        'name' => 'Thiết bị mạng (Wifi Router)',
                        'category_code' => 'wifi',
                        'category_name' => 'Thiết bị mạng (Wifi Router)',
                        'standard_quantity' => $requiredWifi,
                        'actual_quantity' => $actualWifi,
                        'missing_quantity' => $diff,
                        'missing_count' => $diff,
                    ];
                }
            }
        }

        // 5. Kiểm tra Điện thoại bàn (Mỗi 1 phòng cần 1 máy điện thoại)
        $phoneStd = $standardAllocations->first(fn ($s) => ($s->model?->category?->code ?? '') === 'phone');
        if ($phoneStd || $standardAllocations->isNotEmpty()) {
            $actualPhone = $equipmentsByCat['phone'] ?? 0;
            if ($actualPhone < 1) {
                $diff = 1 - $actualPhone;
                $totalMissingCount += $diff;
                $missingEquipments[] = [
                    'model_id' => $phoneStd?->equipment_model_id ?? 0,
                    'model_name' => $phoneStd?->model?->name ?? 'Điện thoại bàn',
                    'name' => 'Điện thoại bàn (Điện thoại phòng)',
                    'category_code' => 'phone',
                    'category_name' => 'Điện thoại bàn',
                    'standard_quantity' => 1,
                    'actual_quantity' => $actualPhone,
                    'missing_quantity' => $diff,
                    'missing_count' => $diff,
                ];
            }
        }

        // 6. Kiểm tra các danh mục khác (bỏ qua ac, fridge, appliance theo yêu cầu nghiệp vụ)
        foreach ($standardAllocations as $std) {
            $catCode = $std->model?->category?->code ?? '';
            if (in_array($catCode, ['tv', 'box', 'remote', 'wifi', 'phone', 'ac', 'fridge', 'appliance'])) {
                continue; // Đã đối soát theo định mức động ở trên hoặc đã bỏ định mức
            }

            $modelId = $std->equipment_model_id;
            $stdModel = $std->model;
            $requiredQty = (int) $std->standard_quantity;

            // Kiểm tra số lượng theo model hoặc theo danh mục nếu phòng đã có thiết bị cùng loại
            $actualQty = $actualModelCounts[$modelId] ?? 0;
            if ($actualQty === 0 && !empty($catCode) && isset($equipmentsByCat[$catCode])) {
                $actualQty = $equipmentsByCat[$catCode];
            }

            if ($actualQty < $requiredQty) {
                $diff = $requiredQty - $actualQty;
                $totalMissingCount += $diff;
                $missingEquipments[] = [
                    'model_id' => $modelId,
                    'model_name' => $stdModel?->name ?? 'Thiết bị chuẩn',
                    'name' => $stdModel?->name ?? 'Thiết bị chuẩn',
                    'category_code' => $catCode,
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

        // 7. Kiểm tra sự cố lan truyền (Cascade Issue) từ phòng phát Wifi nếu phòng này dùng ké Wifi
        $sharedWifiIssue = null;
        if ($wifiMode === 'shared' && $room->sharedWifiRoom) {
            $sourceEquipments = $room->sharedWifiRoom->equipments ?? collect();
            $sourceRouter = $sourceEquipments->first(fn ($item) => ($item->model?->category?->code ?? '') === 'wifi');

            if (!$sourceRouter) {
                $sharedWifiIssue = [
                    'type' => 'missing',
                    'status' => 'missing',
                    'source_room_id' => $room->sharedWifiRoom->id,
                    'source_room_number' => (string) $room->sharedWifiRoom->room_number,
                    'message' => 'Phòng phát Wifi (P.' . $room->sharedWifiRoom->room_number . ') chưa được lắp đặt Router!',
                ];
                $totalMissingCount++;
                $missingEquipments[] = [
                    'model_id' => 0,
                    'model_name' => 'Wifi Router (Dùng ké P.' . $room->sharedWifiRoom->room_number . ')',
                    'name' => 'Wifi ké P.' . $room->sharedWifiRoom->room_number . ' chưa có Router',
                    'category_code' => 'wifi',
                    'category_name' => 'Thiết bị mạng (Wifi Router)',
                    'standard_quantity' => 1,
                    'actual_quantity' => 0,
                    'missing_quantity' => 1,
                    'missing_count' => 1,
                    'is_cascade_error' => true,
                    'source_room_number' => (string) $room->sharedWifiRoom->room_number,
                    'error_message' => 'Phòng phát P.' . $room->sharedWifiRoom->room_number . ' chưa lắp đặt thiết bị phát Wifi!',
                ];
            } elseif ($sourceRouter->status === EquipmentStatusEnum::Broken) {
                $brokenReason = $sourceRouter->note
                    ?: ($sourceRouter->relationLoaded('maintenanceLogs') ? $sourceRouter->maintenanceLogs->first()?->issue_description : null)
                    ?: $sourceRouter->maintenanceLogs()->latest()->value('issue_description')
                    ?: 'Chưa có ghi chú lý do hỏng';

                $sharedWifiIssue = [
                    'type' => 'broken',
                    'status' => 'broken',
                    'source_room_id' => $room->sharedWifiRoom->id,
                    'source_room_number' => (string) $room->sharedWifiRoom->room_number,
                    'router_id' => $sourceRouter->id,
                    'router_name' => $sourceRouter->name,
                    'serial_number' => $sourceRouter->serial_number,
                    'broken_reason' => $brokenReason,
                    'message' => 'Mất sóng Wifi do Router tại phòng phát (P.' . $room->sharedWifiRoom->room_number . ') bị HƯ HỎNG!',
                ];
                $totalBrokenCount++;
                $missingEquipments[] = [
                    'model_id' => $sourceRouter->equipment_model_id ?? 0,
                    'model_name' => 'Wifi Router (Dùng ké P.' . $room->sharedWifiRoom->room_number . ')',
                    'name' => 'Wifi ké P.' . $room->sharedWifiRoom->room_number . ' đang HƯ HỎNG',
                    'category_code' => 'wifi',
                    'category_name' => 'Thiết bị mạng (Wifi Router)',
                    'standard_quantity' => 1,
                    'actual_quantity' => 0,
                    'missing_quantity' => 1,
                    'missing_count' => 1,
                    'is_cascade_error' => true,
                    'source_room_number' => (string) $room->sharedWifiRoom->room_number,
                    'error_message' => 'Router tại P.' . $room->sharedWifiRoom->room_number . ' (S/N: ' . ($sourceRouter->serial_number ?: 'N/A') . ') bị HƯ HỎNG (Lý do: "' . $brokenReason . '"), khiến phòng này mất mạng!',
                ];
            } elseif ($sourceRouter->status === EquipmentStatusEnum::Maintenance) {
                $maintenanceReason = $sourceRouter->note
                    ?: ($sourceRouter->relationLoaded('maintenanceLogs') ? $sourceRouter->maintenanceLogs->first()?->issue_description : null)
                    ?: $sourceRouter->maintenanceLogs()->latest()->value('issue_description')
                    ?: 'Đang bảo dưỡng kỹ thuật';

                $sharedWifiIssue = [
                    'type' => 'maintenance',
                    'status' => 'maintenance',
                    'source_room_id' => $room->sharedWifiRoom->id,
                    'source_room_number' => (string) $room->sharedWifiRoom->room_number,
                    'router_id' => $sourceRouter->id,
                    'router_name' => $sourceRouter->name,
                    'serial_number' => $sourceRouter->serial_number,
                    'broken_reason' => $maintenanceReason,
                    'message' => 'Wifi gián đoạn do Router tại phòng phát (P.' . $room->sharedWifiRoom->room_number . ') đang BẢO TRÌ!',
                ];
            }
        }

        $dependentRooms = $room->dependentWifiRooms ? $room->dependentWifiRooms->map(fn ($r) => [
            'id' => $r->id,
            'room_number' => (string) $r->room_number,
            'name' => $r->name,
        ])->values()->all() : [];

        $isCompliant = ($totalMissingCount === 0 && $totalBrokenCount === 0 && $equipments->isNotEmpty() && !$sharedWifiIssue);

        return [
            'id' => $room->id,
            'room_id' => $room->id,
            'room_number' => $room->room_number,
            'name' => $room->name,
            'block' => $room->block,
            'floor' => $room->floor,
            'room_type' => $room->roomType?->name ?? 'Phòng Tiêu chuẩn',
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
            'shared_wifi_issue' => $sharedWifiIssue,
            'dependent_rooms' => $dependentRooms,
            'compliance_status' => $isCompliant ? 'compliant' : 'non_compliant',
            'room' => [
                'id' => $room->id,
                'name' => $room->name,
                'room_number' => $room->room_number,
                'block' => $room->block,
                'floor' => $room->floor,
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
            'shared_wifi_issue' => $sharedWifiIssue,
            'dependent_rooms' => $dependentRooms,
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

    protected function normalizeMediaUrl(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        $url = str_replace('/laravel_hq/quandh-core/public', '', $url);
        $path = parse_url($url, PHP_URL_PATH);

        return $path ?: $url;
    }
}
