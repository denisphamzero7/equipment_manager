<?php

namespace App\Modules\Equipment\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreStandardEquipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'room_type_id' => 'required|integer',
            'equipment_model_id' => 'required|integer|exists:equipment_models,id',
            'standard_quantity' => 'required|integer|min:1|max:100',
        ];
    }

    public function messages(): array
    {
        return [
            'room_type_id.required' => 'Vui lòng chọn loại phòng.',
            'equipment_model_id.required' => 'Vui lòng chọn mẫu thiết bị.',
            'equipment_model_id.exists' => 'Mẫu thiết bị không tồn tại.',
            'standard_quantity.required' => 'Vui lòng nhập số lượng định mức.',
            'standard_quantity.min' => 'Số lượng định mức tối thiểu là 1.',
        ];
    }
}
