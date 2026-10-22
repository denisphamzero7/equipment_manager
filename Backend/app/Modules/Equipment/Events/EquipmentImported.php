<?php

namespace App\Modules\Equipment\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EquipmentImported implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $organizationId,
        public int $importedCount
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("organization.{$this->organizationId}")];
    }

    public function broadcastAs(): string
    {
        return 'equipment.imported';
    }

    public function broadcastWith(): array
    {
        return [
            'type' => 'imported',
            'imported_count' => $this->importedCount,
            'message' => "Đã nhập {$this->importedCount} thiết bị từ Excel.",
            'organization_id' => $this->organizationId,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
