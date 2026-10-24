<?php

namespace App\Modules\Motel\Events;

use App\Modules\Motel\Models\Room;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RoomChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $organizationId;
    public string $action;
    public ?array $roomData;
    public string $message;

    public function __construct(string $action, $room = null, ?string $message = null, ?int $organizationId = null)
    {
        $this->action = $action;
        $this->organizationId = (int) ($organizationId ?: ($room?->organization_id ?: (function_exists('getPermissionsTeamId') ? getPermissionsTeamId() : (\\App\\Modules\\Core\\Models\\Organization::value('id') ?? 1))););
        $this->message = $message ?: "Dữ liệu phòng vừa được cập nhật ($action).";

        if ($room instanceof Room) {
            $this->roomData = [
                'id' => $room->id,
                'name' => $room->name,
                'room_number' => $room->room_number,
                'block' => $room->block,
                'floor' => $room->floor,
                'status' => $room->status instanceof \BackedEnum ? $room->status->value : (string) $room->status,
                'room_type_id' => $room->room_type_id,
                'price' => $room->price,
            ];
        } elseif (is_array($room)) {
            $this->roomData = $room;
        } else {
            $this->roomData = null;
        }
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("organization.{$this->organizationId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'room.changed';
    }

    public function broadcastWith(): array
    {
        return [
            'type' => $this->action,
            'action' => $this->action,
            'message' => $this->message,
            'room' => $this->roomData,
            'organization_id' => $this->organizationId,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
