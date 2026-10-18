<?php

namespace App\Modules\Equipment\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportEquipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Vui lòng chọn tệp Excel để nhập dữ liệu.',
            'file.file' => 'Tệp tải lên không hợp lệ.',
            'file.mimes' => 'Hệ thống chỉ hỗ trợ định dạng Excel (.xlsx, .xls) hoặc .csv.',
            'file.max' => 'Dung lượng tệp không được vượt quá 10MB.',
        ];
    }
}
