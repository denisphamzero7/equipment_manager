<?php

namespace App\Modules\Core\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Validation\ValidationException;

/**
 * [1. MỤC ĐÍCH]
 * Trait chuẩn hóa định dạng phản hồi JSON API thống nhất cho toàn bộ Controller trong hệ thống:
 * - Cấu trúc thành công: { success: true, message: string, data: any }
 * - Cấu trúc lỗi:        { success: false, message: string, errors?: array, code?: string }
 */
trait RespondsWithJson
{
    /**
     * [1. MỤC ĐÍCH]
     * Trả về JSON Response thành công với dữ liệu thuần (mảng, object, số liệu thống kê).
     *
     * [4. THAM SỐ (PARAMETERS)]
     * @param mixed $data Dữ liệu trả về
     * @param string|null $message Thông điệp thông báo
     * @param int $statusCode Mã HTTP Status (mặc định 200)
     * @return \Illuminate\Http\JsonResponse
     */
    protected function success(
        mixed $data = null,
        ?string $message = null,
        int $statusCode = 200
    ): JsonResponse {
        $payload = array_filter([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], fn ($v) => $v !== null);

        return response()->json($payload, $statusCode);
    }

    /**
     * [1. MỤC ĐÍCH]
     * Trả về JSON Response thành công từ Eloquent JsonResource (tự động gắn envelope success, message).
     *
     * [4. THAM SỐ (PARAMETERS)]
     * @param \Illuminate\Http\Resources\Json\JsonResource $resource Đối tượng Resource (ví dụ: EquipmentResource)
     * @param string|null $message
     * @param int $statusCode (mặc định 200, hoặc 201 khi tạo mới)
     * @return \Illuminate\Http\JsonResponse
     */
    protected function successResource(
        JsonResource $resource,
        ?string $message = null,
        int $statusCode = 200
    ): JsonResponse {
        $additional = array_filter([
            'success' => true,
            'message' => $message,
        ], fn ($v) => $v !== null);

        return $resource->additional($additional)->response()->setStatusCode($statusCode);
    }

    /**
     * [1. MỤC ĐÍCH]
     * Trả về JSON Response cho danh sách phân trang ResourceCollection.
     *
     * [4. THAM SỐ (PARAMETERS)]
     * @param \Illuminate\Http\Resources\Json\ResourceCollection $collection (ví dụ: EquipmentCollection)
     * @param string|null $message
     * @param int $statusCode
     * @return \Illuminate\Http\JsonResponse
     */
    protected function successCollection(
        ResourceCollection $collection,
        ?string $message = null,
        int $statusCode = 200
    ): JsonResponse {
        $additional = array_filter([
            'success' => true,
            'message' => $message,
        ], fn ($v) => $v !== null);

        return $collection->additional($additional)->response()->setStatusCode($statusCode);
    }

    /**
     * [1. MỤC ĐÍCH]
     * Trả về JSON Response thông báo lỗi tổng quát.
     *
     * [4. THAM SỐ (PARAMETERS)]
     * @param string $message Mô tả lỗi
     * @param int $statusCode Mã HTTP Status (mặc định 400)
     * @param array|null $errors Danh sách lỗi chi tiết
     * @param string|null $code Mã định danh lỗi (ví dụ: 'INVALID_CREDENTIALS')
     * @return \Illuminate\Http\JsonResponse
     */
    protected function error(
        string $message,
        int $statusCode = 400,
        ?array $errors = null,
        ?string $code = null
    ): JsonResponse {
        $payload = array_filter([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
            'code' => $code,
        ], fn ($v) => $v !== null);

        return response()->json($payload, $statusCode);
    }

    /**
     * [1. MỤC ĐÍCH]
     * Trả về lỗi kiểm tra dữ liệu đầu vào (Validation Error 422).
     */
    protected function validationError(ValidationException $exception): JsonResponse
    {
        return $this->error(
            $exception->getMessage(),
            422,
            $exception->errors(),
            'VALIDATION_ERROR'
        );
    }

    /**
     * [1. MỤC ĐÍCH]
     * Trả về lỗi chưa đăng nhập hoặc hết hạn phiên làm việc (Unauthorized 401).
     */
    protected function unauthorized(?string $message = null): JsonResponse
    {
        return $this->error(
            $message ?? 'Chưa xác thực',
            401,
            null,
            'UNAUTHORIZED'
        );
    }

    /**
     * [1. MỤC ĐÍCH]
     * Trả về lỗi không đủ quyền thực hiện hành động (Forbidden 403).
     */
    protected function forbidden(?string $message = null): JsonResponse
    {
        return $this->error(
            $message ?? 'Không có quyền truy cập',
            403,
            null,
            'FORBIDDEN'
        );
    }

    /**
     * [1. MỤC ĐÍCH]
     * Trả về lỗi không tìm thấy tài nguyên (Not Found 404).
     */
    protected function notFound(?string $message = null): JsonResponse
    {
        return $this->error(
            $message ?? 'Không tìm thấy tài nguyên',
            404,
            null,
            'NOT_FOUND'
        );
    }

    /**
     * [1. MỤC ĐÍCH]
     * Trả về lỗi xung đột dữ liệu (Conflict 409).
     */
    protected function conflict(?string $message = null): JsonResponse
    {
        return $this->error(
            $message ?? 'Dữ liệu đã tồn tại hoặc xung đột',
            409,
            null,
            'CONFLICT'
        );
    }

    /**
     * [1. MỤC ĐÍCH]
     * Trả về phản hồi thành công không có nội dung (204 No Content).
     */
    protected function noContent(): JsonResponse
    {
        return response()->json(null, 204);
    }
}
