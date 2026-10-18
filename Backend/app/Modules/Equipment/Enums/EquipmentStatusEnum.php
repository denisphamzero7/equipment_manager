<?php

namespace App\Modules\Equipment\Enums;

/**
 * Trạng thái của thiết bị trong khách sạn.
 */
enum EquipmentStatusEnum: string
{
    case Good = 'good';                // Hoạt động tốt
    case Broken = 'broken';            // Bị hư hỏng
    case Maintenance = 'maintenance';  // Đang bảo trì / sửa chữa
    case Lost = 'lost';                // Thất lạc / Báo mất / Thiếu
    case InStock = 'in_stock';          // Trong kho (chưa gán phòng)
    case Disposed = 'disposed';        // Đã thanh lý

    /**
     * Danh sách giá trị để validate.
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Rule validation Laravel.
     */
    public static function rule(): string
    {
        return 'in:'.implode(',', self::values());
    }

    /**
     * Nhãn tiếng Việt hiển thị trên giao diện.
     */
    public function label(): string
    {
        return match ($this) {
            self::Good => 'Hoạt động tốt',
            self::Broken => 'Hư hỏng',
            self::Maintenance => 'Đang bảo trì',
            self::Lost => 'Thất lạc / Báo mất',
            self::InStock => 'Trong kho',
            self::Disposed => 'Đã thanh lý',
        };
    }

    /**
     * Màu sắc biểu thị trạng thái (dùng cho frontend Map View / Badges).
     */
    public function color(): string
    {
        return match ($this) {
            self::Good => 'success',      // Xanh lá
            self::Broken => 'danger',      // Đỏ
            self::Maintenance => 'warning', // Cam / Vàng
            self::Lost => 'secondary',    // Xám đậm
            self::InStock => 'info',       // Xanh dương
            self::Disposed => 'dark',     // Đen
        };
    }
}
