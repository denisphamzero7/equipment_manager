<?php

namespace App\Modules\Equipment\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Danh mục nhóm thiết bị (TV, Remote, Box, Wifi Router, Điều hòa, ...).
 */
class EquipmentCategory extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'equipment_categories';

    protected $fillable = [
        'name',
        'code',
        'icon',
        'description',
        'sort_order',
        'organization_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'sort_order' => 'integer',
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

    /**
     * Danh sách các model/quy cách thuộc danh mục này.
     */
    public function models(): HasMany
    {
        return $this->hasMany(EquipmentModel::class, 'equipment_category_id');
    }
}
