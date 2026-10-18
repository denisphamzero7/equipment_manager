<?php

namespace App\Modules\Equipment\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReportBrokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'issue_description' => 'required|string|max:1000',
            'broken_photo' => 'nullable|image|max:10240', // Ảnh chụp hiện trường hỏng
        ];
    }

    public function messages(): array
    {
        return [
            'issue_description.required' => 'Vui lòng mô tả chi tiết tình trạng hư hỏng của thiết bị.',
            'broken_photo.image' => 'Tệp ảnh bằng chứng hỏng phải là định dạng hình ảnh.',
            'broken_photo.max' => 'Ảnh tải lên không được vượt quá 10MB.',
        ];
    }
}
