<?php

use App\Modules\Equipment\Controllers\EquipmentController;
use App\Modules\Equipment\Controllers\EquipmentMapViewController;
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
    Route::get('map-view', [EquipmentMapViewController::class, 'index']);

    // 2. Chi tiết thiết bị phòng khi click chọn phòng
    Route::get('rooms/{roomId}', [RoomEquipmentDetailController::class, 'show']);

    // 3. Thống kê, Xuất / Nhập Excel
    Route::get('stats', [EquipmentController::class, 'stats']);
    Route::get('export', [EquipmentController::class, 'export']);
    Route::post('import', [EquipmentController::class, 'import']);

    // 4. Thao tác điều chuyển & Báo hỏng thiết bị
    Route::post('assign-to-room', [EquipmentController::class, 'assignToRoom']);
    Route::post('{id}/report-broken', [EquipmentController::class, 'reportBroken']);
    Route::post('{id}/upload-photo', [EquipmentController::class, 'uploadPhoto']);

    // 5. Cập nhật trạng thái
    Route::patch('{id}/status', [EquipmentController::class, 'changeStatus']);
    Route::patch('bulk/status', [EquipmentController::class, 'bulkUpdateStatus']);
    Route::post('bulk/destroy', [EquipmentController::class, 'bulkDestroy']);

    // 6. CRUD thiết bị
    Route::get('', [EquipmentController::class, 'index']);
    Route::get('{id}', [EquipmentController::class, 'show']);
    Route::post('', [EquipmentController::class, 'store']);
    Route::put('{id}', [EquipmentController::class, 'update']);
    Route::delete('{id}', [EquipmentController::class, 'destroy']);
});
