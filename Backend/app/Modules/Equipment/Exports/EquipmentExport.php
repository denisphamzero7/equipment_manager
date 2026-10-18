<?php

namespace App\Modules\Equipment\Exports;

use App\Modules\Equipment\Models\RoomEquipment;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class EquipmentExport implements FromCollection, WithHeadings
{
    public function __construct(
        protected array $filters = []
    ) {}

    public function collection()
    {
        $query = RoomEquipment::with(['model.category', 'room']);

        if (!empty($this->filters['status'])) {
            $query->where('status', $this->filters['status']);
        }
        if (!empty($this->filters['room_id'])) {
            $query->where('room_id', $this->filters['room_id']);
        }
        if (!empty($this->filters['equipment_category_id'])) {
            $query->whereHas('model', fn ($q) => $q->where('equipment_category_id', $this->filters['equipment_category_id']));
        }
        if (!empty($this->filters['search'])) {
            $s = $this->filters['search'];
            $query->where(fn ($q) => $q->where('serial_number', 'like', "%{$s}%")->orWhere('position_note', 'like', "%{$s}%"));
        }

        $items = $query->orderBy('room_id')->orderBy('id', 'desc')->get();

        return $items->map(fn ($item) => [
            'id' => $item->id,
            'serial_number' => $item->serial_number,
            'category_name' => $item->model?->category?->name ?? 'N/A',
            'model_name' => $item->model?->name ?? 'N/A',
            'brand' => $item->model?->brand ?? 'N/A',
            'room_name' => $item->room ? "P.{$item->room->room_number} ({$item->room->block})" : 'Trong kho',
            'position_note' => $item->position_note ?? '',
            'status' => $item->status?->label() ?? $item->status,
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
            'Phòng đặt',
            'Vị trí trong phòng',
            'Trạng thái',
            'Ghi chú tình trạng',
            'Ngày lắp đặt',
            'Ngày tạo',
        ];
    }
}
