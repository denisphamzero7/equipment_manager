<?php

namespace App\Modules\Motel\Enums;

/**
 * Trạng thái công việc của nhân viên.
 */
enum EmployeeStatusEnum: string
{
    case Active = 'active';     // Đang làm việc bình thường
    case Inactive = 'inactive'; // Đang tạm ngưng / nghỉ phép
    case Resigned = 'resigned'; // Đã thôi việc

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
            self::Active => 'Đang làm việc',
            self::Inactive => 'Tạm ngưng',
            self::Resigned => 'Đã nghỉ việc',
        };
    }
}
