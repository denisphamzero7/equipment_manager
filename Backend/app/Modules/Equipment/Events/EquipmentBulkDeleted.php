<?php

namespace App\Modules\Equipment\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EquipmentBulkDeleted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $organizationId;
    public array $ids;
    public int $count;

    public function __construct(array $ids, int $organizationId = 1)
    {
        $this->organizationId = $organizationId;
        $this->ids = array_values(array_map('intval', $ids));
        $this->count = count($ids);
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("organization.{$this->organizationId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'equipment.bulk_deleted';
    }

    public function broadcastWith(): array
    {
        return [
            'type' => 'bulk_deleted',
            'message' => "Đã xóa {$this->count} thiết bị khỏi hệ thống.",
            'ids' => $this->ids,
            'count' => $this->count,
            'organization_id' => $this->organizationId,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
