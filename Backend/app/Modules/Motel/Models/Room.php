<?php
namespace App\Modules\Motel\Models;

use App\Modules\Motel\Enums\RoomStatusEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

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
        'bedroom_count',
        'living_room_count',
        'wifi_mode',
        'shared_wifi_room_id',
        'shared_wifi_note',
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
        'room_number' => 'string',
        'bedroom_count' => 'integer',
        'living_room_count' => 'integer',
        'shared_wifi_room_id' => 'integer',
        'room_type_id' => 'integer',
        'floor' => 'integer',
        'grid_row' => 'integer',
        'grid_col' => 'integer',
        'status' => RoomStatusEnum::class,
    ];

    public function roomType()
    {
        return $this->belongsTo(\App\Modules\Motel\Models\RoomType::class, 'room_type_id');
    }

    public function equipments()
    {
        return $this->hasMany(\App\Modules\Equipment\Models\RoomEquipment::class, 'room_id');
    }

    public function standardEquipments()
    {
        return $this->hasMany(\App\Modules\Equipment\Models\RoomStandardEquipment::class, 'room_type_id', 'room_type_id');
    }

    public function sharedWifiRoom()
    {
        return $this->belongsTo(\App\Modules\Motel\Models\Room::class, 'shared_wifi_room_id');
    }

    public function scopeFilter($query, array $filters)
    {
        $query->when(!empty($filters['search']), function ($q) use ($filters) {
            $s = $filters['search'];
            $q->where(function ($sub) use ($s) {
                $sub->where('room_number', 'like', "%{$s}%")
                    ->orWhere('name', 'like', "%{$s}%");
            });
        })->when(!empty($filters['block']), function ($q) use ($filters) {
            $q->where('block', $filters['block']);
        })->when(isset($filters['floor']) && $filters['floor'] !== '' && $filters['floor'] !== null, function ($q) use ($filters) {
            $q->where('floor', $filters['floor']);
        })->when(!empty($filters['status']), function ($q) use ($filters) {
            $q->where('status', $filters['status']);
        })->when(!empty($filters['room_type_id']), function ($q) use ($filters) {
            $q->where('room_type_id', $filters['room_type_id']);
        })->when(!empty($filters['organization_id']), function ($q) use ($filters) {
            $q->where('organization_id', $filters['organization_id']);
        });
    }

    protected static function booted()
    {
        static::creating(function ($room) {
            if (auth()->check()) {
                $room->created_by = $room->updated_by = auth()->id();
            }
            if (empty($room->name) && !empty($room->room_number)) {
                $room->name = 'Phòng ' . $room->room_number;
            }
        });
        static::updating(function ($room) {
            if (auth()->check()) {
                $room->updated_by = auth()->id();
            }
        });
    }
}
