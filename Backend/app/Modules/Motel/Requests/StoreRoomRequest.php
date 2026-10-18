<?php

namespace App\Modules\Motel\Requests;

use App\Modules\Motel\Enums\RoomStatusEnum;
use Illuminate\Foundation\Http\FormRequest;

class StoreRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'room_number' => 'required|integer|min:1',
            'room_type_id' => 'nullable|integer|exists:room_types,id',
            'status' => ['required', RoomStatusEnum::rule()],
            'price' => 'nullable|numeric|min:0',
            'organization_id' => 'nullable|integer|exists:organizations,id',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Tên phòng không được để trống.',
            'name.string' => 'Tên phòng phải là chuỗi ký tự.',
            'name.max' => 'Tên phòng không được vượt quá 255 ký tự.',
            'room_number.required' => 'Số phòng không được để trống.',
            'room_number.integer' => 'Số phòng phải là số nguyên.',
            'room_number.min' => 'Số phòng phải lớn hơn 0.',
            'status.in' => 'Trạng thái phòng không hợp lệ. Chỉ chấp nhận ready, occupied, not_ready, maintenance.',
            'room_type_id.exists' => 'Loại phòng không tồn tại.',
            'organization_id.exists' => 'Tổ chức không tồn tại.',
        ];
    }

    public function bodyParameters(): array
    {
        return [];
    }
}
