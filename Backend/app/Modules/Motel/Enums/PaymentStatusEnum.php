<?php

namespace App\Modules\Motel\Enums;

/**
 * Trạng thái thanh toán của đơn thuê phòng.
 */
enum PaymentStatusEnum: string
{
    case Unpaid = 'unpaid';   // Chưa thanh toán
    case Partial = 'partial'; // Đã thanh toán một phần / cọc
    case Paid = 'paid';       // Đã thanh toán đủ toàn bộ hóa đơn

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
            self::Unpaid => 'Chưa thanh toán',
            self::Partial => 'Đã cọc / một phần',
            self::Paid => 'Đã thanh toán đủ',
        };
    }
}
