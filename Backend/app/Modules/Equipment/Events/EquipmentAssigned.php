<?php

namespace App\Modules\Equipment\Events;

use App\Modules\Equipment\Models\RoomEquipment;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EquipmentAssigned implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $organizationId;
    public array $payload;

    public function __construct(RoomEquipment $item, ?int $fromRoomId = null)
    {
        $this->organizationId = (int) ($item->organization_id ?: (function_exists('getPermissionsTeamId') ? getPermissionsTeamId() : 22));

        $this->payload = [
            'id' => $item->id,
            'serial_number' => $item->serial_number,
            'model_name' => $item->model?->name ?? 'Thiết bị',
            'from_room_id' => $fromRoomId,
            'to_room_id' => $item->room_id,
            'to_room_number' => $item->room?->room_number,
            'status' => $item->status instanceof \BackedEnum ? $item->status->value : (string) $item->status,
            'position_note' => $item->position_note,
            'assigned_at' => now()->toIso8601String(),
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
        return 'equipment.assigned';
    }

    public function broadcastWith(): array
    {
        $msg = $this->payload['to_room_id']
            ? "Thiết bị #{$this->payload['id']} ({$this->payload['model_name']}) đã được gán vào Phòng {$this->payload['to_room_number']}."
            : "Thiết bị #{$this->payload['id']} ({$this->payload['model_name']}) đã được thu hồi về kho.";

        return [
            'type' => 'assigned',
            'message' => $msg,
            'equipment' => $this->payload,
            'organization_id' => $this->organizationId,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
