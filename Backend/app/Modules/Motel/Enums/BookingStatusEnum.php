<?php

namespace App\Modules\Motel\Enums;

/**
 * Trạng thái của đơn đặt / thuê phòng.
 */
enum BookingStatusEnum: string
{
    case Pending = 'pending';       // Đã đặt trước, chưa check-in
    case CheckedIn = 'checked_in';   // Khách đã nhận phòng, đang lưu trú
    case Completed = 'completed';   // Đã trả phòng & hoàn tất thủ tục
    case Cancelled = 'cancelled';   // Đã hủy đặt phòng

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
            self::Pending => 'Chờ nhận phòng',
            self::CheckedIn => 'Đang lưu trú',
            self::Completed => 'Đã hoàn tất',
            self::Cancelled => 'Đã hủy',
        };
    }

    /**
     * Mã màu hiển thị trên giao diện Flutter / Web.
     */
    public function badgeColor(): string
    {
        return match ($this) {
            self::Pending => '#3B82F6',   // Xanh dương (Blue)
            self::CheckedIn => '#10B981', // Xanh lá (Green)
            self::Completed => '#6B7280', // Xám (Gray)
            self::Cancelled => '#EF4444', // Đỏ (Red)
        };
    }
}
