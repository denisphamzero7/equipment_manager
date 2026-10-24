<?php

namespace App\Modules\Equipment\Models;

use App\Modules\Equipment\Enums\MaintenanceStatusEnum;
use App\Modules\Motel\Models\Room;
use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lịch sử báo hỏng và bảo trì / sửa chữa thiết bị.
 */
class EquipmentMaintenanceLog extends Model
{
    use HasFactory;

    protected $table = 'equipment_maintenance_logs';

    protected $fillable = [
        'room_equipment_id',
        'room_id',
        'issue_description',
        'status',
        'cost',
        'reported_by',
        'resolved_by',
        'resolved_at',
    ];

    protected $casts = [
        'room_equipment_id' => 'integer',
        'room_id' => 'integer',
        'status' => MaintenanceStatusEnum::class,
        'cost' => 'decimal:2',
        'reported_by' => 'integer',
        'resolved_by' => 'integer',
        'resolved_at' => 'datetime',
    ];

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(RoomEquipment::class, 'room_equipment_id');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'room_id');
    }

    public function reportedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function resolvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
