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
            'equipment_id' => 'required_without:equipment_ids|nullable|integer|exists:room_equipments,id',
            'equipment_ids' => 'required_without:equipment_id|nullable|array|min:1',
            'equipment_ids.*' => 'integer|exists:room_equipments,id',
            'room_id' => 'nullable|integer|exists:rooms,id', // null = chuyển về kho (in_stock)
            'position_note' => 'nullable|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'equipment_id.required_without' => 'Vui lòng chọn ít nhất một thiết bị cần điều chuyển.',
            'equipment_ids.required_without' => 'Vui lòng chọn ít nhất một thiết bị cần điều chuyển.',
            'equipment_id.exists' => 'Thiết bị không tồn tại.',
            'equipment_ids.*.exists' => 'Một trong các thiết bị đã chọn không tồn tại.',
            'room_id.exists' => 'Phòng đích không tồn tại.',
        ];
    }
}
