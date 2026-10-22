<?php

namespace App\Modules\Equipment\Requests;

use App\Modules\Equipment\Enums\EquipmentStatusEnum;
use Illuminate\Foundation\Http\FormRequest;

class StoreEquipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'equipment_model_id' => 'required|integer|exists:equipment_models,id',
            'serial_number' => 'nullable|string|max:100|unique:room_equipments,serial_number',
            'room_id' => 'nullable|integer|exists:rooms,id',
            'status' => 'nullable|string|'.EquipmentStatusEnum::rule(),
            'position_note' => 'nullable|string|max:255',
            'note' => 'nullable|string|max:1000',
            'installed_at' => 'nullable|date',
            'photo' => 'nullable|image|max:10240', // Cho phép upload ảnh lúc tạo mới
            'client_request_id' => 'nullable|string|max:100',
        ];
    }

    public function messages(): array
    {
        return [
            'equipment_model_id.required' => 'Vui lòng chọn mẫu mã thiết bị (model).',
            'equipment_model_id.exists' => 'Mẫu mã thiết bị không tồn tại.',
            'serial_number.unique' => 'Mã Serial này đã tồn tại trong hệ thống.',
            'room_id.exists' => 'Phòng được chỉ định không tồn tại.',
            'photo.image' => 'Tệp tải lên phải là hình ảnh hợp lệ.',
            'photo.max' => 'Ảnh tải lên không được vượt quá 10MB.',
        ];
    }
}
