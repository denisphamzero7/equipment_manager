<?php

namespace App\Modules\Equipment\Enums;

/**
 * Trạng thái xử lý sự cố / bảo trì thiết bị.
 */
enum MaintenanceStatusEnum: string
{
    case Pending = 'pending';          // Chờ xử lý / Đã tiếp nhận
    case InProgress = 'in_progress';    // Đang sửa chữa
    case Completed = 'completed';      // Đã sửa xong / Hoạt động lại
    case Unfixable = 'unfixable';      // Không thể sửa / Đề xuất thanh lý

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function rule(): string
    {
        return 'in:'.implode(',', self::values());
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Chờ tiếp nhận',
            self::InProgress => 'Đang sửa chữa',
            self::Completed => 'Đã khắc phục xong',
            self::Unfixable => 'Không thể sửa',
        };
    }
}
