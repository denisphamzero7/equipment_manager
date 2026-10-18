<?php

namespace App\Modules\Motel\Enums;

/**
 * Trạng thái phòng trong hệ thống nhà nghỉ / khách sạn.
 */
enum RoomStatusEnum: string
{
    case Ready = 'ready';             // Sẵn sàng đón khách
    case Occupied = 'occupied';       // Đang có khách lưu trú
    case NotReady = 'not_ready';       // Phòng bẩn, chờ buồng phòng dọn dẹp
    case Maintenance = 'maintenance'; // Đang sửa chữa / bảo trì thiết bị

    /**
     * Danh sách tất cả các giá trị string.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Rule validation dùng trong FormRequest: in:ready,occupied,not_ready,maintenance
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
            self::Ready => 'Sẵn sàng đón khách',
            self::Occupied => 'Đang có khách',
            self::NotReady => 'Chờ dọn dẹp',
            self::Maintenance => 'Đang bảo trì',
        };
    }

    /**
     * Mã màu hiển thị trên giao diện Flutter / Web.
     */
    public function badgeColor(): string
    {
        return match ($this) {
            self::Ready => '#10B981',       // Xanh lá (Green)
            self::Occupied => '#EF4444',    // Đỏ (Red)
            self::NotReady => '#F59E0B',    // Vàng cam (Orange)
            self::Maintenance => '#6B7280', // Xám (Gray)
        };
    }
}
