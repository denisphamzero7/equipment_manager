<?php

namespace Database\Seeders;

use App\Modules\Equipment\Enums\EquipmentStatusEnum;
use App\Modules\Equipment\Models\EquipmentMaintenanceLog;
use App\Modules\Equipment\Models\RoomEquipment;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class EquipmentMaintenanceLogSeeder extends Seeder
{
    public function run(): void
    {
        $problemEquipments = RoomEquipment::whereIn('status', [
            EquipmentStatusEnum::Broken->value,
            EquipmentStatusEnum::Maintenance->value,
        ])->get();

        foreach ($problemEquipments as $eq) {
            $status = ($eq->status === EquipmentStatusEnum::Maintenance) ? 'in_progress' : 'pending';
            $cost = ($eq->status === EquipmentStatusEnum::Maintenance) ? 350000 : null;

            EquipmentMaintenanceLog::updateOrCreate(
                [
                    'room_equipment_id' => $eq->id,
                    'status' => $status,
                ],
                [
                    'room_id' => $eq->room_id,
                    'issue_description' => $eq->note ?? 'Thiết bị gặp sự cố kỹ thuật trong quá trình khách sử dụng phòng.',
                    'cost' => $cost,
                    'reported_by' => 1,
                    'resolved_by' => null,
                    'resolved_at' => null,
                    'created_at' => Carbon::now()->subDays(rand(1, 5)),
                ]
            );
        }
    }
}
