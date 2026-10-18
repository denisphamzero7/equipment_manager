<?php

namespace Tests\Unit;

use App\Modules\Equipment\Enums\EquipmentStatusEnum;
use Tests\TestCase;

class EquipmentStatusEnumTest extends TestCase
{
    public function test_values_returns_expected_statuses(): void
    {
        $expected = [
            'good',
            'broken',
            'maintenance',
            'lost',
            'in_stock',
            'disposed',
        ];

        $this->assertSame($expected, EquipmentStatusEnum::values());
    }

    public function test_rule_returns_valid_validation_rule(): void
    {
        $this->assertSame('in:good,broken,maintenance,lost,in_stock,disposed', EquipmentStatusEnum::rule());
    }

    public function test_labels_and_colors_match_correctly(): void
    {
        $this->assertSame('Hoạt động tốt', EquipmentStatusEnum::Good->label());
        $this->assertSame('success', EquipmentStatusEnum::Good->color());

        $this->assertSame('Hư hỏng', EquipmentStatusEnum::Broken->label());
        $this->assertSame('danger', EquipmentStatusEnum::Broken->color());

        $this->assertSame('Trong kho', EquipmentStatusEnum::InStock->label());
        $this->assertSame('info', EquipmentStatusEnum::InStock->color());
    }
}
