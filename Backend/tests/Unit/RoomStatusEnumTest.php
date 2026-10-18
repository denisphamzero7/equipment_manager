<?php

namespace Tests\Unit;

use App\Modules\Motel\Enums\RoomStatusEnum;
use App\Modules\Motel\Models\Room;
use PHPUnit\Framework\TestCase;

class RoomStatusEnumTest extends TestCase
{
    public function test_values_returns_expected_statuses(): void
    {
        $this->assertSame(['ready', 'occupied', 'not_ready', 'maintenance'], RoomStatusEnum::values());
    }

    public function test_rule_returns_validation_rule(): void
    {
        $this->assertSame('in:ready,occupied,not_ready,maintenance', RoomStatusEnum::rule());
    }

    public function test_model_casts_status_to_enum(): void
    {
        $room = new Room(['status' => RoomStatusEnum::Ready->value]);

        $this->assertSame(RoomStatusEnum::Ready, $room->status);
    }
}
