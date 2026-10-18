<?php

namespace App\Modules\Motel\Requests;

use App\Modules\Motel\Enums\RoomStatusEnum;
use Illuminate\Foundation\Http\FormRequest;

class BulkUpdateStatusRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:rooms,id',
            'status' => ['required', RoomStatusEnum::rule()],
        ];
    }

    public function messages(): array
    {
        return [
            'ids.required' => 'Danh sách phòng không được để trống.',
            'ids.array' => 'Danh sách phòng phải là mảng.',
            'ids.*.exists' => 'Một hoặc nhiều phòng không tồn tại.',
            'status.in' => 'Trạng thái phòng không hợp lệ. Chỉ chấp nhận ready, occupied, not_ready, maintenance.',
        ];
    }

    public function bodyParameters(): array
    {
        return [];
    }
}
