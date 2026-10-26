<?php

use App\Modules\Equipment\Controllers\EquipmentController;
use App\Modules\Equipment\Controllers\EquipmentMapViewController;
use App\Modules\Equipment\Controllers\EquipmentModelController;
use App\Modules\Equipment\Controllers\RoomEquipmentDetailController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Equipment Module Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {
    // 1. Hotel Map View & Sơ đồ mặt bằng
    Route::get('map-view/blocks', [EquipmentMapViewController::class, 'getBlocks']);
    Route::get('map-view/floors', [EquipmentMapViewController::class, 'getFloors']);
    Route::get('map-view', [EquipmentMapViewController::class, 'index']);

    // 2. Chi tiết thiết bị phòng khi click chọn phòng
    Route::get('rooms/{roomId}', [RoomEquipmentDetailController::class, 'show']);

    // 3. Thống kê, Xuất / Nhập Excel
    Route::get('stats', [EquipmentController::class, 'stats']);
    Route::get('export', [EquipmentController::class, 'export']);
    Route::post('import', [EquipmentController::class, 'import']);

    // 4. Thao tác điều chuyển & Báo hỏng thiết bị
    Route::post('assign-to-room', [EquipmentController::class, 'assignToRoom']);
    Route::get('{id}/check-assign-target', [EquipmentController::class, 'checkAssignTarget'])->whereNumber('id');
    Route::post('{id}/report-broken', [EquipmentController::class, 'reportBroken'])->whereNumber('id');
    Route::patch('maintenance-logs/{logId}', [EquipmentController::class, 'updateMaintenanceLog']);
    Route::post('{id}/upload-photo', [EquipmentController::class, 'uploadPhoto'])->whereNumber('id');
    Route::delete('{id}/photos/{mediaId}', [EquipmentController::class, 'deletePhoto'])->whereNumber('id');

    // 5. Thao tác hàng loạt (Bulk actions) - PHẢI ĐẶT TRƯỚC {id} ĐỂ TRÁNH MATCHING NHẦM
    Route::patch('bulk/status', [EquipmentController::class, 'bulkUpdateStatus']);
    Route::post('bulk/destroy', [EquipmentController::class, 'bulkDestroy']);

    // Cập nhật trạng thái từng thiết bị
    Route::patch('{id}/status', [EquipmentController::class, 'changeStatus'])->whereNumber('id');

    // 6. Quản lý Mẫu thiết bị (Equipment Models) & Danh mục (Categories)
    Route::get('models', [EquipmentModelController::class, 'index']);
    Route::post('models', [EquipmentModelController::class, 'store']);
    Route::put('models/{id}', [EquipmentModelController::class, 'update']);
    Route::delete('models/{id}', [EquipmentModelController::class, 'destroy']);
    Route::get('categories', [EquipmentModelController::class, 'categories']);
    Route::post('categories', [EquipmentModelController::class, 'storeCategory']);

    // 7. Danh sách phòng phục vụ chọn lựa (Cascader)
    Route::get('room-options', [EquipmentController::class, 'getRoomOptions']);

    // 8. CRUD thiết bị
    Route::get('', [EquipmentController::class, 'index']);
    Route::get('{id}', [EquipmentController::class, 'show'])->whereNumber('id');
    Route::post('', [EquipmentController::class, 'store']);
    Route::put('{id}', [EquipmentController::class, 'update'])->whereNumber('id');
    Route::delete('{id}', [EquipmentController::class, 'destroy'])->whereNumber('id');
});
