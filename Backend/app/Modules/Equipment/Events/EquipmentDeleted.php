<?php

namespace App\Modules\Equipment\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EquipmentDeleted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $organizationId;
    public int $equipmentId;
    public ?int $roomId;

    public function __construct(int $equipmentId, ?int $roomId = null, ?int $organizationId = null)
    {
        $this->equipmentId = $equipmentId;
        $this->roomId = $roomId;
        $this->organizationId = (int) ($organizationId ?: (function_exists('getPermissionsTeamId') ? getPermissionsTeamId() : 22));
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("organization.{$this->organizationId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'equipment.deleted';
    }

    public function broadcastWith(): array
    {
        return [
            'type' => 'deleted',
            'message' => "Thiết bị #{$this->equipmentId} đã bị xóa khỏi hệ thống.",
            'equipment_id' => $this->equipmentId,
            'room_id' => $this->roomId,
            'organization_id' => $this->organizationId,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
