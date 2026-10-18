<?php

namespace App\Modules\Motel\Enums;

/**
 * Phương thức thanh toán giao dịch.
 */
enum PaymentMethodEnum: string
{
    case Cash = 'cash';         // Tiền mặt
    case Transfer = 'transfer'; // Chuyển khoản ngân hàng / Quét mã VietQR
    case Card = 'card';         // Quẹt thẻ máy POS

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
            self::Cash => 'Tiền mặt',
            self::Transfer => 'Chuyển khoản (VietQR)',
            self::Card => 'Quẹt thẻ (POS)',
        };
    }
}
