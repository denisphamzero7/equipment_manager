<?php

namespace App\Modules\Equipment\Events;

use App\Modules\Equipment\Models\RoomEquipment;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EquipmentCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $organizationId;
    public array $equipment;

    public function __construct(RoomEquipment $item, ?string $clientRequestId = null)
    {
        $this->organizationId = (int) ($item->organization_id ?: (function_exists('getPermissionsTeamId') ? getPermissionsTeamId() : 1));
        $this->equipment = [
            'id' => $item->id,
            'serial_number' => $item->serial_number,
            'model_name' => $item->model?->name ?? 'Thiết bị mới',
            'category_name' => $item->model?->category?->name ?? 'Khác',
            'room_id' => $item->room_id,
            'room_number' => $item->room?->room_number,
            'status' => $item->status instanceof \BackedEnum ? $item->status->value : (string) $item->status,
            'position_note' => $item->position_note,
            'created_at' => $item->created_at?->toIso8601String(),
            'client_request_id' => $clientRequestId,
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
        return 'equipment.created';
    }

    public function broadcastWith(): array
    {
        return [
            'type' => 'created',
            'message' => "Thiết bị mới #{$this->equipment['id']} ({$this->equipment['model_name']}) vừa được tạo.",
            'equipment' => $this->equipment,
            'organization_id' => $this->organizationId,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
