<?php

namespace App\Modules\Equipment\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EquipmentBulkStatusChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $organizationId;
    public array $ids;
    public string $status;
    public string $statusLabel;
    public int $count;

    public function __construct(array $ids, string $status, int $organizationId = 1)
    {
        $this->organizationId = $organizationId;
        $this->ids = array_values(array_map('intval', $ids));
        $this->status = $status;
        $this->count = count($ids);

        $labels = [
            'good' => 'Hoạt động tốt',
            'broken' => 'Hư hỏng',
            'maintenance' => 'Đang bảo trì / sửa chữa',
            'in_stock' => 'Lưu trong kho',
            'disposed' => 'Đã thanh lý',
        ];
        $this->statusLabel = $labels[$status] ?? $status;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("organization.{$this->organizationId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'equipment.bulk_status_changed';
    }

    public function broadcastWith(): array
    {
        return [
            'type' => 'bulk_status_changed',
            'message' => "Đã cập nhật trạng thái cho {$this->count} thiết bị sang \"{$this->statusLabel}\".",
            'ids' => $this->ids,
            'status' => $this->status,
            'status_label' => $this->statusLabel,
            'count' => $this->count,
            'organization_id' => $this->organizationId,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
