<?php

namespace App\Modules\Equipment\Events;

use App\Modules\Equipment\Models\RoomEquipment;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EquipmentStatusChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $organizationId;
    public array $payload;

    public function __construct(RoomEquipment $item, ?string $oldStatus = null)
    {
        $this->organizationId = (int) ($item->organization_id ?: (function_exists('getPermissionsTeamId') ? getPermissionsTeamId() : 22));
        $newStatus = $item->status instanceof \BackedEnum ? $item->status->value : (string) $item->status;

        $this->payload = [
            'id' => $item->id,
            'serial_number' => $item->serial_number,
            'model_name' => $item->model?->name ?? 'Thiết bị',
            'room_id' => $item->room_id,
            'room_number' => $item->room?->room_number,
            'floor' => $item->room?->floor ?? 1,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'note' => $item->note,
            'updated_at' => now()->toIso8601String(),
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
        return 'equipment.status_changed';
    }

    public function broadcastWith(): array
    {
        return [
            'type' => 'status_changed',
            'message' => "Thiết bị #{$this->payload['id']} ({$this->payload['model_name']}) đã đổi trạng thái sang: {$this->payload['new_status']}.",
            'equipment' => $this->payload,
            'organization_id' => $this->organizationId,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
