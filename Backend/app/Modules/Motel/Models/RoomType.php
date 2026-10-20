<?php
namespace App\Modules\Motel\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class RoomType extends Model implements HasMedia
{
    use HasFactory;
    use InteractsWithMedia;
    use SoftDeletes;

    protected static function newFactory()
    {
        return \Database\Factories\RoomTypeFactory::new();
    }

    protected $fillable = [
        'name',
        'code',
        'description',
        'hourly_rate',
        'daily_rate',
        'organization_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'hourly_rate' => 'decimal:2',
        'daily_rate' => 'decimal:2',
        'organization_id' => 'integer',
        'created_by' => 'integer',
        'updated_by' => 'integer',
    ];

    public function rooms()
    {
        return $this->hasMany(Room::class, 'room_type_id');
    }

    public function standardEquipments()
    {
        return $this->hasMany(\App\Modules\Equipment\Models\RoomStandardEquipment::class, 'room_type_id');
    }

    protected static function booted()
    {
        static::creating(fn ($item) => $item->created_by = $item->updated_by = auth()->id());
        static::updating(fn ($item) => $item->updated_by = auth()->id());
    }
}
