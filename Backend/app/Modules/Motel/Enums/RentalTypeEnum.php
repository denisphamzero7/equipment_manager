<?php

namespace App\Modules\Motel\Enums;

/**
 * Hình thức thuê phòng.
 */
enum RentalTypeEnum: string
{
    case Hourly = 'hourly';       // Thuê theo giờ
    case Daily = 'daily';         // Thuê theo ngày
    case Overnight = 'overnight'; // Thuê qua đêm

    /**
     * Danh sách giá trị để validate.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Rule validation dùng trong FormRequest.
     */
    public static function rule(): string
    {
        return 'in:'.implode(',', self::values());
    }

    /**
     * Nhãn hiển thị tiếng Việt.
     */
    public function label(): string
    {
        return match ($this) {
            self::Hourly => 'Thuê theo giờ',
            self::Daily => 'Thuê theo ngày',
            self::Overnight => 'Thuê qua đêm',
        };
    }
}
