<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class EquipmentModuleDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoomTypeSeeder::class,
            EquipmentMasterSeeder::class,
            RoomStandardEquipmentSeeder::class,
            HotelRoomSeeder::class,
            RoomEquipmentSeeder::class,
            EquipmentMaintenanceLogSeeder::class,
        ]);
    }
}
