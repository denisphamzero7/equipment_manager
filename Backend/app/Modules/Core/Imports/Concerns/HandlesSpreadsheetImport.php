<?php

namespace App\Modules\Core\Imports\Concerns;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Exceptions\LaravelExcelException;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Exception as SpreadsheetException;

trait HandlesSpreadsheetImport
{
    protected function executeSpreadsheetImport(object $import, UploadedFile $file): array
    {
        if ((int) $file->getSize() === 0) {
            throw ValidationException::withMessages([
                'file' => ['Tệp tải lên đang trống (0 byte). Vui lòng kiểm tra lại.'],
            ]);
        }

        try {
            Excel::import($import, $file);
        } catch (ValidationException $e) {
            throw $e;
        } catch (SpreadsheetException|LaravelExcelException|\InvalidArgumentException $e) {
            throw ValidationException::withMessages([
                'file' => ['Không thể đọc tệp bảng tính. Vui lòng kiểm tra tệp đúng định dạng .xlsx, .xls hoặc .csv và không bị hỏng.'],
            ]);
        }

        return $import->getResult();
    }

    protected function failedSpreadsheetImportResponse(array $result): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => "Nhập dữ liệu thất bại: không có dòng nào hợp lệ ({$result['failed_count']} dòng lỗi / bỏ qua).",
            'errors' => [
                'file' => [$result['errors'][0] ?? 'Không có dòng dữ liệu nào hợp lệ để nhập.'],
            ],
            'data' => $result,
            'code' => 'IMPORT_VALIDATION_ERROR',
        ], 422);
    }
}
