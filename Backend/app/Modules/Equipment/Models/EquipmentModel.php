<?php

namespace App\Modules\Equipment\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Mẫu mã / quy cách chi tiết (Ví dụ: TV Samsung 32", TV LG 40", FPT Box, Remote Sony...).
 */
class EquipmentModel extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'equipment_models';

    protected $fillable = [
        'equipment_category_id',
        'name',
        'brand',
        'model_code',
        'specs',
        'organization_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'equipment_category_id' => 'integer',
        'specs' => 'array',
        'organization_id' => 'integer',
    ];

    protected static function booted()
    {
        static::creating(function ($item) {
            if (auth()->check()) {
                $item->created_by = $item->updated_by = auth()->id();
            }
        });
        static::updating(function ($item) {
            if (auth()->check()) {
                $item->updated_by = auth()->id();
            }
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(EquipmentCategory::class, 'equipment_category_id');
    }

    public function equipments(): HasMany
    {
        return $this->hasMany(RoomEquipment::class, 'equipment_model_id');
    }

    public function standardAllocations(): HasMany
    {
        return $this->hasMany(RoomStandardEquipment::class, 'equipment_model_id');
    }
}
