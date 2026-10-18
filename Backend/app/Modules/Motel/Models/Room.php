<?php
namespace App\Modules\Motel\Models;

use App\Modules\Motel\Enums\RoomStatusEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Room extends Model implements HasMedia
{
    use HasFactory;
    use InteractsWithMedia;
    use SoftDeletes;

    protected static function newFactory()
    {
        return \Database\Factories\RoomFactory::new();
    }

    protected $fillable = [
        'name',
        'description',
        'room_number',
        'block',
        'floor',
        'grid_row',
        'grid_col',
        'room_type_id',
        'status',
        'price',
        'organization_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'organization_id' => 'integer',
        'room_number' => 'integer',
        'room_type_id' => 'integer',
        'floor' => 'integer',
        'grid_row' => 'integer',
        'grid_col' => 'integer',
        'status' => RoomStatusEnum::class,
    ];

    public function equipments()
    {
        return $this->hasMany(\App\Modules\Equipment\Models\RoomEquipment::class, 'room_id');
    }

    public function standardEquipments()
    {
        return $this->hasMany(\App\Modules\Equipment\Models\RoomStandardEquipment::class, 'room_type_id', 'room_type_id');
    }

    protected static function booted()
    {
        static::creating(fn ($room) => $room->created_by = $room->updated_by = auth()->id());
        static::updating(fn ($room) => $room->updated_by = auth()->id());
    }
}