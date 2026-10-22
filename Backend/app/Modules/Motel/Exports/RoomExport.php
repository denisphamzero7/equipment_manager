<?php

namespace App\Modules\Motel\Exports;

use App\Modules\Motel\Models\Room;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class RoomExport implements FromCollection, WithHeadings
{
    public function __construct(
        protected array $filters = [],
        protected int $organizationId = 0,
        protected bool $template = false
    ) {}

    public function collection(): Collection
    {
        if ($this->template) {
            return collect();
        }

        $query = Room::query()
            ->with('roomType')
            ->where('organization_id', $this->organizationId);

        foreach (['block', 'floor', 'room_type_id', 'status'] as $filter) {
            if (isset($this->filters[$filter]) && $this->filters[$filter] !== '') {
                $query->where($filter, $this->filters[$filter]);
            }
        }

        if (!empty($this->filters['search'])) {
            $search = $this->filters['search'];
            $query->where(function ($query) use ($search) {
                $query->where('room_number', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }

        return $query->orderBy('block')
            ->orderBy('floor')
            ->orderBy('room_number')
            ->get()
            ->map(fn (Room $room) => [
                'room_number' => $room->room_number,
                'name' => $room->name,
                'block' => $room->block,
                'floor' => $room->floor,
                'room_type_id' => $room->room_type_id,
                'room_type' => $room->roomType?->name,
                'status' => $room->status?->value ?? $room->status,
                'price' => $room->price,
                'bedroom_count' => $room->bedroom_count,
                'living_room_count' => $room->living_room_count,
                'wifi_mode' => $room->wifi_mode,
                'shared_wifi_room_id' => $room->shared_wifi_room_id,
                'shared_wifi_note' => $room->shared_wifi_note,
                'description' => $room->description,
            ]);
    }

    public function headings(): array
    {
        return [
            'Số phòng',
            'Tên phòng',
            'Khu vực',
            'Tầng',
            'ID hạng phòng',
            'Hạng phòng',
            'Trạng thái',
            'Giá',
            'Số phòng ngủ',
            'Số phòng khách',
            'Chế độ WiFi',
            'ID phòng WiFi dùng chung',
            'Ghi chú WiFi dùng chung',
            'Mô tả',
        ];
    }
}
