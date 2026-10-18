<?php

namespace App\Modules\Motel\Requests;

use App\Modules\Motel\Enums\RoomStatusEnum;
use Illuminate\Foundation\Http\FormRequest;

class ChangeStatusRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', RoomStatusEnum::rule()],
        ];
    }

    public function messages(): array
    {
        return [
            'status.in' => 'Trạng thái phòng không hợp lệ. Chỉ chấp nhận ready, occupied, not_ready, maintenance.',
        ];
    }

    public function bodyParameters(): array
    {
        return [];
    }
}
