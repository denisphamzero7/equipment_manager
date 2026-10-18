<?php

namespace App\Modules\Equipment\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Định mức số lượng thiết bị tiêu chuẩn theo loại phòng (Room Type).
 */
class RoomStandardEquipment extends Model
{
    use HasFactory;

    protected $table = 'room_standard_equipments';

    protected $fillable = [
        'room_type_id',
        'equipment_model_id',
        'standard_quantity',
        'organization_id',
    ];

    protected $casts = [
        'room_type_id' => 'integer',
        'equipment_model_id' => 'integer',
        'standard_quantity' => 'integer',
        'organization_id' => 'integer',
    ];

    public function model(): BelongsTo
    {
        return $this->belongsTo(EquipmentModel::class, 'equipment_model_id');
    }
}
