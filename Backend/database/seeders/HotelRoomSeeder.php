<?php

namespace Database\Seeders;

use App\Modules\Equipment\Models\RoomEquipment;
use App\Modules\Motel\Enums\RoomStatusEnum;
use App\Modules\Motel\Models\Room;
use Illuminate\Database\Seeder;

class HotelRoomSeeder extends Seeder
{
    public function run(): void
    {
        $orgId = \App\Modules\Core\Models\Organization::value('id') ?? 1;

        // Dữ liệu sơ đồ toàn bộ phòng Olalani Hotel (Block A, B, C, D, Condos 1, Condos 2, Penthouse-spa+Villa)
        $blocks = [
            'Block A' => [
                7 => [
                    ['room_number' => '726', 'col' => 1],
                    ['room_number' => '724', 'col' => 2],
                    ['room_number' => '722', 'col' => 3],
                    ['room_number' => '721', 'col' => 4],
                    ['room_number' => '723', 'col' => 5],
                    ['room_number' => '725', 'col' => 6],
                ],
                6 => [
                    ['room_number' => '626', 'col' => 1],
                    ['room_number' => '624', 'col' => 2],
                    ['room_number' => '622', 'col' => 3],
                    ['room_number' => '621', 'col' => 4],
                    ['room_number' => '623', 'col' => 5],
                    ['room_number' => '625', 'col' => 6],
                ],
                5 => [
                    ['room_number' => '526', 'col' => 1],
                    ['room_number' => '524', 'col' => 2],
                    ['room_number' => '522', 'col' => 3],
                    ['room_number' => '521', 'col' => 4],
                    ['room_number' => '523', 'col' => 5],
                    ['room_number' => '525', 'col' => 6],
                ],
                4 => [
                    ['room_number' => '426', 'col' => 1],
                    ['room_number' => '424', 'col' => 2],
                    ['room_number' => '422', 'col' => 3],
                    ['room_number' => '421', 'col' => 4],
                    ['room_number' => '423', 'col' => 5],
                    ['room_number' => '425', 'col' => 6],
                ],
                3 => [
                    ['room_number' => '326', 'col' => 1],
                    ['room_number' => '324', 'col' => 2],
                    ['room_number' => '322', 'col' => 3],
                    ['room_number' => '321', 'col' => 4],
                    ['room_number' => '323', 'col' => 5],
                    ['room_number' => '325', 'col' => 6],
                ],
                2 => [
                    ['room_number' => '226', 'col' => 1],
                    ['room_number' => '224', 'col' => 2],
                    ['room_number' => '222', 'col' => 3],
                    ['room_number' => '221', 'col' => 4],
                    ['room_number' => '223', 'col' => 5],
                    ['room_number' => '225', 'col' => 6],
                ],
                1 => [
                    ['room_number' => '126', 'col' => 1],
                    ['room_number' => '124', 'col' => 2],
                    ['room_number' => '122', 'col' => 3],
                    ['room_number' => '123', 'col' => 5],
                    ['room_number' => '125', 'col' => 6],
                ],
            ],
            'Block B' => [
                6 => [
                    ['room_number' => '627', 'col' => 1],
                    ['room_number' => '629', 'col' => 2],
                    ['room_number' => '631', 'col' => 3],
                    ['room_number' => '633', 'col' => 4],
                    ['room_number' => '635', 'col' => 5],
                    ['room_number' => '637', 'col' => 6],
                    ['room_number' => '639', 'col' => 7],
                ],
                5 => [
                    ['room_number' => '527', 'col' => 1],
                    ['room_number' => '529', 'col' => 2],
                    ['room_number' => '531', 'col' => 3],
                    ['room_number' => '533', 'col' => 4],
                    ['room_number' => '535', 'col' => 5],
                    ['room_number' => '537', 'col' => 6],
                    ['room_number' => '539', 'col' => 7],
                ],
                4 => [
                    ['room_number' => '427', 'col' => 1],
                    ['room_number' => '429', 'col' => 2],
                    ['room_number' => '431', 'col' => 3],
                    ['room_number' => '433', 'col' => 4],
                    ['room_number' => '435', 'col' => 5],
                    ['room_number' => '437', 'col' => 6],
                    ['room_number' => '439', 'col' => 7],
                ],
                3 => [
                    ['room_number' => '327', 'col' => 1],
                    ['room_number' => '329', 'col' => 2],
                    ['room_number' => '331', 'col' => 3],
                    ['room_number' => '333', 'col' => 4],
                    ['room_number' => '335', 'col' => 5],
                    ['room_number' => '337', 'col' => 6],
                    ['room_number' => '339', 'col' => 7],
                ],
                2 => [
                    ['room_number' => '227', 'col' => 1],
                    ['room_number' => '229', 'col' => 2],
                    ['room_number' => '231', 'col' => 3],
                    ['room_number' => '233', 'col' => 4],
                    ['room_number' => '235', 'col' => 5],
                    ['room_number' => '237', 'col' => 6],
                    ['room_number' => '239', 'col' => 7],
                ],
                1 => [
                    ['room_number' => '127', 'col' => 1],
                    ['room_number' => '129', 'col' => 2],
                    ['room_number' => '131', 'col' => 3],
                    ['room_number' => '135', 'col' => 5],
                    ['room_number' => '137', 'col' => 6],
                    ['room_number' => '139', 'col' => 7],
                ],
            ],
            'Block C' => [
                6 => [
                    ['room_number' => '641', 'col' => 1],
                    ['room_number' => '643', 'col' => 2],
                    ['room_number' => '645', 'col' => 3],
                    ['room_number' => '647', 'col' => 4],
                    ['room_number' => '649', 'col' => 5],
                    ['room_number' => '651', 'col' => 6],
                    ['room_number' => '653', 'col' => 7],
                ],
                5 => [
                    ['room_number' => '541', 'col' => 1],
                    ['room_number' => '543', 'col' => 2],
                    ['room_number' => '545', 'col' => 3],
                    ['room_number' => '547', 'col' => 4],
                    ['room_number' => '549', 'col' => 5],
                    ['room_number' => '551', 'col' => 6],
                    ['room_number' => '553', 'col' => 7],
                ],
                4 => [
                    ['room_number' => '441', 'col' => 1],
                    ['room_number' => '443', 'col' => 2],
                    ['room_number' => '445', 'col' => 3],
                    ['room_number' => '447', 'col' => 4],
                    ['room_number' => '449', 'col' => 5],
                    ['room_number' => '451', 'col' => 6],
                    ['room_number' => '453', 'col' => 7],
                ],
                3 => [
                    ['room_number' => '341', 'col' => 1],
                    ['room_number' => '343', 'col' => 2],
                    ['room_number' => '345', 'col' => 3],
                    ['room_number' => '347', 'col' => 4],
                    ['room_number' => '349', 'col' => 5],
                    ['room_number' => '351', 'col' => 6],
                    ['room_number' => '353', 'col' => 7],
                ],
                2 => [
                    ['room_number' => '241', 'col' => 1],
                    ['room_number' => '243', 'col' => 2],
                    ['room_number' => '245', 'col' => 3],
                    ['room_number' => '247', 'col' => 4],
                    ['room_number' => '249', 'col' => 5],
                    ['room_number' => '251', 'col' => 6],
                    ['room_number' => '253', 'col' => 7],
                ],
                1 => [
                    ['room_number' => '141', 'col' => 1],
                    ['room_number' => '143', 'col' => 2],
                    ['room_number' => '145', 'col' => 3],
                    ['room_number' => '147', 'col' => 4],
                    ['room_number' => '149', 'col' => 5],
                    ['room_number' => '151', 'col' => 6],
                    ['room_number' => '153', 'col' => 7],
                ],
            ],
            'Block D' => [
                6 => [
                    ['room_number' => '640', 'col' => 1],
                    ['room_number' => '638', 'col' => 2],
                    ['room_number' => '636', 'col' => 3],
                    ['room_number' => '634', 'col' => 4],
                    ['room_number' => '632', 'col' => 5],
                    ['room_number' => '630', 'col' => 6],
                    ['room_number' => '628', 'col' => 7],
                ],
                5 => [
                    ['room_number' => '540', 'col' => 1],
                    ['room_number' => '538', 'col' => 2],
                    ['room_number' => '536', 'col' => 3],
                    ['room_number' => '534', 'col' => 4],
                    ['room_number' => '532', 'col' => 5],
                    ['room_number' => '530', 'col' => 6],
                    ['room_number' => '528', 'col' => 7],
                ],
                4 => [
                    ['room_number' => '440', 'col' => 1],
                    ['room_number' => '438', 'col' => 2],
                    ['room_number' => '436', 'col' => 3],
                    ['room_number' => '434', 'col' => 4],
                    ['room_number' => '432', 'col' => 5],
                    ['room_number' => '430', 'col' => 6],
                    ['room_number' => '428', 'col' => 7],
                ],
                3 => [
                    ['room_number' => '340', 'col' => 1],
                    ['room_number' => '338', 'col' => 2],
                    ['room_number' => '336', 'col' => 3],
                    ['room_number' => '334', 'col' => 4],
                    ['room_number' => '332', 'col' => 5],
                    ['room_number' => '330', 'col' => 6],
                    ['room_number' => '328', 'col' => 7],
                ],
                2 => [
                    ['room_number' => '240', 'col' => 1],
                    ['room_number' => '238', 'col' => 2],
                    ['room_number' => '236', 'col' => 3],
                    ['room_number' => '234', 'col' => 4],
                    ['room_number' => '232', 'col' => 5],
                    ['room_number' => '230', 'col' => 6],
                    ['room_number' => '228', 'col' => 7],
                ],
                1 => [
                    ['room_number' => '140', 'col' => 1],
                    ['room_number' => '138', 'col' => 2],
                    ['room_number' => '136', 'col' => 3],
                    ['room_number' => '132', 'col' => 5],
                    ['room_number' => '130', 'col' => 6],
                    ['room_number' => '128', 'col' => 7],
                ],
            ],
            'Condos 1' => [
                6 => [
                    ['room_number' => '642', 'col' => 1],
                    ['room_number' => '644', 'col' => 2],
                    ['room_number' => '646', 'col' => 3],
                    ['room_number' => '648', 'col' => 4],
                    ['room_number' => '650', 'col' => 5],
                ],
                5 => [
                    ['room_number' => '542', 'col' => 1],
                    ['room_number' => '544', 'col' => 2],
                    ['room_number' => '546', 'col' => 3],
                    ['room_number' => '548', 'col' => 4],
                    ['room_number' => '550', 'col' => 5],
                ],
                4 => [
                    ['room_number' => '442', 'col' => 1],
                    ['room_number' => '444', 'col' => 2],
                    ['room_number' => '446', 'col' => 3],
                    ['room_number' => '448', 'col' => 4],
                    ['room_number' => '450', 'col' => 5],
                    ['room_number' => '452', 'col' => 6],
                    ['room_number' => '454', 'col' => 7],
                    ['room_number' => '456', 'col' => 8],
                    ['room_number' => '458', 'col' => 9],
                ],
                3 => [
                    ['room_number' => '342', 'col' => 1],
                    ['room_number' => '344', 'col' => 2],
                    ['room_number' => '346', 'col' => 3],
                    ['room_number' => '348', 'col' => 4],
                    ['room_number' => '350', 'col' => 5],
                    ['room_number' => '352', 'col' => 6],
                    ['room_number' => '354', 'col' => 7],
                    ['room_number' => '356', 'col' => 8],
                    ['room_number' => '358', 'col' => 9],
                ],
                2 => [
                    ['room_number' => '242', 'col' => 1],
                    ['room_number' => '244', 'col' => 2],
                    ['room_number' => '246', 'col' => 3],
                    ['room_number' => '248', 'col' => 4],
                    ['room_number' => '250', 'col' => 5],
                    ['room_number' => '252', 'col' => 6],
                    ['room_number' => '254', 'col' => 7],
                    ['room_number' => '256', 'col' => 8],
                    ['room_number' => '258', 'col' => 9],
                ],
                1 => [
                    ['room_number' => '142', 'col' => 1],
                    ['room_number' => '144', 'col' => 2],
                    ['room_number' => '146', 'col' => 3],
                    ['room_number' => '148', 'col' => 4],
                    ['room_number' => '150', 'col' => 5],
                    ['room_number' => '152', 'col' => 6],
                    ['room_number' => '154', 'col' => 7],
                    ['room_number' => '156', 'col' => 8],
                    ['room_number' => '158', 'col' => 9],
                ],
            ],
            'Condos 2' => [
                6 => [
                    ['room_number' => '655', 'col' => 1],
                    ['room_number' => '657', 'col' => 2],
                    ['room_number' => '659', 'col' => 3],
                    ['room_number' => '661', 'col' => 4],
                ],
                5 => [
                    ['room_number' => '555', 'col' => 1],
                    ['room_number' => '557', 'col' => 2],
                    ['room_number' => '559', 'col' => 3],
                    ['room_number' => '561', 'col' => 4],
                    ['room_number' => '563', 'col' => 5],
                ],
                4 => [
                    ['room_number' => '455', 'col' => 1],
                    ['room_number' => '457', 'col' => 2],
                    ['room_number' => '459', 'col' => 3],
                    ['room_number' => '461', 'col' => 4],
                    ['room_number' => '463', 'col' => 5],
                    ['room_number' => '465', 'col' => 6],
                    ['room_number' => '467', 'col' => 7],
                    ['room_number' => '469', 'col' => 8],
                ],
                3 => [
                    ['room_number' => '355', 'col' => 1],
                    ['room_number' => '357', 'col' => 2],
                    ['room_number' => '359', 'col' => 3],
                    ['room_number' => '361', 'col' => 4],
                    ['room_number' => '363', 'col' => 5],
                    ['room_number' => '365', 'col' => 6],
                    ['room_number' => '367', 'col' => 7],
                    ['room_number' => '369', 'col' => 8],
                ],
                2 => [
                    ['room_number' => '255', 'col' => 1],
                    ['room_number' => '257', 'col' => 2],
                    ['room_number' => '259', 'col' => 3],
                    ['room_number' => '261', 'col' => 4],
                    ['room_number' => '263', 'col' => 5],
                    ['room_number' => '265', 'col' => 6],
                    ['room_number' => '267', 'col' => 7],
                    ['room_number' => '269', 'col' => 8],
                ],
                1 => [
                    ['room_number' => '155', 'col' => 1],
                    ['room_number' => '157', 'col' => 2],
                    ['room_number' => '159', 'col' => 3],
                    ['room_number' => '161', 'col' => 4],
                    ['room_number' => '163', 'col' => 5],
                    ['room_number' => '165', 'col' => 6],
                    ['room_number' => '167', 'col' => 7],
                    ['room_number' => '169', 'col' => 8],
                ],
            ],
            'Penthouse-spa+Villa' => [
                1 => [
                    ['room_number' => 'PS', 'col' => 1],
                    ['room_number' => 'VA1', 'col' => 2],
                    ['room_number' => 'VA3', 'col' => 3],
                ],
            ],
        ];

        $validRoomKeys = [];

        foreach ($blocks as $blockName => $floors) {
            foreach ($floors as $floor => $rooms) {
                foreach ($rooms as $item) {
                    $roomNumber = (string) $item['room_number'];
                    $col = $item['col'];
                    $validRoomKeys[] = "{$blockName}_{$roomNumber}";

                    $desc = ($blockName === 'Penthouse-spa+Villa')
                        ? "{$blockName} ({$roomNumber})"
                        : "Phòng {$roomNumber} - {$blockName} Tầng {$floor}";

                    $name = ($blockName === 'Penthouse-spa+Villa')
                        ? $roomNumber
                        : "Phòng {$roomNumber}";

                    Room::updateOrCreate(
                        [
                            'organization_id' => $orgId,
                            'block' => $blockName,
                            'room_number' => $roomNumber,
                        ],
                        [
                            'name' => $name,
                            'description' => $desc,
                            'floor' => $floor,
                            'grid_row' => $floor,
                            'grid_col' => $col,
                            'room_type_id' => 1,
                            'status' => RoomStatusEnum::Ready->value,
                            'price' => 500000,
                        ]
                    );
                }
            }
        }

        // Dọn dẹp các phòng cũ không nằm trong danh sách sơ đồ khách sạn
        $existingRooms = Room::where('organization_id', $orgId)->get();
        foreach ($existingRooms as $r) {
            $key = "{$r->block}_{$r->room_number}";
            if (!in_array($key, $validRoomKeys)) {
                RoomEquipment::where('room_id', $r->id)->update(['room_id' => null]);
                $r->forceDelete();
            }
        }
    }
}
