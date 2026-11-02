<?php

namespace App\Modules\Auth\Services;

use App\Modules\Core\Enums\UserStatusEnum;
use App\Modules\Core\Models\Organization;
use App\Modules\Core\Models\User;
use App\Modules\Core\Resources\UserResource;
use App\Modules\Core\Services\UserPreferenceService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

class AuthService
{
    public function __construct(private UserPreferenceService $userPreferenceService) {}

    /**
     * [1. MỤC ĐÍCH]
     * Xác thực thông tin đăng nhập của người dùng qua Email hoặc Tên đăng nhập (Username), cấp Sanctum Token và phân quyền cơ sở.
     *
     * [2. LUỒNG XỬ LÝ (FLOW)]
     * 1. Tìm bản ghi `User` khớp với `email` hoặc `user_name` = `$login`.
     * 2. Kiểm tra mật khẩu mã hóa qua `Hash::check($password, $user->password)`.
     *    - Nếu sai: Trả về lỗi `unauthorized` ("Thông tin đăng nhập không chính xác").
     * 3. Kiểm tra trạng thái tài khoản: Nếu khác `active` -> Trả về lỗi `forbidden` ("Tài khoản của bạn đã bị khóa").
     * 4. Khởi tạo Personal Access Token của Laravel Sanctum: `$user->createToken('auth_token')->plainTextToken`.
     * 5. Lấy danh sách các cơ sở / tổ chức (`organizations`) mà người dùng này được cấp quyền truy cập.
     * 6. Xác định `current_organization_id`:
     *    - Ưu tiên cấu hình đã lưu trong `user_preferences`.
     *    - Nếu người dùng chỉ có đúng 1 tổ chức -> Tự động chọn và lưu vào preference.
     * 7. Tải toàn bộ vai trò (`roles`), quyền hạn (`permissions`) và định dạng CASL (`abilities`) tương ứng với tổ chức đã chọn.
     * 8. Trả về mảng dữ liệu hoàn chỉnh để lưu trữ trên client (Pinia Store & LocalStorage).
     *
     * [3. NGUỒN GỌI (CALLERS)]
     * - API: POST /api/auth/login
     * - Controller: App\Modules\Auth\AuthController::login()
     * - Frontend Store: `useAuthStore::login()` tại form đăng nhập `Frontend/src/modules/auth/views/login/index.vue`
     *
     * [4. THAM SỐ (PARAMETERS)]
     * @param string $login Email hoặc Tên đăng nhập
     * @param string $password Mật khẩu dạng plain text
     *
     * [5. GIÁ TRỊ TRẢ VỀ]
     * @return array Cấu trúc: ['ok' => bool, 'data' => array{access_token, user, available_organizations, current_organization_id, roles, permissions, abilities}] hoặc ['ok' => false, 'type', 'message']
     *
     * [6. VÍ DỤ SỬ DỤNG]
     * @example
     * ```php
     * $result = $authService->login('admin@hotel.com', 'password123');
     * if ($result['ok']) {
     *     $token = $result['data']['access_token'];
     * }
     * ```
     */
    public function login(string $login, string $password): array
    {
        $user = User::where('email', $login)
            ->orWhere('user_name', $login)
            ->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            return [
                'ok' => false,
                'type' => 'unauthorized',
                'message' => 'Thông tin đăng nhập không chính xác',
            ];
        }

        if ($user->status !== UserStatusEnum::Active->value) {
            return [
                'ok' => false,
                'type' => 'forbidden',
                'message' => 'Tài khoản của bạn đã bị khóa',
            ];
        }

        $token = $user->createToken('auth_token')->plainTextToken;
        $organizations = $this->getAccessibleOrganizations($user);
        $accessibleIds = array_column($organizations, 'id');

        if ($organizations === []) {
            $this->userPreferenceService->clearCurrentOrganizationId($user);
            $currentOrganizationId = null;
        } else {
            $currentOrganizationId = $this->resolveCurrentOrganizationIdForLogin(
                $user,
                $organizations,
                $accessibleIds
            );
        }

        $rolesAndPermissions = $this->getRolesAndPermissionsForOrganization($user, $currentOrganizationId);

        return [
            'ok' => true,
            'data' => [
                'access_token' => $token,
                'token_type' => 'Bearer',
                'user' => (new UserResource($user))->resolve(),
                'available_organizations' => $organizations,
                'current_organization_id' => $currentOrganizationId,
                'roles' => $rolesAndPermissions['roles'],
                'permissions' => $rolesAndPermissions['permissions'],
                'abilities' => $rolesAndPermissions['abilities'],
            ],
        ];
    }

    /**
     * [1. MỤC ĐÍCH]
     * Hủy bỏ Token hiện tại của phiên làm việc khi người dùng đăng xuất.
     *
     * [2. LUỒNG XỬ LÝ (FLOW)]
     * 1. Lấy Access Token đang xác thực từ request: `$user->currentAccessToken()`.
     * 2. Thực hiện xóa bản ghi token khỏi bảng `personal_access_tokens`.
     *
     * [3. NGUỒN GỌI (CALLERS)]
     * - API: POST /api/auth/logout
     * - Controller: App\Modules\Auth\AuthController::logout()
     * - Frontend Store: `useAuthStore::logout()`
     *
     * [4. THAM SỐ (PARAMETERS)]
     * @param \App\Modules\Core\Models\User $user Thực thể người dùng đang đăng nhập
     *
     * [5. GIÁ TRỊ TRẢ VỀ]
     * @return void
     */
    public function logout($user): void
    {
        $user->currentAccessToken()->delete();
    }

    /**
     * [1. MỤC ĐÍCH]
     * Tạo và gửi liên kết đặt lại mật khẩu qua thư điện tử.
     *
     * [2. LUỒNG XỬ LÝ (FLOW)]
     * 1. Sử dụng Password Broker của Laravel: `Password::sendResetLink(['email' => $email])`.
     * 2. Trả về true nếu email gửi thành công (`Password::RESET_LINK_SENT`).
     *
     * [3. NGUỒN GỌI (CALLERS)]
     * - API: POST /api/auth/forgot-password
     * - Controller: App\Modules\Auth\AuthController::forgotPassword()
     *
     * [4. THAM SỐ (PARAMETERS)]
     * @param string $email Địa chỉ email đã đăng ký
     *
     * [5. GIÁ TRỊ TRẢ VỀ]
     * @return bool
     */
    public function forgotPassword(string $email): bool
    {
        return Password::sendResetLink(['email' => $email]) === Password::RESET_LINK_SENT;
    }

    /**
     * [1. MỤC ĐÍCH]
     * Xác thực Token và lưu mật khẩu mới cho người dùng.
     *
     * [2. LUỒNG XỬ LÝ (FLOW)]
     * 1. Gọi `Password::reset` kiểm tra token hợp lệ trong bảng `password_resets`.
     * 2. Hash mật khẩu mới bằng `Hash::make($newPassword)` và lưu vào bảng `users`.
     * 3. Trả về true nếu đổi mật khẩu thành công.
     *
     * [3. NGUỒN GỌI (CALLERS)]
     * - API: POST /api/auth/reset-password
     * - Controller: App\Modules\Auth\AuthController::resetPassword()
     *
     * [4. THAM SỐ (PARAMETERS)]
     * @param string $email Email tài khoản
     * @param string $password Mật khẩu mới
     * @param string $token Token xác thực từ email
     *
     * [5. GIÁ TRỊ TRẢ VỀ]
     * @return bool
     */
    public function resetPassword(string $email, string $password, string $token): bool
    {
        $status = Password::reset(
            ['email' => $email, 'password' => $password, 'token' => $token],
            function (User $user, string $newPassword) {
                $user->forceFill(['password' => Hash::make($newPassword)])->save();
            }
        );

        return $status === Password::PASSWORD_RESET;
    }

    /**
     * [1. MỤC ĐÍCH]
     * Chuyển đổi ngữ cảnh làm việc sang một Tổ chức / Chi nhánh / Khách sạn khác.
     *
     * [2. LUỒNG XỬ LÝ (FLOW)]
     * 1. Kiểm tra tổ chức có tồn tại và đang ở trạng thái 'active'.
     * 2. Kiểm tra người dùng có quyền truy cập vào tổ chức này hay không (`hasOrganizationAccess`).
     *    - Nếu không: trả về lỗi `forbidden`.
     * 3. Tải lại toàn bộ vai trò và quyền hạn tương ứng của tổ chức mới (`getRolesAndPermissionsForOrganization`).
     * 4. Lưu lại ID tổ chức vào bảng tùy chọn cá nhân `user_preferences` để nhớ cho lần đăng nhập tiếp theo.
     * 5. Trả về thông tin tổ chức, vai trò, quyền và CASL abilities mới.
     *
     * [3. NGUỒN GỌI (CALLERS)]
     * - API: POST /api/auth/switch-organization
     * - Controller: App\Modules\Auth\AuthController::switchOrganization()
     * - Frontend Store: `useAuthStore::selectOrganization(orgId)` khi chọn cơ sở trên thanh Header
     *
     * [4. THAM SỐ (PARAMETERS)]
     * @param \App\Modules\Core\Models\User $user Người dùng hiện tại
     * @param int $organizationId ID tổ chức cần chuyển tới
     *
     * [5. GIÁ TRỊ TRẢ VỀ]
     * @return array Kết quả chuyển đổi kèm permissions và abilities mới
     */
    public function switchOrganization(User $user, int $organizationId): array
    {
        $organization = Organization::query()
            ->whereKey($organizationId)
            ->where('status', 'active')
            ->first();

        if (! $organization) {
            return [
                'ok' => false,
                'type' => 'forbidden',
                'message' => 'Tổ chức không hợp lệ hoặc đã ngừng hoạt động.',
            ];
        }

        if (! $this->hasOrganizationAccess((int) $user->id, (int) $organization->id)) {
            return [
                'ok' => false,
                'type' => 'forbidden',
                'message' => 'Bạn không có quyền truy cập tổ chức đã chọn.',
            ];
        }

        $rolesAndPermissions = $this->getRolesAndPermissionsForOrganization($user, (int) $organization->id);

        $this->userPreferenceService->setCurrentOrganizationId($user, (int) $organization->id);

        return [
            'ok' => true,
            'data' => [
                'current_organization_id' => (int) $organization->id,
                'current_organization' => [
                    'id' => (int) $organization->id,
                    'name' => $organization->name,
                    'description' => $organization->description,
                ],
                'roles' => $rolesAndPermissions['roles'],
                'permissions' => $rolesAndPermissions['permissions'],
                'abilities' => $rolesAndPermissions['abilities'],
            ],
        ];
    }

    protected function getAccessibleOrganizations(User $user): array
    {
        $tableNames = config('permission.table_names');
        $columnNames = config('permission.column_names');
        $modelMorphKey = $columnNames['model_morph_key'] ?? 'model_id';
        $teamForeignKey = $columnNames['team_foreign_key'] ?? 'organization_id';
        $modelType = User::class;
        $userId = (int) $user->id;

        return Organization::query()
            ->where('status', 'active')
            ->where(function ($query) use ($tableNames, $modelMorphKey, $teamForeignKey, $modelType, $userId) {
                foreach (['model_has_roles', 'model_has_permissions'] as $membershipTable) {
                    $queryMethod = $membershipTable === 'model_has_roles' ? 'whereExists' : 'orWhereExists';
                    $query->{$queryMethod}(function ($membershipQuery) use ($tableNames, $membershipTable, $modelMorphKey, $teamForeignKey, $modelType, $userId) {
                        $membershipQuery
                            ->selectRaw('1')
                            ->from($tableNames[$membershipTable] ?? $membershipTable)
                            ->whereColumn($teamForeignKey, 'organizations.id')
                            ->where($modelMorphKey, $userId)
                            ->where('model_type', $modelType);
                    });
                }
            })
            ->orderBy('name')
            ->get(['id', 'name', 'description'])
            ->map(fn (Organization $organization) => [
                'id' => (int) $organization->id,
                'name' => $organization->name,
                'description' => $organization->description,
            ])
            ->values()
            ->all();
    }
    // Trả danh sách chi tiết của tổ chức có quyền truy câp
    protected function getAccessibleOrganizationIds(int $userId): array
    {
        $tableNames = config('permission.table_names');
        $columnNames = config('permission.column_names');
        $modelMorphKey = $columnNames['model_morph_key'] ?? 'model_id';
        $teamForeignKey = $columnNames['team_foreign_key'] ?? 'organization_id';
        $modelType = \App\Modules\Core\Models\User::class;

        $roleOrgIds = DB::table($tableNames['model_has_roles'] ?? 'model_has_roles')
            ->where($modelMorphKey, $userId)
            ->where('model_type', $modelType)
            ->whereNotNull($teamForeignKey)
            ->select($teamForeignKey.' as organization_id');

        $permissionOrgIds = DB::table($tableNames['model_has_permissions'] ?? 'model_has_permissions')
            ->where($modelMorphKey, $userId)
            ->where('model_type', $modelType)
            ->whereNotNull($teamForeignKey)
            ->select($teamForeignKey.' as organization_id');

        return $roleOrgIds
            ->union($permissionOrgIds)
            ->pluck('organization_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    protected function hasOrganizationAccess(int $userId, int $organizationId): bool
    {
        return in_array($organizationId, $this->getAccessibleOrganizationIds($userId), true);
    }

    /**
     * Xác định tổ chức hiện tại khi đăng nhập: ưu tiên bản ghi user_preferences;
     * nếu không hợp lệ thì xóa preference; nếu chỉ có một tổ chức thì tự gán và lưu.
     */
    protected function resolveCurrentOrganizationIdForLogin(User $user, array $organizations, array $accessibleIds): ?int
    {
        $preferredId = $this->userPreferenceService->getCurrentOrganizationId($user);

        if ($preferredId !== null) {
            if (in_array($preferredId, $accessibleIds, true)) {
                return $preferredId;
            }
            $this->userPreferenceService->clearCurrentOrganizationId($user);
        }

        if (count($organizations) === 1) {
            $onlyId = (int) $organizations[0]['id'];
            $this->userPreferenceService->setCurrentOrganizationId($user, $onlyId);

            return $onlyId;
        }

        return null;
    }

    /**
     * Lấy danh sách vai trò và quyền hạn của user trong tổ chức, dùng cho Vue Casl.
     */
    protected function getRolesAndPermissionsForOrganization(User $user, ?int $organizationId): array
    {
        if ($organizationId === null) {
            return ['roles' => [], 'permissions' => [], 'abilities' => []];
        }

        setPermissionsTeamId($organizationId);
        $user->unsetRelation('roles');
        $user->unsetRelation('permissions');

        // getAllPermissions() = direct + từ vai trò; getPermissionNames() chỉ direct
        $permissions = $user->getAllPermissions()->pluck('name')->values()->unique()->all();

        return [
            'roles' => $user->getRoleNames()->values()->all(),
            'permissions' => $permissions,
            'abilities' => CaslAbilityConverter::toCaslAbilities($permissions),
        ];
    }
}
