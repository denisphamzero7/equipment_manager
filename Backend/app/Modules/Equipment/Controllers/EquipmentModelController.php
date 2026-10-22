<?php

namespace App\Modules\Equipment\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Equipment\Models\EquipmentCategory;
use App\Modules\Equipment\Models\EquipmentModel;
use App\Modules\Equipment\Services\EquipmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * @group Quản lý Mẫu / Tên Thiết bị (Equipment Models)
 *
 * API Thêm, Sửa, Xóa danh mục quy cách & tên thiết bị (Tivi, Box, Router,...)
 */
class EquipmentModelController extends Controller
{
    public function __construct(
        protected EquipmentService $equipmentService
    ) {}

    /**
     * Danh sách toàn bộ mẫu thiết bị
     */
    public function index(Request $request): JsonResponse
    {
        $orgId = $request->user()?->organization_id;
        $query = EquipmentModel::with('category')->withCount('equipments');

        if ($orgId) {
            $query->where(function ($q) use ($orgId) {
                $q->where('organization_id', $orgId)->orWhereNull('organization_id');
            });
        }

        $models = $query->orderBy('name', 'asc')->get();

        return $this->success($models, 'Lấy danh sách mẫu thiết bị thành công');
    }

    /**
     * Danh sách nhóm danh mục thiết bị (TV, Remote, Box, Wifi,...)
     */
    public function categories(): JsonResponse
    {
        $categories = EquipmentCategory::orderBy('sort_order', 'asc')
            ->orderBy('name', 'asc')
            ->get();

        return $this->success($categories, 'Lấy danh mục nhóm thiết bị thành công');
    }

    /**
     * Tạo nhóm danh mục thiết bị.
     */
    public function storeCategory(Request $request): JsonResponse
    {
        if (is_string($request->input('name'))) {
            $request->merge(['name' => trim($request->input('name'))]);
        }

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('equipment_categories', 'name')->whereNull('deleted_at'),
            ],
        ], [
            'name.required' => 'Vui lòng nhập tên nhóm danh mục.',
            'name.unique' => 'Tên nhóm danh mục này đã tồn tại.',
        ]);

        $baseCode = substr(Str::slug($validated['name']) ?: 'category', 0, 240);
        $code = $baseCode;
        $suffix = 2;
        while (EquipmentCategory::withTrashed()->where('code', $code)->exists()) {
            $code = "{$baseCode}-{$suffix}";
            $suffix++;
        }

        $category = EquipmentCategory::create([
            'name' => $validated['name'],
            'code' => $code,
            'sort_order' => (int) EquipmentCategory::max('sort_order') + 1,
            'organization_id' => $request->user()?->organization_id,
        ]);

        return $this->success($category, 'Thêm nhóm danh mục thiết bị thành công', 201);
    }

    /**
     * Tạo mới một mẫu / tên thiết bị
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'equipment_category_id' => 'required|integer|exists:equipment_categories,id',
            'brand' => 'nullable|string|max:100',
            'model_code' => 'nullable|string|max:100',
            'specs' => 'nullable|array',
        ], [
            'name.required' => 'Vui lòng nhập tên mẫu thiết bị.',
            'equipment_category_id.required' => 'Vui lòng chọn nhóm danh mục cho thiết bị.',
            'equipment_category_id.exists' => 'Nhóm danh mục không tồn tại.',
        ]);

        $validated['organization_id'] = $request->user()?->organization_id;

        $model = EquipmentModel::create($validated);
        $model->load('category');
        $model->loadCount('equipments');
        $this->equipmentService->invalidateCache($model->organization_id);

        return $this->success($model, 'Thêm mẫu thiết bị mới thành công', 201);
    }

    /**
     * Cập nhật thông tin / đổi tên mẫu thiết bị
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $model = EquipmentModel::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'equipment_category_id' => 'required|integer|exists:equipment_categories,id',
            'brand' => 'nullable|string|max:100',
            'model_code' => 'nullable|string|max:100',
            'specs' => 'nullable|array',
        ], [
            'name.required' => 'Vui lòng nhập tên mẫu thiết bị.',
            'equipment_category_id.required' => 'Vui lòng chọn nhóm danh mục cho thiết bị.',
            'equipment_category_id.exists' => 'Nhóm danh mục không tồn tại.',
        ]);

        $model->update($validated);
        $model->load('category');
        $model->loadCount('equipments');
        $this->equipmentService->invalidateCache($model->organization_id);

        return $this->success($model, 'Cập nhật mẫu thiết bị thành công');
    }

    /**
     * Xóa mẫu thiết bị
     */
    public function destroy(int $id): JsonResponse
    {
        $model = EquipmentModel::withCount('equipments')->findOrFail($id);

        if ($model->equipments_count > 0) {
            return $this->error(
                "Không thể xóa mẫu thiết bị '{$model->name}' vì đang có {$model->equipments_count} thiết bị thực tế trong hệ thống sử dụng mẫu này.",
                422
            );
        }

        $organizationId = $model->organization_id;
        $model->delete();
        $this->equipmentService->invalidateCache($organizationId);

        return $this->success(null, "Đã xóa mẫu thiết bị '{$model->name}' thành công");
    }
}
