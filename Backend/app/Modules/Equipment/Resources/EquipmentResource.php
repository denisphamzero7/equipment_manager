<?php

namespace App\Modules\Equipment\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EquipmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'serial_number' => $this->serial_number,
            'status' => $this->status?->value ?? $this->status,
            'status_label' => $this->status?->label() ?? $this->status,
            'status_color' => $this->status?->color() ?? 'secondary',
            'position_note' => $this->position_note,
            'note' => $this->note,
            'installed_at' => $this->installed_at?->format('Y-m-d H:i:s'),
            'photo_url' => $this->getFirstMediaUrl('equipment_photos') ? str_replace('/laravel_hq/quandh-core/public', '', $this->getFirstMediaUrl('equipment_photos')) : null,
            'photos' => $this->getMedia('equipment_photos')->map(fn ($m) => [
                'id' => $m->id,
                'url' => str_replace('/laravel_hq/quandh-core/public', '', $m->getUrl()),
                'file_name' => $m->file_name,
            ]),
            'broken_photos' => $this->getMedia('broken_photos')->map(fn ($m) => [
                'id' => $m->id,
                'url' => str_replace('/laravel_hq/quandh-core/public', '', $m->getUrl()),
                'file_name' => $m->file_name,
            ]),
            'model' => $this->whenLoaded('model', fn () => [
                'id' => $this->model->id,
                'name' => $this->model->name,
                'brand' => $this->model->brand,
                'model_code' => $this->model->model_code,
                'specs' => $this->model->specs,
                'category' => $this->model->relationLoaded('category') && $this->model->category ? [
                    'id' => $this->model->category->id,
                    'name' => $this->model->category->name,
                    'code' => $this->model->category->code,
                    'icon' => $this->model->category->icon,
                ] : null,
            ]),
            'room' => $this->whenLoaded('room', fn () => [
                'id' => $this->room->id,
                'name' => $this->room->name,
                'room_number' => $this->room->room_number,
                'block' => $this->room->block,
                'floor' => $this->room->floor,
            ]),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
