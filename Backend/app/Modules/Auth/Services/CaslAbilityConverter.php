<?php

namespace App\Modules\Auth\Services;

/**
 * [1. MỤC ĐÍCH]
 * Chuyển đổi danh sách quyền hạn dạng chuỗi của Spatie Permission (ví dụ: 'equipments.index', 'rooms.store')
 * sang định dạng đối tượng Abilities chuẩn của thư viện phân quyền CASL phía Frontend Vue.js.
 *
 * Định dạng đầu ra:
 * [
 *   { "action": "index", "subject": "Equipment" },
 *   { "action": "store", "subject": "Equipment" },
 *   { "action": "show",  "subject": "Room" }
 * ]
 */
class CaslAbilityConverter
{
    /**
     * [1. MỤC ĐÍCH]
     * Chuyển danh sách permission chuỗi sang mảng các đối tượng ability cho CASL.
     *
     * [2. LUỒNG XỬ LÝ (FLOW)]
     * 1. Khởi tạo mảng rỗng `$abilities = []`.
     * 2. Duyệt qua từng permission trong mảng `$permissions`:
     *    - Kiểm tra chuỗi hợp lệ và có chứa dấu chấm phân cách `resource.action`.
     *    - Tách thành 2 phần: `$resource` (tài nguyên) và `$action` (hành động: index, show, create, store, edit, update, delete...).
     *    - Chuyển `$resource` từ dạng kebab/snake-case sang PascalCase làm tên `$subject` (ví dụ: 'room-equipments' -> 'RoomEquipment').
     *    - Thêm vào mảng ability: `['action' => $action, 'subject' => $subject]`.
     * 3. Trả về danh sách abilities.
     *
     * [3. NGUỒN GỌI (CALLERS)]
     * - `AuthService::getRolesAndPermissionsForOrganization()`
     * - `AuthController::me()`
     * - `AuthService::switchOrganization()`
     *
     * [4. THAM SỐ (PARAMETERS)]
     * @param array<string> $permissions Danh sách quyền dạng chuỗi (ví dụ: ["equipments.index", "equipments.store"])
     *
     * [5. GIÁ TRỊ TRẢ VỀ]
     * @return array<array{action: string, subject: string}> Mảng các object { action, subject }
     *
     * [6. VÍ DỤ SỬ DỤNG]
     * @example
     * ```php
     * $abilities = CaslAbilityConverter::toCaslAbilities(['equipments.index', 'equipments.delete']);
     * // Kết quả:
     * // [
     * //   ['action' => 'index', 'subject' => 'Equipment'],
     * //   ['action' => 'delete', 'subject' => 'Equipment']
     * // ]
     * ```
     */
    public static function toCaslAbilities(array $permissions): array
    {
        $abilities = [];

        foreach ($permissions as $permission) {
            if (! is_string($permission) || ! str_contains($permission, '.')) {
                continue;
            }

            [$resource, $action] = explode('.', $permission, 2);
            $subject = self::resourceToSubject($resource);

            $abilities[] = [
                'action' => $action,
                'subject' => $subject,
            ];
        }

        return $abilities;
    }

    /**
     * [1. MỤC ĐÍCH]
     * Chuyển đổi tên tài nguyên (kebab-case) sang Subject chuẩn PascalCase của CASL.
     *
     * [2. LUỒNG XỬ LÝ (FLOW)]
     * 1. Tách chuỗi theo dấu gạch ngang (`explode('-', $resource)`).
     * 2. Viết hoa chữ cái đầu của từng từ (`ucfirst(strtolower($part))`).
     * 3. Ghép nối các từ lại thành chuỗi không dấu cách.
     *
     * [4. THAM SỐ (PARAMETERS)]
     * @param string $resource Tên tài nguyên (ví dụ: 'room-equipments', 'roles')
     *
     * [5. GIÁ TRỊ TRẢ VỀ]
     * @return string Subject dạng PascalCase (ví dụ: 'RoomEquipment', 'Role')
     */
    protected static function resourceToSubject(string $resource): string
    {
        return collect(explode('-', $resource))
            ->map(fn (string $part) => ucfirst(strtolower($part)))
            ->implode('');
    }
}
