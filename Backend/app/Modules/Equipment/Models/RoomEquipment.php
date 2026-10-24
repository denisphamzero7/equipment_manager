<?php

namespace App\Modules\Equipment\Models;

use App\Modules\Equipment\Enums\EquipmentStatusEnum;
use App\Modules\Motel\Models\Room;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Từng cá thể thiết bị cụ thể trong khách sạn.
 */
class RoomEquipment extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia, SoftDeletes;

    protected $table = 'room_equipments';

    protected $fillable = [
        'serial_number',
        'equipment_model_id',
        'room_id',
        'status',
        'position_note',
        'note',
        'installed_at',
        'organization_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'equipment_model_id' => 'integer',
        'room_id' => 'integer',
        'status' => EquipmentStatusEnum::class,
        'installed_at' => 'datetime',
        'organization_id' => 'integer',
    ];

    protected static function booted()
    {
        static::creating(function ($item) {
            if (auth()->check()) {
                $item->created_by = $item->updated_by = auth()->id();
            }
            if (empty($item->serial_number)) {
                $item->serial_number = 'EQ-'.strtoupper(uniqid());
            }
        });
        static::updating(function ($item) {
            if (auth()->check()) {
                $item->updated_by = auth()->id();
            }
        });
    }

    public function model(): BelongsTo
    {
        return $this->belongsTo(EquipmentModel::class, 'equipment_model_id');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'room_id');
    }

    public function maintenanceLogs(): HasMany
    {
        return $this->hasMany(EquipmentMaintenanceLog::class, 'room_equipment_id')->latest('id');
    }

    /**
     * Đăng ký media collections cho ảnh chụp thiết bị và ảnh chụp hiện trường hư hỏng.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('equipment_photos');
        $this->addMediaCollection('broken_photos');
    }

    /**
     * Tự động tạo ảnh Thumbnail kích thước nhỏ phục vụ hiển thị danh sách bảng nhanh chóng.
     */
    public function registerMediaConversions(?\Spatie\MediaLibrary\MediaCollections\Models\Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(200)
            ->height(200)
            ->nonQueued();
    }
}
