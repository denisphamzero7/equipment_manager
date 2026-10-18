<?php
namespace App\Modules\Motel\Services;

use App\Modules\Core\Services\MediaService as MediaService;
use App\Modules\Motel\Enums\RoomStatusEnum;
use App\Modules\Motel\Models\Room as RoomModel;
use App\Modules\Motel\Models\RoomType as RoomTypeModel;

class RoomService
{
    public function __construct(private MediaService $mediaService)
    {
    }

    public function stats(array $filters): array
    {
        $base = RoomModel::filter($filters);

        return [
            'total' => (clone $base)->count(),
            'ready' => (clone $base)->where('status', RoomStatusEnum::Ready->value)->count(),
            'occupied' => (clone $base)->where('status', RoomStatusEnum::Occupied->value)->count(),
            'not_ready' => (clone $base)->where('status', RoomStatusEnum::NotReady->value)->count(),
            'maintenance' => (clone $base)->where('status', RoomStatusEnum::Maintenance->value)->count(),
        ];
    }

    public function index(array $filters, int $limit)
    {
        return RoomModel::with(['roomType', 'organization'])
            ->filter($filters)
            ->paginate($limit);
    }
    public function getAllRooms()
    {
        return RoomModel::all();
    }

    public function getRoomById($id)
    {
        return RoomModel::findOrFail($id);
    }

    public function createRoom(array $data)
    {
        return RoomModel::create($data);
    }

    public function updateRoom($id, array $data)
    {
        $room = RoomModel::findOrFail($id);
        $room->update($data);
        return $room;
    }

    public function deleteRoom($id)
    {
        $room = RoomModel::findOrFail($id);
        $room->delete();
        return true;
    }
}