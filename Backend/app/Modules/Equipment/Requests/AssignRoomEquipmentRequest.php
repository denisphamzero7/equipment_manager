<?php

namespace App\Modules\Equipment\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssignRoomEquipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'equipment_id' => 'required|integer|exists:room_equipments,id',
            'room_id' => 'nullable|integer|exists:rooms,id', // null = chuyển về kho (in_stock)
            'position_note' => 'nullable|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'equipment_id.required' => 'Vui lòng chọn thiết bị cần điều chuyển.',
            'equipment_id.exists' => 'Thiết bị không tồn tại.',
            'room_id.exists' => 'Phòng đích không tồn tại.',
        ];
    }
}
