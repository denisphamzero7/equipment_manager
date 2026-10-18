<?php

namespace App\Modules\Motel\Enums;

/**
 * Phân loại khoản tiền thu/chi trong đơn thuê phòng.
 */
enum PaymentTypeEnum: string
{
    case Deposit = 'deposit';                 // Đặt cọc giữ phòng
    case FullPrepayment = 'full_prepayment'; // Trả trước toàn bộ tiền phòng
    case CheckoutPayment = 'checkout_payment'; // Thanh toán khi làm thủ tục trả phòng
    case Refund = 'refund';                   // Hoàn tiền thừa lại cho khách

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
            self::Deposit => 'Đặt cọc',
            self::FullPrepayment => 'Trả trước toàn bộ',
            self::CheckoutPayment => 'Thanh toán trả phòng',
            self::Refund => 'Hoàn trả tiền thừa',
        };
    }
}
