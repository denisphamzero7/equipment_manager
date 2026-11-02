<?php

namespace App\Modules\Category\Services;

use App\Modules\Core\Enums\StatusEnum;
use App\Modules\Category\Exports\CategoriesExport;
use App\Modules\Category\Imports\CategoriesImport;
use App\Modules\Category\Models\Category;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * [1. MỤC ĐÍCH]
 * Tầng dịch vụ quản lý Danh mục (Categories) dùng chung trong hệ thống:
 * Quản lý cây phân cấp danh mục cha - con, thống kê trạng thái, xuất/nhập Excel và thao tác hàng loạt.
 */
class CategoryService
{
    /**
     * [1. MỤC ĐÍCH]
     * Thống kê số lượng danh mục theo trạng thái kích hoạt (active) và ngừng hoạt động (inactive).
     *
     * [2. LUỒNG XỬ LÝ (FLOW)]
     * 1. Áp dụng query filter từ `$filters`.
     * 2. Đếm tổng số bản ghi (`total`), số lượng `active` và `inactive`.
     * 3. Trả về mảng thống kê.
     *
     * [3. NGUỒN GỌI (CALLERS)]
     * - Route: GET /api/v1/categories/stats
     * - Controller: App\Modules\Category\CategoryController::stats()
     *
     * [4. THAM SỐ (PARAMETERS)]
     * @param array $filters Bộ lọc tìm kiếm và trạng thái
     * @return array Mảng chứa: total, active, inactive
     */
    public function stats(array $filters): array
    {
        $base = Category::filter($filters);

        return [
            'total' => (clone $base)->count(),
            'active' => (clone $base)->where('status', StatusEnum::Active->value)->count(),
            'inactive' => (clone $base)->where('status', StatusEnum::Inactive->value)->count(),
        ];
    }

    /**
     * [1. MỤC ĐÍCH]
     * Lấy danh sách danh mục có phân trang, hỗ trợ tìm kiếm và eager load.
     *
     * [2. LUỒNG XỬ LÝ (FLOW)]
     * 1. Áp dụng filter qua model scope `filter($filters)`.
     * 2. Phân trang theo số lượng `limit`.
     * 3. Trả về LengthAwarePaginator.
     *
     * [3. NGUỒN GỌI (CALLERS)]
     * - Route: GET /api/v1/categories
     * - Controller: App\Modules\Category\CategoryController::index()
     *
     * [4. THAM SỐ (PARAMETERS)]
     * @param array $filters Bộ lọc tìm kiếm, trạng thái, ngày tạo
     * @param int $limit Số bản ghi trên mỗi trang
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function index(array $filters, int $limit)
    {
        return Category::filter($filters)
            ->paginate($limit);
    }

    /**
     * [1. MỤC ĐÍCH]
     * Lấy thông tin chi tiết của một danh mục kèm danh mục cha và người tạo/sửa.
     *
     * [4. THAM SỐ (PARAMETERS)]
     * @param \App\Modules\Category\Models\Category $category Đối tượng danh mục
     * @return \App\Modules\Category\Models\Category
     */
    public function show(Category $category): Category
    {
        return $category->load(['creator', 'editor', 'parent']);
    }

    /**
     * [1. MỤC ĐÍCH]
     * Tạo mới một danh mục thuộc tổ chức hiện tại.
     *
     * [2. LUỒNG XỬ LÝ (FLOW)]
     * 1. Bắt đầu Database Transaction.
     * 2. Gán `organization_id` theo tổ chức đang chọn (`resolveCurrentOrganizationId()`).
     * 3. Tạo bản ghi `Category::create($data)`.
     * 4. Eager load creator, editor, parent và trả về kết quả.
     *
     * [4. THAM SỐ (PARAMETERS)]
     * @param array $validated Dữ liệu tạo danh mục (name, slug, parent_id, status...)
     * @return \App\Modules\Category\Models\Category
     * @throws \Throwable
     */
    public function store(array $validated): Category
    {
        return DB::transaction(function () use ($validated) {
            $data = $validated;
            $data['organization_id'] = $this->resolveCurrentOrganizationId();
            
            $category = Category::create($data);

            return $category->load(['creator', 'editor', 'parent']);
        });
    }

    /**
     * [1. MỤC ĐÍCH]
     * Cập nhật thông tin danh mục.
     *
     * [4. THAM SỐ (PARAMETERS)]
     * @param \App\Modules\Category\Models\Category $category
     * @param array $validated
     * @return array
     */
    public function update(Category $category, array $validated): array
    {
        try {
            return DB::transaction(function () use ($category, $validated) {
                $category->update($validated);
                return [
                    'ok' => true,
                    'category' => $category->load(['creator', 'editor', 'parent'])
                ];
            });
        } catch (\Exception $e) {
            return [
                'ok' => false,
                'message' => 'Lỗi cập nhật: ' . $e->getMessage(),
                'code' => 500,
                'error_code' => 'UPDATE_ERROR'
            ];
        }
    }

    /**
     * [1. MỤC ĐÍCH]
     * Xóa một danh mục khỏi hệ thống.
     * @param \App\Modules\Category\Models\Category $category
     * @return void
     */
    public function destroy(Category $category): void
    {
        $category->delete();
    }

    /**
     * [1. MỤC ĐÍCH]
     * Xóa đồng thời nhiều danh mục theo mảng ID.
     * @param array<int> $ids
     * @return void
     */
    public function bulkDestroy(array $ids): void
    {
        DB::transaction(function () use ($ids) {
            Category::where('organization_id', $this->resolveCurrentOrganizationId())
                ->whereIn('id', $ids)
                ->get()
                ->each
                ->delete();
        });
    }

    /**
     * [1. MỤC ĐÍCH]
     * Đổi trạng thái hàng loạt cho nhiều danh mục.
     * @param array<int> $ids
     * @param string $status
     * @return void
     */
    public function bulkUpdateStatus(array $ids, string $status): void
    {
        Category::where('organization_id', $this->resolveCurrentOrganizationId())
            ->whereIn('id', $ids)
            ->update(['status' => $status]);
    }

    /**
     * [1. MỤC ĐÍCH]
     * Xuất danh sách danh mục ra file Excel.
     * @param array $filters
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function export(array $filters): BinaryFileResponse
    {
        return Excel::download(
            new CategoriesExport($filters, $this->resolveCurrentOrganizationId()), 
            'categories.xlsx'
        );
    }

    /**
     * [1. MỤC ĐÍCH]
     * Nhập danh sách danh mục từ file Excel.
     * @param mixed $file
     * @return void
     */
    public function import($file): void
    {
        Excel::import(new CategoriesImport($this->resolveCurrentOrganizationId()), $file);
    }

    /**
     * [1. MỤC ĐÍCH]
     * Xác định ID tổ chức hiện tại từ session/token.
     * @return int
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    private function resolveCurrentOrganizationId(): int
    {
        $organizationId = function_exists('getPermissionsTeamId') ? getPermissionsTeamId() : null;

        if (! is_numeric($organizationId) || (int) $organizationId <= 0) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException('Không xác định được tổ chức làm việc hiện tại.');
        }

        return (int) $organizationId;
    }
}
