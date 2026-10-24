<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

/**
 * Universal Storage Route Fallback:
 * Đảm bảo hình ảnh / tài liệu trong storage/app/public luôn truy cập được
 * trên cả 2 môi trường (Docker Linux & Windows Native) ngay cả khi symlink gặp vấn đề.
 */
Route::get('/storage/{path}', function (string $path) {
    $filePath = storage_path('app/public/' . $path);
    if (!file_exists($filePath)) {
        abort(404);
    }
    return response()->file($filePath);
})->where('path', '.*');

