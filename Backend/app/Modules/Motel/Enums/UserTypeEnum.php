<?php

namespace App\Modules\Motel\Enums;

/**
 * Phân loại đối tượng người dùng trong hệ thống Motel.
 */
enum UserTypeEnum: string
{
    case Employee = 'employee'; // Nhân viên (lễ tân, buồng phòng, quản lý)
    case Guest = 'guest';       // Khách hàng / khách thuê

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
            self::Employee => 'Nhân viên',
            self::Guest => 'Khách hàng',
        };
    }
}
