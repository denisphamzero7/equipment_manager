<?php

namespace App\Modules\Equipment\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EquipmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $mediaCollection = $this->relationLoaded('media') ? $this->media : collect();
        $eqPhotos = $mediaCollection->where('collection_name', 'equipment_photos');
        $brPhotos = $mediaCollection->where('collection_name', 'broken_photos');
        $firstPhoto = $eqPhotos->first();
        $photoUrl = $firstPhoto ? str_replace('/laravel_hq/quandh-core/public', '', $firstPhoto->getUrl()) : null;
        $thumbUrl = ($firstPhoto && $firstPhoto->hasGeneratedConversion('thumb'))
            ? str_replace('/laravel_hq/quandh-core/public', '', $firstPhoto->getUrl('thumb'))
            : $photoUrl;

        return [
            'id' => $this->id,
            'room_id' => $this->room_id,
            'serial_number' => $this->serial_number,
            'status' => $this->status?->value ?? $this->status,
            'status_label' => $this->status?->label() ?? $this->status,
            'status_color' => $this->status?->color() ?? 'secondary',
            'position_note' => $this->position_note,
            'note' => $this->note,
            'installed_at' => $this->installed_at?->format('Y-m-d H:i:s'),
            'photo_url' => $photoUrl,
            'thumb_url' => $thumbUrl,
            'photos' => $eqPhotos->map(fn ($m) => [
                'id' => $m->id,
                'url' => str_replace('/laravel_hq/quandh-core/public', '', $m->getUrl()),
                'file_name' => $m->file_name,
            ])->values()->all(),
            'broken_photos' => $brPhotos->map(fn ($m) => [
                'id' => $m->id,
                'url' => str_replace('/laravel_hq/quandh-core/public', '', $m->getUrl()),
                'file_name' => $m->file_name,
            ])->values()->all(),
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
            'maintenance_logs' => $this->whenLoaded('maintenanceLogs', fn () => $this->maintenanceLogs->map(fn ($log) => [
                'id' => $log->id,
                'issue_description' => $log->issue_description,
                'status' => $log->status?->value ?? $log->status,
                'status_label' => $log->status instanceof \App\Modules\Equipment\Enums\MaintenanceStatusEnum ? $log->status->label() : ($log->status ?? 'Chờ tiếp nhận'),
                'cost' => (float) ($log->cost ?? 0),
                'reported_by' => $log->reported_by,
                'reported_by_name' => $log->relationLoaded('reportedByUser') ? $log->reportedByUser?->name : null,
                'resolved_by' => $log->resolved_by,
                'resolved_by_name' => $log->relationLoaded('resolvedByUser') ? $log->resolvedByUser?->name : null,
                'resolved_at' => $log->resolved_at?->format('Y-m-d H:i:s'),
                'created_at' => $log->created_at?->format('Y-m-d H:i:s'),
                'updated_at' => $log->updated_at?->format('Y-m-d H:i:s'),
            ])->values()->all()),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
