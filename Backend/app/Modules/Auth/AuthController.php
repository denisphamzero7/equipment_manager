<?php

namespace App\Modules\Auth;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Requests\ForgotPasswordRequest;
use App\Modules\Auth\Requests\LoginRequest as LoginRequest;
use App\Modules\Auth\Requests\ResetPasswordRequest;
use App\Modules\Auth\Requests\SwitchOrganizationRequest;
use App\Modules\Auth\Services\AuthService;
use App\Modules\Auth\Services\CaslAbilityConverter;
use App\Modules\Core\Resources\UserResource;
use Illuminate\Http\Request;

/**
 * @group Auth
 *
 * Xác thực: đăng nhập, đăng xuất, quên mật khẩu, đặt lại mật khẩu
 */
class AuthController extends Controller
{
    public function __construct(private AuthService $authService) {}

    /**
     * [1. MỤC ĐÍCH]
     * Tiếp nhận yêu cầu đăng nhập, kiểm tra thông tin tài khoản và trả về Access Token kèm danh sách cơ sở, quyền hạn.
     *
     * [2. LUỒNG XỬ LÝ (FLOW)]
     * 1. Validate định dạng `email`/tên đăng nhập và `password` qua `LoginRequest`.
     * 2. Gọi `$this->authService->login($request->email, $request->password)`.
     * 3. Xử lý phản hồi:
     *    - Nếu thất bại: Trả về 401 Unauthorized (sai mật khẩu) hoặc 403 Forbidden (tài khoản bị khóa).
     *    - Nếu thành công: Trả về mã 200 kèm `access_token`, thông tin user, danh sách tổ chức và CASL abilities.
     *
     * [3. NGUỒN GỌI (CALLERS)]
     * - Route: POST /api/auth/login
     * - Frontend: `useAuthStore::login()` tại form đăng nhập
     *
     * [4. THAM SỐ (PARAMETERS)]
     * @param \App\Modules\Auth\Requests\LoginRequest $request Chứa `email` và `password`
     *
     * [5. GIÁ TRỊ TRẢ VỀ]
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(LoginRequest $request)
    {
        $result = $this->authService->login($request->email, $request->password);

        if (! $result['ok']) {
            if ($result['type'] === 'unauthorized') {
                return $this->unauthorized($result['message']);
            }

            return $this->forbidden($result['message']);
        }

        return $this->success($result['data'], 'Đăng nhập thành công.');
    }

    /**
     * [1. MỤC ĐÍCH]
     * Lấy thông tin người dùng hiện tại kèm danh sách vai trò (roles) và quyền hạn (permissions) của tổ chức đang chọn.
     *
     * [2. LUỒNG XỬ LÝ (FLOW)]
     * 1. Lấy user từ Sanctum Auth: `$request->user()`.
     * 2. Lấy toàn bộ quyền hạn trực tiếp và từ vai trò của tổ chức hiện tại (`getAllPermissions`).
     * 3. Chuyển đổi quyền sang CASL abilities (`CaslAbilityConverter::toCaslAbilities`).
     * 4. Trả về thông tin user và quyền hạn để Frontend khởi tạo CASL khi F5 / tải lại trang.
     *
     * [3. NGUỒN GỌI (CALLERS)]
     * - Route: GET /api/auth/me (Yêu cầu header `X-Organization-Id`)
     * - Frontend Store: `useAuthStore::fetchMe()`
     *
     * [4. THAM SỐ (PARAMETERS)]
     * @param \Illuminate\Http\Request $request
     *
     * [5. GIÁ TRỊ TRẢ VỀ]
     * @return \Illuminate\Http\JsonResponse
     */
    public function me(Request $request)
    {
        $user = $request->user();
        // getAllPermissions() = direct + từ vai trò
        $permissions = $user->getAllPermissions()->pluck('name')->values()->unique()->all();

        return $this->success([
            'user' => (new UserResource($user))->resolve(),
            'roles' => $user->getRoleNames()->values()->all(),
            'permissions' => $permissions,
            'abilities' => CaslAbilityConverter::toCaslAbilities($permissions),
        ]);
    }

    /**
     * [1. MỤC ĐÍCH]
     * Đăng xuất khỏi hệ thống và xóa token phiên làm việc hiện tại.
     *
     * [2. LUỒNG XỬ LÝ (FLOW)]
     * 1. Gọi `$this->authService->logout($request->user())`.
     * 2. Xóa access token trong cơ sở dữ liệu.
     * 3. Trả về JSON thành công 200.
     *
     * [3. NGUỒN GỌI (CALLERS)]
     * - Route: POST /api/auth/logout
     * - Frontend Store: `useAuthStore::logout()`
     *
     * [4. THAM SỐ (PARAMETERS)]
     * @param \Illuminate\Http\Request $request
     *
     * [5. GIÁ TRỊ TRẢ VỀ]
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout(Request $request)
    {
        $this->authService->logout($request->user());

        return $this->success(null, 'Đã đăng xuất');
    }

    /**
     * [1. MỤC ĐÍCH]
     * Chuyển đổi tổ chức / cơ sở làm việc và tải lại toàn bộ quyền hạn tương ứng.
     *
     * [2. LUỒNG XỬ LÝ (FLOW)]
     * 1. Validate `organization_id` qua `SwitchOrganizationRequest`.
     * 2. Gọi `$this->authService->switchOrganization()` để kiểm tra quyền và lưu preference.
     * 3. Trả về quyền hạn và CASL abilities mới.
     *
     * [3. NGUỒN GỌI (CALLERS)]
     * - Route: POST /api/auth/switch-organization
     * - Frontend: Dropdown chọn khách sạn trên Header
     *
     * [4. THAM SỐ (PARAMETERS)]
     * @param \App\Modules\Auth\Requests\SwitchOrganizationRequest $request
     *
     * [5. GIÁ TRỊ TRẢ VỀ]
     * @return \Illuminate\Http\JsonResponse
     */
    public function switchOrganization(SwitchOrganizationRequest $request)
    {
        $result = $this->authService->switchOrganization($request->user(), (int) $request->organization_id);

        if (! $result['ok']) {
            return $this->forbidden($result['message']);
        }

        return $this->success($result['data'], 'Đã chuyển tổ chức làm việc.');
    }

    /**
     * [1. MỤC ĐÍCH]
     * Gửi link đặt lại mật khẩu vào địa chỉ email của người dùng.
     *
     * [2. LUỒNG XỬ LÝ (FLOW)]
     * 1. Validate `email` qua `ForgotPasswordRequest`.
     * 2. Gọi `$this->authService->forgotPassword($request->email)`.
     * 3. Trả về thông báo thành công hoặc lỗi 400.
     *
     * [3. NGUỒN GỌI (CALLERS)]
     * - Route: POST /api/auth/forgot-password
     * - Frontend: Form Quên mật khẩu
     *
     * [4. THAM SỐ (PARAMETERS)]
     * @param \App\Modules\Auth\Requests\ForgotPasswordRequest $request
     *
     * [5. GIÁ TRỊ TRẢ VỀ]
     * @return \Illuminate\Http\JsonResponse
     */
    public function forgotPassword(ForgotPasswordRequest $request)
    {
        $ok = $this->authService->forgotPassword($request->email);

        return $ok
            ? $this->success(null, 'Link reset đã được gửi vào Email')
            : $this->error('Không thể gửi mail', 400);
    }

    /**
     * [1. MỤC ĐÍCH]
     * Đặt mật khẩu mới bằng token xác thực nhận được từ email.
     *
     * [2. LUỒNG XỬ LÝ (FLOW)]
     * 1. Validate `email`, `password`, `password_confirmation`, `token` qua `ResetPasswordRequest`.
     * 2. Gọi `$this->authService->resetPassword()`.
     * 3. Trả về thông báo đổi mật khẩu thành công.
     *
     * [3. NGUỒN GỌI (CALLERS)]
     * - Route: POST /api/auth/reset-password
     * - Frontend: Form Đặt lại mật khẩu
     *
     * [4. THAM SỐ (PARAMETERS)]
     * @param \App\Modules\Auth\Requests\ResetPasswordRequest $request
     *
     * [5. GIÁ TRỊ TRẢ VỀ]
     * @return \Illuminate\Http\JsonResponse
     */
    public function resetPassword(ResetPasswordRequest $request)
    {
        $ok = $this->authService->resetPassword($request->email, $request->password, $request->token);

        return $ok
            ? $this->success(null, 'Mật khẩu đã được đặt lại')
            : $this->error('Không thể đặt lại mật khẩu', 400);
    }
}
