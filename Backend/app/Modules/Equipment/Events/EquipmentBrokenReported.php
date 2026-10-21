<?php

namespace App\Modules\Equipment\Events;

use App\Modules\Equipment\Models\RoomEquipment;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EquipmentBrokenReported implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $organizationId;
    public array $payload;

    public function __construct(RoomEquipment $item, string $issueDescription, ?string $reporterName = null)
    {
        $this->organizationId = (int) ($item->organization_id ?: (function_exists('getPermissionsTeamId') ? getPermissionsTeamId() : 22));

        $this->payload = [
            'id' => $item->id,
            'serial_number' => $item->serial_number,
            'model_name' => $item->model?->name ?? 'Thiết bị',
            'room_id' => $item->room_id,
            'room_number' => $item->room?->room_number ?? 'Không xác định',
            'floor' => $item->room?->floor ?? 1,
            'issue_description' => $issueDescription,
            'reporter_name' => $reporterName ?: (auth()->user()?->name ?? 'Nhân viên'),
            'status' => 'broken',
            'created_at' => now()->toIso8601String(),
        ];
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("organization.{$this->organizationId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'equipment.broken_reported';
    }

    public function broadcastWith(): array
    {
        return [
            'type' => 'broken_reported',
            'urgent' => true,
            'title' => "CẢNH BÁO: Báo hỏng tại Phòng {$this->payload['room_number']}",
            'message' => "Thiết bị '{$this->payload['model_name']}' tại Phòng {$this->payload['room_number']} vừa được báo hỏng: {$this->payload['issue_description']}",
            'equipment' => $this->payload,
            'organization_id' => $this->organizationId,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
