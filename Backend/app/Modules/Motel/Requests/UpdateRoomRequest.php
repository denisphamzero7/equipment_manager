<?php

namespace App\Modules\Motel\Requests;

use App\Modules\Motel\Enums\RoomStatusEnum;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'room_number' => 'sometimes|integer|min:1',
            'block' => 'nullable|string|max:100',
            'floor' => 'nullable|integer',
            'grid_row' => 'nullable|integer',
            'grid_col' => 'nullable|integer',
            'room_type_id' => 'nullable|integer|exists:room_types,id',
            'status' => ['sometimes', RoomStatusEnum::rule()],
            'price' => 'nullable|numeric|min:0',
            'organization_id' => 'nullable|integer|exists:organizations,id',
        ];
    }

    public function messages(): array
    {
        return [
            'room_number.integer' => 'Số phòng phải là số nguyên.',
            'room_number.min' => 'Số phòng phải lớn hơn 0.',
            'status.in' => 'Trạng thái phòng không hợp lệ.',
            'room_type_id.exists' => 'Loại phòng không tồn tại.',
            'organization_id.exists' => 'Tổ chức không tồn tại.',
        ];
    }
}
