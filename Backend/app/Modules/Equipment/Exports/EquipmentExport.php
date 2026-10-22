<?php

namespace App\Modules\Equipment\Exports;

use App\Modules\Equipment\Models\RoomEquipment;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class EquipmentExport implements FromCollection, WithHeadings
{
    public function __construct(
        protected array $filters = [],
        protected ?int $organizationId = null,
        protected bool $template = false
    ) {}

    public function collection(): Collection
    {
        if ($this->template) {
            return collect();
        }

        $query = RoomEquipment::with(['model.category', 'room']);

        if ($this->organizationId) {
            $query->where('organization_id', $this->organizationId);
        }
        if (!empty($this->filters['status'])) {
            $query->where('status', $this->filters['status']);
        }
        if (!empty($this->filters['room_id'])) {
            $query->where('room_id', $this->filters['room_id']);
        }
        if (!empty($this->filters['equipment_category_id'])) {
            $query->whereHas('model', fn ($q) => $q->where('equipment_category_id', $this->filters['equipment_category_id']));
        }
        if (!empty($this->filters['block'])) {
            if ($this->filters['block'] === '__stock__') {
                $query->whereNull('room_id');
            } else {
                $query->whereHas('room', fn ($q) => $q->where('block', $this->filters['block']));
            }
        }
        if (isset($this->filters['floor']) && $this->filters['floor'] !== '' && $this->filters['floor'] !== null) {
            $query->whereHas('room', fn ($q) => $q->where('floor', (int) $this->filters['floor']));
        }
        if (!empty($this->filters['room_number'])) {
            $query->whereHas('room', fn ($q) => $q->where('room_number', 'like', '%'.$this->filters['room_number'].'%'));
        }
        if (!empty($this->filters['search'])) {
            $s = $this->filters['search'];
            $query->where(function ($q) use ($s) {
                $q->where('serial_number', 'like', "%{$s}%")
                    ->orWhere('position_note', 'like', "%{$s}%")
                    ->orWhere('note', 'like', "%{$s}%")
                    ->orWhereHas('model', fn ($mq) => $mq->where('name', 'like', "%{$s}%")->orWhere('brand', 'like', "%{$s}%"))
                    ->orWhereHas('room', fn ($rq) => $rq->where('room_number', 'like', "%{$s}%")->orWhere('name', 'like', "%{$s}%"));
            });
        }

        $items = $query->orderBy('room_id')->orderBy('id', 'desc')->get();

        return $items->map(fn ($item) => [
            'id' => $item->id,
            'serial_number' => $item->serial_number,
            'category_name' => $item->model?->category?->name ?? 'N/A',
            'model_name' => $item->model?->name ?? 'N/A',
            'brand' => $item->model?->brand ?? 'N/A',
            'room_number' => $item->room?->room_number ?? '',
            'room_name' => $item->room ? "P.{$item->room->room_number} ({$item->room->block})" : 'Trong kho',
            'position_note' => $item->position_note ?? '',
            'status' => $item->status?->value ?? $item->status,
            'note' => $item->note ?? '',
            'installed_at' => $item->installed_at?->format('d/m/Y H:i:s') ?? '',
            'created_at' => $item->created_at?->format('d/m/Y H:i:s'),
        ]);
    }

    public function headings(): array
    {
        return [
            'ID',
            'Mã Serial',
            'Danh mục',
            'Mẫu thiết bị',
            'Thương hiệu',
            'Số phòng',
            'Phòng đặt',
            'Vị trí trong phòng',
            'Trạng thái',
            'Ghi chú tình trạng',
            'Ngày lắp đặt',
            'Ngày tạo',
        ];
    }
}
