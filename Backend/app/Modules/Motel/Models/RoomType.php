<?php
namespace App\Modules\Motel\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;



class RoomType extends Model implements HasMedia
{
    use HasFactory;
    use InteractsWithMedia;

    protected static function newFactory()
    {
        return \Database\Factories\RoomTypeFactory::new();
    }

    protected $fillable = [
        'name',
        'hourly_rate',
        'daily_rate',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'hourly_rate' => 'decimal:2',
        'daily_rate' => 'decimal:2',
        'created_by' => 'integer',
        'updated_by' => 'integer',
    ];

    protected static function booted()
    {
        static::creating(fn ($room) => $room->created_by = $room->updated_by = auth()->id());
        static::updating(fn ($room) => $room->updated_by = auth()->id());
    }
}