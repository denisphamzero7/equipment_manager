<?php

use App\Modules\Motel\Controllers\RoomController as Motel;
use Illuminate\Support\Facades\Route;

// Block / Structure management
Route::post('/structure/rename-block', [Motel::class, 'renameBlock'])->middleware('permission:motel.update,web');
Route::delete('/structure/delete-block', [Motel::class, 'deleteBlock'])->middleware('permission:motel.destroy,web');

// Batch generation & Room types
Route::post('/batch-generate', [Motel::class, 'batchGenerate'])->middleware('permission:motel.store,web');
Route::get('/room-types', [Motel::class, 'getRoomTypes'])->middleware('permission:motel.index|motel.update,web');

Route::get('/export', [Motel::class, 'export'])->middleware('permission:motel.export,web');
Route::post('/import', [Motel::class, 'import'])->middleware('permission:motel.import,web');
Route::post('/bulk-delete', [Motel::class, 'bulkDestroy'])->middleware('permission:motel.bulkDestroy,web');
Route::patch('/bulk-status', [Motel::class, 'bulkUpdateStatus'])->middleware('permission:motel.bulkUpdateStatus,web');
Route::get('/stats', [Motel::class, 'stats'])->middleware('permission:motel.stats,web');

// Base routes for motel
Route::get('/', [Motel::class, 'index'])->middleware('permission:motel.index,web');
Route::post('/', [Motel::class, 'store'])->middleware('permission:motel.store,web');

// Subgroup /rooms for API consistency
Route::prefix('rooms')->group(function () {
    Route::get('/', [Motel::class, 'index'])->middleware('permission:motel.index,web');
    Route::post('/', [Motel::class, 'store'])->middleware('permission:motel.store,web');
    Route::post('/batch-generate', [Motel::class, 'batchGenerate'])->middleware('permission:motel.store,web');
    Route::get('/room-types', [Motel::class, 'getRoomTypes'])->middleware('permission:motel.index|motel.update,web');
    Route::post('/bulk-delete', [Motel::class, 'bulkDestroy'])->middleware('permission:motel.bulkDestroy,web');
    Route::get('/{room}', [Motel::class, 'show'])->middleware('permission:motel.show,web');
    Route::put('/{room}', [Motel::class, 'update'])->middleware('permission:motel.update,web');
    Route::patch('/{room}', [Motel::class, 'update'])->middleware('permission:motel.update,web');
    Route::delete('/{room}', [Motel::class, 'destroy'])->middleware('permission:motel.destroy,web');
    Route::patch('/{room}/status', [Motel::class, 'changeStatus'])->middleware('permission:motel.changeStatus,web');
});

Route::get('/{room}', [Motel::class, 'show'])->middleware('permission:motel.show,web');
Route::put('/{room}', [Motel::class, 'update'])->middleware('permission:motel.update,web');
Route::patch('/{room}', [Motel::class, 'update'])->middleware('permission:motel.update,web');
Route::delete('/{room}', [Motel::class, 'destroy'])->middleware('permission:motel.destroy,web');
Route::patch('/{room}/status', [Motel::class, 'changeStatus'])->middleware('permission:motel.changeStatus,web');
